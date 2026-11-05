<?php

namespace RSE\Delivra\WhatsApp\Webhook;

use Carbon\CarbonImmutable;

/**
 * A message a user sent to one of the business numbers.
 */
class InboundMessage
{
    public function __construct(
        public readonly string $id,
        public readonly string $from,
        public readonly string $type,
        public readonly CarbonImmutable $timestamp,
        public readonly ?string $account,
        public readonly ?string $phoneNumberId,
        public readonly ?string $displayPhoneNumber = null,
        public readonly ?string $contactName = null,
        public readonly ?string $replyToId = null,
        public readonly array $raw = [],
    ) {}

    public static function fromPayload(array $message, array $metadata, array $contacts, ?string $account): static
    {
        $from = $message['from'];

        return new static(
            id: $message['id'],
            from: $from,
            type: $message['type'] ?? 'unknown',
            timestamp: isset($message['timestamp']) ? CarbonImmutable::createFromTimestamp((int) $message['timestamp']) : CarbonImmutable::now(),
            account: $account,
            phoneNumberId: $metadata['phone_number_id'] ?? null,
            displayPhoneNumber: $metadata['display_phone_number'] ?? null,
            contactName: $contacts[$from]['profile']['name'] ?? null,
            replyToId: $message['context']['id'] ?? null,
            raw: $message,
        );
    }

    /**
     * Text body, media caption, or tapped button title.
     */
    public function text(): ?string
    {
        return match ($this->type) {
            'text'                       => $this->raw['text']['body'] ?? null,
            'image', 'video', 'document' => $this->raw[$this->type]['caption'] ?? null,
            'button'                     => $this->raw['button']['text'] ?? null,
            'interactive'                => $this->interactiveReply()['title'] ?? null,
            'reaction'                   => $this->raw['reaction']['emoji'] ?? null,
            default                      => null,
        };
    }

    /**
     * Id of a tapped reply button or list row, or the payload of a template quick reply.
     */
    public function buttonId(): ?string
    {
        return match ($this->type) {
            'button'      => $this->raw['button']['payload'] ?? null,
            'interactive' => $this->interactiveReply()['id'] ?? null,
            default       => null,
        };
    }

    public function isButtonReply(): bool
    {
        return $this->buttonId() !== null;
    }

    public function hasMedia(): bool
    {
        return $this->mediaId() !== null;
    }

    public function mediaId(): ?string
    {
        return in_array($this->type, ['image', 'video', 'audio', 'document', 'sticker'], true)
            ? ($this->raw[$this->type]['id'] ?? null)
            : null;
    }

    public function mimeType(): ?string
    {
        return $this->hasMedia() ? ($this->raw[$this->type]['mime_type'] ?? null) : null;
    }

    /**
     * @return array{latitude: float, longitude: float, name?: string, address?: string}|null
     */
    public function location(): ?array
    {
        return $this->raw['location'] ?? null;
    }

    protected function interactiveReply(): array
    {
        return $this->raw['interactive']['button_reply'] ?? $this->raw['interactive']['list_reply'] ?? [];
    }
}
