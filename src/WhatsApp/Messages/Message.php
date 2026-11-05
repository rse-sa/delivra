<?php

namespace RSE\Delivra\WhatsApp\Messages;

abstract class Message
{
    /** @var list<string> */
    protected array $recipients = [];

    protected ?string $account = null;

    protected ?string $from = null;

    protected ?string $replyTo = null;

    protected ?string $tracking = null;

    /**
     * The Cloud API message type, e.g. "text" or "template".
     */
    abstract public function type(): string;

    /**
     * The type specific object sent under the type key.
     */
    abstract protected function content(): array;

    /**
     * Short human readable text, used by events and logs.
     */
    abstract public function description(): string;

    public function to(string|array $recipients): static
    {
        $this->recipients = array_values((array) $recipients);

        return $this;
    }

    public function account(string $account): static
    {
        $this->account = $account;

        return $this;
    }

    /**
     * Send from a number name (as in config) or a raw phone_number_id.
     */
    public function from(string $number): static
    {
        $this->from = $number;

        return $this;
    }

    public function replyTo(string $messageId): static
    {
        $this->replyTo = $messageId;

        return $this;
    }

    /**
     * Opaque data Meta returns back in status webhooks (biz_opaque_callback_data).
     */
    public function tracking(string $data): static
    {
        $this->tracking = $data;

        return $this;
    }

    /** @return list<string> */
    public function getRecipients(): array
    {
        return $this->recipients;
    }

    public function getAccount(): ?string
    {
        return $this->account;
    }

    public function getFrom(): ?string
    {
        return $this->from;
    }

    public function getReplyTo(): ?string
    {
        return $this->replyTo;
    }

    public function getTracking(): ?string
    {
        return $this->tracking;
    }

    public function toPayload(): array
    {
        $payload = [
            'type'        => $this->type(),
            $this->type() => $this->content(),
        ];

        if ($this->replyTo) {
            $payload['context'] = ['message_id' => $this->replyTo];
        }

        if ($this->tracking) {
            $payload['biz_opaque_callback_data'] = $this->tracking;
        }

        return $payload;
    }
}
