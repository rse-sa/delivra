<?php

namespace RSE\Delivra\Fcm;

use Throwable;

/**
 * Outcome for one target of a send or topic call.
 */
class FcmResult
{
    public const SENT = 'sent';

    public const INVALID_TOKEN = 'invalid_token';

    public const WRONG_PROJECT = 'wrong_project';

    public const FAILED = 'failed';

    public function __construct(
        public readonly Target $target,
        public readonly string $status,
        public readonly ?string $messageId = null,
        public readonly ?string $error = null,
        public readonly ?string $errorMessage = null,
        public readonly ?int $httpStatus = null,
        public readonly bool $retryable = false,
        public readonly ?Throwable $exception = null,
    ) {}

    public static function sent(Target $target, ?string $messageId = null, ?int $httpStatus = 200): static
    {
        return new static($target, self::SENT, $messageId, httpStatus: $httpStatus);
    }

    public function successful(): bool
    {
        return $this->status === self::SENT;
    }

    public function failed(): bool
    {
        return ! $this->successful();
    }

    public function isInvalidToken(): bool
    {
        return $this->status === self::INVALID_TOKEN;
    }

    public function token(): ?string
    {
        return $this->target->isToken() ? $this->target->value : null;
    }
}
