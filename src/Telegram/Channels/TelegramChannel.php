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

        if (! $message instanceof TelegramMessageAbstract) {
            throw new Exception('Wrong return format from toTelegram method');
        }

        // Get receivers from notifiable if not set on message
        if (empty($message->getReceivers())) {
            if ($to = $notifiable->routeNotificationFor('telegram', $notification)) {
                $message->to($to);
            } else {
                throw new Exception('No receivers specified for Telegram message');
            }
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
