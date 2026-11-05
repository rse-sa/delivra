<?php

namespace RSE\Delivra\WhatsApp;

use Carbon\CarbonImmutable;

class WhatsAppResponse
{
    public readonly CarbonImmutable $timestamp;

    public function __construct(
        public readonly string $account,
        public readonly string $phoneNumberId,
        public readonly string $recipient,
        public readonly ?string $messageId,
        public readonly ?string $waId = null,
        public readonly ?string $messageStatus = null,
        public readonly array $raw = [],
    ) {
        $this->timestamp = CarbonImmutable::now();
    }

    public static function fromApi(string $account, string $phoneNumberId, string $recipient, array $raw): static
    {
        return new static(
            account: $account,
            phoneNumberId: $phoneNumberId,
            recipient: $recipient,
            messageId: $raw['messages'][0]['id'] ?? null,
            waId: $raw['contacts'][0]['wa_id'] ?? null,
            messageStatus: $raw['messages'][0]['message_status'] ?? null,
            raw: $raw,
        );
    }

    public function successful(): bool
    {
        return $this->messageId !== null;
    }

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    public function toArray(): array
    {
        return [
            'account'         => $this->account,
            'phone_number_id' => $this->phoneNumberId,
            'recipient'       => $this->recipient,
            'message_id'      => $this->messageId,
            'wa_id'           => $this->waId,
            'message_status'  => $this->messageStatus,
            'timestamp'       => $this->timestamp->toIso8601String(),
            'raw'             => $this->raw,
        ];
    }
}
