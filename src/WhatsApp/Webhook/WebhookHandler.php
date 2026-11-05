<?php

namespace RSE\Delivra\WhatsApp\Webhook;

use Illuminate\Support\Facades\Cache;
use RSE\Delivra\WhatsApp\Events\WhatsAppMessageReceived;
use RSE\Delivra\WhatsApp\Events\WhatsAppStatusUpdated;
use RSE\Delivra\WhatsApp\Events\WhatsAppTemplateStatusUpdated;
use RSE\Delivra\WhatsApp\Events\WhatsAppWebhookReceived;
use RSE\Delivra\WhatsApp\WhatsAppManager;

/**
 * Turns a verified webhook payload into delivra events.
 */
class WebhookHandler
{
    public function __construct(protected WhatsAppManager $manager, protected int $deduplicateFor = 86400) {}

    public function handle(array $payload): void
    {
        if (($payload['object'] ?? null) !== 'whatsapp_business_account') {
            return;
        }

        foreach ($payload['entry'] ?? [] as $entry) {
            $businessAccountId = isset($entry['id']) ? (string) $entry['id'] : null;

            foreach ($entry['changes'] ?? [] as $change) {
                $this->handleChange($change['field'] ?? null, $change['value'] ?? [], $businessAccountId);
            }
        }
    }

    protected function handleChange(?string $field, array $value, ?string $businessAccountId): void
    {
        $account = $this->accountName($value['metadata']['phone_number_id'] ?? null, $businessAccountId);

        event(new WhatsAppWebhookReceived($field, $value, $account, $businessAccountId));

        match ($field) {
            'messages'                       => $this->handleMessages($value, $account),
            'message_template_status_update' => event(WhatsAppTemplateStatusUpdated::fromPayload($value, $account)),
            default                          => null,
        };
    }

    protected function handleMessages(array $value, ?string $account): void
    {
        $metadata = $value['metadata'] ?? [];

        foreach ($value['statuses'] ?? [] as $status) {
            event(new WhatsAppStatusUpdated(StatusUpdate::fromPayload($status, $metadata, $account)));
        }

        $contacts = collect($value['contacts'] ?? [])->keyBy('wa_id')->all();

        foreach ($value['messages'] ?? [] as $message) {
            if ($this->alreadyProcessed($message['id'] ?? null)) {
                continue;
            }

            event(new WhatsAppMessageReceived(InboundMessage::fromPayload($message, $metadata, $contacts, $account)));
        }
    }

    /**
     * Meta retries webhooks, so skip message ids we have seen.
     */
    protected function alreadyProcessed(?string $messageId): bool
    {
        if ($messageId === null || $this->deduplicateFor <= 0) {
            return false;
        }

        return ! Cache::add("delivra:whatsapp:inbound:{$messageId}", true, $this->deduplicateFor);
    }

    protected function accountName(?string $phoneNumberId, ?string $businessAccountId): ?string
    {
        return ($this->manager->findAccountByPhoneNumberId($phoneNumberId)
            ?? $this->manager->findAccountByBusinessId($businessAccountId))?->name;
    }
}
