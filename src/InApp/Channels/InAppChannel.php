<?php

namespace RSE\Delivra\InApp\Channels;

use Illuminate\Notifications\Notification;
use RSE\Delivra\InApp\Models\InAppNotification;

class InAppChannel
{
    /**
     * Nothing stored is [], as on every delivra channel.
     */
    public function send(object $notifiable, Notification $notification): InAppNotification|array
    {
        $message = $notification->toInApp($notifiable);

        if ($message === null) {
            return [];
        }

        // resolve on each send so InApp::fake() is picked up
        return app('delivra-in-app')->send($notifiable, $message, $notification) ?? [];
    }
}
