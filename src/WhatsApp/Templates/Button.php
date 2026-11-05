<?php

namespace RSE\Delivra\WhatsApp\Templates;

class Button
{
    public function __construct(public readonly string $subType, public readonly array $parameters = []) {}

    /**
     * Value for the {{1}} placeholder in a dynamic URL button.
     */
    public static function url(string $suffix): static
    {
        return new static('url', [['type' => 'text', 'text' => $suffix]]);
    }

    /**
     * Payload you get back in the webhook when the user taps the button.
     */
    public static function quickReply(string $payload): static
    {
        return new static('quick_reply', [['type' => 'payload', 'payload' => $payload]]);
    }

    public static function copyCode(string $code): static
    {
        return new static('copy_code', [['type' => 'coupon_code', 'coupon_code' => $code]]);
    }

    public static function flow(?string $token = null, array $data = []): static
    {
        return new static('flow', [['type' => 'action', 'action' => array_filter([
            'flow_token'       => $token,
            'flow_action_data' => $data ?: null,
        ])]]);
    }

    public function toArray(int $index): array
    {
        return [
            'type'       => 'button',
            'sub_type'   => $this->subType,
            'index'      => (string) $index,
            'parameters' => $this->parameters,
        ];
    }
}
