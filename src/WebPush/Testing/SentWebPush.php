<?php

namespace RSE\Delivra\WebPush\Testing;

use RSE\Delivra\WebPush\Messages\WebPushMessage;
use RSE\Delivra\WebPush\Subscription;

class SentWebPush
{
    public function __construct(
        public readonly Subscription $subscription,
        public readonly WebPushMessage $message,
        public readonly array $payload,
        public readonly array $options,
    ) {}

    public function endpoint(): string
    {
        return $this->subscription->endpoint;
    }
}
