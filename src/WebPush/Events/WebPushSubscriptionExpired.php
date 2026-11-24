<?php

namespace RSE\Delivra\WebPush\Events;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use RSE\Delivra\WebPush\Subscription;

/**
 * The push service says the subscription is gone (HTTP 404 or 410). Delete it.
 * Fired before delete_expired deletes the model.
 */
class WebPushSubscriptionExpired
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  Subscription  $subscription  A value object, so queued listeners still get it after the row is deleted.
     *                                      Its source holds the model or array the app passed in.
     */
    public function __construct(
        public readonly string $endpoint,
        public readonly ?int $status,
        public readonly Subscription $subscription,
        public readonly mixed $notifiable = null,
        public readonly mixed $notification = null,
    ) {}
}
