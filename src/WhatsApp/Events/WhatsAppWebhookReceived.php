<?php

namespace RSE\Delivra\WhatsApp\Events;

/**
 * Fired for every webhook change, before the typed events. Use it for fields delivra doesn't map.
 */
class WhatsAppWebhookReceived
{
    public function __construct(
        public readonly ?string $field,
        public readonly array $value,
        public readonly ?string $account,
        public readonly ?string $businessAccountId,
    ) {}
}
