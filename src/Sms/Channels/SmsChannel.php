<?php

namespace RSE\Delivra\Sms\Channels;

use Exception;
use Illuminate\Notifications\Notification;
use RSE\Delivra\Events\DelivraMessageFailed;
use RSE\Delivra\Events\DelivraMessageSending;
use RSE\Delivra\Events\DelivraMessageSent;
use RSE\Delivra\Sms\Sms;
use RSE\Delivra\Sms\SmsBuilder;

class SmsChannel
{
    public function __construct(protected Sms $sms) {}

    public function send($notifiable, Notification $notification)
    {
        // nothing sent is always [], on every delivra channel
        if (! method_exists($notification, 'toSms')) {
            return [];
        }

        $message = $notification->toSms($notifiable);

        if (! $message instanceof SmsBuilder) {
            return [];
        }

        if (empty($message->getRecipients())) {
            $to = $notifiable->routeNotificationFor('sms', $notification);

            // a route object goes to the sender registered with Sms::sendUsing()
            if (($results = Sms::sendThroughSender($message, $to, $notifiable, $notification)) !== null) {
                return $results;
            }

            if (! $to) {
                return [];
            }

            $message->to($to);
        }

        $this->validate($message);

        if (! empty($message->getDriver())) {
            $this->sms->via($message->getDriver());
        }

        $recipients = $message->getRecipients();
        $driver     = $message->getDriver() ?: $this->sms->getDriver();
        $body       = $message->getBody();
        $results    = [];

        foreach ($recipients as $recipient) {
            $payload = [
                'recipient' => $recipient,
                'body'      => $body,
                'driver'    => $driver,
            ];

            // halt mode: Laravel never puts a listener's false into the normal results
            if (event(new DelivraMessageSending('sms', $recipient, $body, $payload, $driver, null, $notifiable, $notification), [], true) === false) {
                continue;
            }

            try {
                $singleMessage = clone $message;
                $singleMessage->to([$recipient]);
                $response = $this->sms->send($singleMessage);

                event(new DelivraMessageSent('sms', $recipient, $body, $response->getMessageId(), $response, $notifiable, $notification));

                $results[] = $response;
            } catch (\Throwable $e) {
                event(new DelivraMessageFailed('sms', $recipient, $body, $e->getMessage(), $e, $notifiable, $notification));
                throw $e;
            }
        }

        return $results;
    }

    private function validate($message): void
    {
        $conditions = [
            'Invalid data for sms notification.' => ! $message instanceof SmsBuilder,
            'Message body could not be empty.'   => empty($message->getBody()),
        ];

        foreach ($conditions as $ex => $condition) {
            throw_if($condition, new Exception($ex));
        }
    }
}
