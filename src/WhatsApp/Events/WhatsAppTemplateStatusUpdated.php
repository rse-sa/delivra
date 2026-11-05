<?php

namespace RSE\Delivra\WhatsApp\Events;

/**
 * Meta approved, rejected, paused or disabled a template.
 */
class WhatsAppTemplateStatusUpdated
{
    public function __construct(
        public readonly ?string $account,
        public readonly ?string $templateId,
        public readonly ?string $templateName,
        public readonly ?string $language,
        public readonly ?string $status,
        public readonly ?string $reason,
        public readonly array $raw = [],
    ) {}

    public static function fromPayload(array $value, ?string $account): static
    {
        return new static(
            account: $account,
            templateId: isset($value['message_template_id']) ? (string) $value['message_template_id'] : null,
            templateName: $value['message_template_name'] ?? null,
            language: $value['message_template_language'] ?? null,
            status: $value['event'] ?? null,
            reason: $value['reason'] ?? null,
            raw: $value,
        );
    }
}
