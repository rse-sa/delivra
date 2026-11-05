<?php

namespace RSE\Delivra\WhatsApp\Channels;

use Illuminate\Notifications\Notification;
use RSE\Delivra\Exceptions\WhatsApp\RecipientBlockedException;
use RSE\Delivra\WhatsApp\Messages\Message;
use RSE\Delivra\WhatsApp\Templates\Template;
use RSE\Delivra\WhatsApp\WhatsAppResponse;

class WhatsAppChannel
{
    /**
     * @return list<WhatsAppResponse>
     */
    public function send(object $notifiable, Notification $notification): array
    {
        /** @var Message|Template|null $message */
        $message = $notification->toWhatsApp($notifiable);

        if ($message === null) {
            return [];
        }

        // resolve on each send so WhatsApp::fake() is picked up
        $manager = app('delivra-whatsapp');
        $message = $manager->prepare($message);

        $recipients = $message->getRecipients()
            ?: array_filter((array) $notifiable->routeNotificationFor('whatsapp', $notification));

        if ($recipients === []) {
            return [];
        }

        $account   = $manager->accountFor($message);
        $responses = [];

        foreach ($recipients as $recipient) {
            try {
                $response = $manager->sendTo($account, $recipient, $message, $notifiable, $notification);
            } catch (RecipientBlockedException) {
                continue;
            }

            if ($response) {
                $responses[] = $response;
            }
        }

        return $responses;
    }
}
