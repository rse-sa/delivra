<?php

namespace RSE\Delivra\Telegram\Channels;

use Exception;
use Illuminate\Notifications\Notification;
use RSE\Delivra\Telegram\Messages\TelegramMessageAbstract;
use RSE\Delivra\Telegram\Telegram;

class TelegramChannel
{
    public function __construct(protected Telegram $telegram) {}

    public function send($notifiable, Notification $notification)
    {
        if (! method_exists($notification, 'toTelegram')) {
            throw new Exception('toTelegram method is missing');
        }

        $message = $notification->toTelegram($notifiable);

        // nothing sent is always [], on every delivra channel
        if ($message === null) {
            return [];
        }

        if (! $message instanceof TelegramMessageAbstract) {
            throw new Exception('Wrong return format from toTelegram method');
        }

        // Get receivers from notifiable if not set on message
        if (empty($message->getReceivers())) {
            $to = $notifiable->routeNotificationFor('telegram', $notification);

            // a route object (a linked user, say) goes to the sender registered with Telegram::sendUsing()
            if (($results = Telegram::sendThroughSender($message, $to, $notifiable, $notification)) !== null) {
                return $results;
            }

            if (! $to) {
                return [];
            }

            $message->to($to);
        }

        $receivers = $message->getReceivers();
        $results   = [];

        foreach ($receivers as $chatId) {
            $singleMessage = clone $message;
            $singleMessage->to([$chatId]);

            // same path as telegram()->send(): uploads, typed failures, cancellable sending event
            if ($response = $this->telegram->deliver($singleMessage, (string) $chatId, $notifiable, $notification)) {
                $results[] = $response;
            }
        }

        return $results;
    }
}
