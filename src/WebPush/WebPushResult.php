<?php

namespace RSE\Delivra\WebPush;

use Throwable;

/**
 * Outcome for one subscription of a send.
 */
class WebPushResult
{
    public const SENT = 'sent';

    public const EXPIRED = 'expired';

    public const FAILED = 'failed';

    public function __construct(
        public readonly Subscription $subscription,
        public readonly string $status,
        public readonly ?int $httpStatus = null,
        public readonly ?string $reason = null,
        public readonly ?Throwable $exception = null,
    ) {}

    public static function sent(Subscription $subscription, ?int $httpStatus = 201): static
    {
        return new static($subscription, self::SENT, $httpStatus);
    }

    public function successful(): bool
    {
        return $this->status === self::SENT;
    }

    public function failed(): bool
    {
        return ! $this->successful();
    }

    /**
     * The push service answered 404 or 410: the browser dropped this subscription.
     */
    public function isExpired(): bool
    {
        return $this->status === self::EXPIRED;
    }

    public function endpoint(): string
    {
        return $this->subscription->endpoint;
    }
}
