<?php

namespace RSE\Delivra\Fcm\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use RSE\Delivra\Fcm\FcmManager;
use RSE\Delivra\Fcm\FcmReport;
use RSE\Delivra\Fcm\Messages\FcmMessage;
use RSE\Delivra\Fcm\PendingFcm;
use RSE\Delivra\Fcm\Target;

class FcmChannel
{
    /** Platforms FCM can reach. Other keys in a platform map are skipped. */
    public const PLATFORMS = ['android', 'ios', 'web'];

    /**
     * Never throws for token or delivery failures, only for configuration errors.
     * Nothing sent is [], as on every delivra channel.
     */
    public function send(object $notifiable, Notification $notification): FcmReport|array
    {
        $route = $this->route($notifiable, $notification);

        // a route object goes to the sender registered with FcmManager::sendUsing()
        if (FcmManager::senderFor($route)) {
            return FcmManager::sendThroughSender($notification->toFcm($notifiable), $route, $notifiable, $notification);
        }

        if ($this->isPlatformMap($route)) {
            $report = null;

            foreach (self::PLATFORMS as $platform) {
                if (empty($route[$platform])) {
                    continue;
                }

                $sent = $this->deliver($notifiable, $notification, $notification->toFcm($notifiable, $platform), $route[$platform]);

                $report = $sent ? ($report?->merge($sent) ?? $sent) : $report;
            }

            return $report ?? [];
        }

        return $this->deliver($notifiable, $notification, $notification->toFcm($notifiable), $route) ?? [];
    }

    protected function deliver(object $notifiable, Notification $notification, ?FcmMessage $message, mixed $tokens): ?FcmReport
    {
        if ($message === null) {
            return null;
        }

        // resolve on each send so Fcm::fake() is picked up
        $manager = app('delivra-fcm');
        $pending = new PendingFcm($manager, $manager->project($message->getProject()));
        $tokens  = Target::tokens($tokens instanceof Collection ? $tokens->all() : (array) $tokens);

        if ($manager->targetsFor($message) === [] && $tokens === []) {
            return null;
        }

        // a topic or condition on the message wins over the notifiable's tokens
        if ($manager->targetsFor($message) === []) {
            $pending->to($tokens);
        }

        return $pending->send($message, $notifiable, $notification);
    }

    protected function route(object $notifiable, Notification $notification): mixed
    {
        if (! method_exists($notifiable, 'routeNotificationFor')) {
            return null;
        }

        foreach (array_unique(array_filter(['fcm', config('delivra.fcm.channel'), self::class])) as $name) {
            $route = $notifiable->routeNotificationFor($name, $notification);

            if (! empty($route)) {
                return $route instanceof Collection ? $route->all() : $route;
            }
        }

        return null;
    }

    /**
     * ['android' => [...], 'ios' => [...]] instead of a token list.
     */
    protected function isPlatformMap(mixed $route): bool
    {
        return is_array($route) && $route !== [] && collect(array_keys($route))->every(fn ($key) => is_string($key));
    }
}
