<?php

namespace RSE\Delivra\WhatsApp\Webhook;

use Carbon\CarbonImmutable;
use RSE\Delivra\WhatsApp\Enums\MessageStatus;

/**
 * Delivery status for a message the business sent.
 */
class StatusUpdate
{
    public function __construct(
        public readonly string $messageId,
        public readonly ?MessageStatus $status,
        public readonly string $recipient,
        public readonly CarbonImmutable $timestamp,
        public readonly ?string $account,
        public readonly ?string $phoneNumberId,
        public readonly ?string $tracking = null,
        public readonly array $errors = [],
        public readonly array $raw = [],
    ) {}

    public static function fromPayload(array $status, array $metadata, ?string $account): static
    {
        return new static(
            messageId: $status['id'],
            status: MessageStatus::tryFrom($status['status'] ?? ''),
            recipient: $status['recipient_id'] ?? '',
            timestamp: isset($status['timestamp']) ? CarbonImmutable::createFromTimestamp((int) $status['timestamp']) : CarbonImmutable::now(),
            account: $account,
            phoneNumberId: $metadata['phone_number_id'] ?? null,
            tracking: $status['biz_opaque_callback_data'] ?? null,
            errors: $status['errors'] ?? [],
            raw: $status,
        );
    }

    public function failed(): bool
    {
        return $this->status === MessageStatus::Failed;
    }

    public function errorCode(): ?int
    {
        return isset($this->errors[0]['code']) ? (int) $this->errors[0]['code'] : null;
    }

    public function errorMessage(): ?string
    {
        return $this->errors[0]['error_data']['details'] ?? $this->errors[0]['message'] ?? $this->errors[0]['title'] ?? null;
    }
}
