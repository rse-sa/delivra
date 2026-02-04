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
        if (! method_exists($notification, 'toSms')) {
            return false;
        }

        $message = $notification->toSms($notifiable);

        if (! $message instanceof SmsBuilder) {
            return false;
        }

        if (empty($message->getRecipients())) {
            if ($to = $notifiable->routeNotificationFor('sms', $notification)) {
                $message->to($to);
            } else {
                return false;
            }
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

            $eventResults = event(new DelivraMessageSending('sms', $recipient, $body, $payload, $driver, null, $notifiable, $notification));
            if (isset($eventResults[0]) && $eventResults[0] === false) {
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
