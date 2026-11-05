<?php

namespace RSE\Delivra\Tests\Feature\WhatsApp;

use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use RSE\Delivra\Tests\TestCase;
use RSE\Delivra\WhatsApp\Enums\MessageStatus;
use RSE\Delivra\WhatsApp\Events\WhatsAppMessageReceived;
use RSE\Delivra\WhatsApp\Events\WhatsAppStatusUpdated;
use RSE\Delivra\WhatsApp\Events\WhatsAppTemplateStatusUpdated;
use RSE\Delivra\WhatsApp\Events\WhatsAppWebhookReceived;

class WebhookTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('delivra.whatsapp.webhook.enabled', true);
        $app['config']->set('delivra.whatsapp.webhook.verify_token', 'verify-me');
    }

    protected function postWebhook(array $payload, string $secret = 'secret-sales'): TestResponse
    {
        $body = json_encode($payload);

        return $this->call('POST', '/delivra/whatsapp/webhook', [], [], [], [
            'CONTENT_TYPE'             => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $body, $secret),
        ], $body);
    }

    protected function messagesPayload(array $value): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry'  => [[
                'id'      => 'waba-sales',
                'changes' => [[
                    'field' => 'messages',
                    'value' => ['metadata' => ['phone_number_id' => '2001', 'display_phone_number' => '966111']] + $value,
                ]],
            ]],
        ];
    }

    public function test_verify_handshake(): void
    {
        $this->get('/delivra/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=12345')
            ->assertOk()
            ->assertSeeText('12345');

        $this->get('/delivra/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=1')
            ->assertForbidden();
    }

    public function test_rejects_invalid_signature(): void
    {
        Event::fake();

        $this->postWebhook($this->messagesPayload([]), 'wrong-secret')->assertForbidden();

        Event::assertNotDispatched(WhatsAppWebhookReceived::class);
    }

    public function test_dispatches_inbound_message_once(): void
    {
        Event::fake([WhatsAppMessageReceived::class, WhatsAppWebhookReceived::class]);

        $payload = $this->messagesPayload([
            'contacts' => [['wa_id' => '966500000001', 'profile' => ['name' => 'Ali']]],
            'messages' => [[
                'id'          => 'wamid.in1',
                'from'        => '966500000001',
                'timestamp'   => '1767225600',
                'type'        => 'interactive',
                'interactive' => ['type' => 'button_reply', 'button_reply' => ['id' => 'wa_unsubscribe', 'title' => 'Stop']],
            ]],
        ]);

        $this->postWebhook($payload)->assertOk();
        $this->postWebhook($payload)->assertOk();

        Event::assertDispatchedTimes(WhatsAppMessageReceived::class, 1);
        Event::assertDispatched(WhatsAppMessageReceived::class, function (WhatsAppMessageReceived $event) {
            return $event->message->account === 'sales'
                && $event->message->contactName === 'Ali'
                && $event->message->buttonId() === 'wa_unsubscribe'
                && $event->message->text() === 'Stop'
                && $event->message->isButtonReply();
        });
        Event::assertDispatched(WhatsAppWebhookReceived::class, fn ($event) => $event->field === 'messages' && $event->businessAccountId === 'waba-sales');
    }

    public function test_dispatches_status_updates_with_errors(): void
    {
        Event::fake([WhatsAppStatusUpdated::class]);

        $this->postWebhook($this->messagesPayload([
            'statuses' => [[
                'id'                       => 'wamid.out1',
                'status'                   => 'failed',
                'recipient_id'             => '966500000001',
                'timestamp'                => '1767225600',
                'biz_opaque_callback_data' => 'order:5',
                'errors'                   => [['code' => 131026, 'title' => 'Message undeliverable']],
            ]],
        ]))->assertOk();

        Event::assertDispatched(WhatsAppStatusUpdated::class, function (WhatsAppStatusUpdated $event) {
            return $event->status->status === MessageStatus::Failed
                && $event->status->failed()
                && $event->status->errorCode() === 131026
                && $event->status->tracking === 'order:5'
                && $event->status->account === 'sales';
        });
    }

    public function test_dispatches_template_status_updates(): void
    {
        Event::fake([WhatsAppTemplateStatusUpdated::class]);

        $this->postWebhook([
            'object' => 'whatsapp_business_account',
            'entry'  => [[
                'id'      => 'waba-default',
                'changes' => [[
                    'field' => 'message_template_status_update',
                    'value' => ['event' => 'REJECTED', 'message_template_id' => 77, 'message_template_name' => 'promo', 'message_template_language' => 'ar', 'reason' => 'INVALID_FORMAT'],
                ]],
            ]],
        ], 'secret-default')->assertOk();

        Event::assertDispatched(WhatsAppTemplateStatusUpdated::class, fn ($event) => $event->account === 'default'
            && $event->templateName === 'promo'
            && $event->status === 'REJECTED'
            && $event->templateId === '77');
    }
}
