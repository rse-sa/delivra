<?php

namespace RSE\Delivra\InApp\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use RSE\Delivra\InApp\Messages\InAppMessage;
use RSE\Delivra\InApp\Models\InAppNotification;

class InAppNotificationCreated
{
    public function __construct(
        public readonly InAppNotification $notification,
        public readonly Model $notifiable,
        public readonly ?Notification $source = null,
        public readonly ?InAppMessage $message = null,
    ) {}
}
