<?php

namespace RSE\Delivra\WebPush\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use RSE\Delivra\WebPush\Subscription;
use RSE\Delivra\WebPush\WebPushManager;
use RSE\Delivra\WebPush\WebPushReport;

class WebPushChannel
{
    /**
     * Never throws for delivery failures, only for configuration errors.
     * Nothing sent is [], as on every delivra channel.
     */
    public function send(object $notifiable, Notification $notification): WebPushReport|array
    {
        $route = $this->route($notifiable, $notification);

        // a route object goes to the sender registered with WebPushManager::sendUsing()
        if (WebPushManager::senderFor($route)) {
            return WebPushManager::sendThroughSender($notification->toWebPush($notifiable, $notification), $route, $notifiable, $notification);
        }

        $subscriptions = Subscription::list($route);

        if ($subscriptions === []) {
            return [];
        }

        // the second argument keeps notifications written for laravel-notification-channels/webpush working
        $message = $notification->toWebPush($notifiable, $notification);

        if ($message === null) {
            return [];
        }

        // resolve on each send so WebPush::fake() is picked up
        $report = app('delivra-webpush')->sendTo($subscriptions, $message, $notifiable, $notification);

        return $report->isEmpty() ? [] : $report;
    }

    protected function route(object $notifiable, Notification $notification): mixed
    {
        if (! method_exists($notifiable, 'routeNotificationFor')) {
            return null;
        }

        foreach (array_unique(array_filter(['WebPush', config('delivra.webpush.channel'), self::class])) as $name) {
            $route = $notifiable->routeNotificationFor($name, $notification);
            $route = $route instanceof Collection ? $route->all() : $route;

            if (! empty($route)) {
                return $route;
            }
        }

        return null;
    }
}
