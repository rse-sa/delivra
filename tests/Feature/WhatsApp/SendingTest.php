<?php

namespace RSE\Delivra\Tests\Feature\WhatsApp;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use RSE\Delivra\Events\DelivraMessageFailed;
use RSE\Delivra\Events\DelivraMessageSending;
use RSE\Delivra\Events\DelivraMessageSent;
use RSE\Delivra\Exceptions\WhatsApp\RecipientBlockedException;
use RSE\Delivra\Exceptions\WhatsApp\ReEngagementRequiredException;
use RSE\Delivra\Exceptions\WhatsApp\TemplateValidationException;
use RSE\Delivra\Exceptions\WhatsApp\WhatsAppBatchException;
use RSE\Delivra\Exceptions\WhatsApp\WhatsAppException;
use RSE\Delivra\Tests\TestCase;
use RSE\Delivra\WhatsApp\Messages\TemplateMessage;
use RSE\Delivra\WhatsApp\Templates\Button;
use RSE\Delivra\WhatsApp\WhatsAppBatchResponse;
use RSE\Delivra\WhatsApp\WhatsAppResponse;

class SendingTest extends TestCase
{
    protected function fakeMeta(): void
    {
        Http::fake([
            'graph.facebook.com/*/messages' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts'          => [['input' => '966501234567', 'wa_id' => '966501234567']],
                'messages'          => [['id' => 'wamid.abc', 'message_status' => 'accepted']],
            ]),
        ]);
    }

    public function test_sends_template_to_default_account_and_number(): void
    {
        $this->fakeMeta();

        $response = whatsapp()->to('0501234567')->send(
            TemplateMessage::make('order_ready', 'ar')->body(['name' => 'Acme'])->urlButton('uuid-1')
        );

        $this->assertInstanceOf(WhatsAppResponse::class, $response);
        $this->assertSame('wamid.abc', $response->messageId);
        $this->assertSame('default', $response->account);
        $this->assertSame('accepted', $response->messageStatus);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://graph.facebook.com/v24.0/1001/messages'
                && $request->hasHeader('Authorization', 'Bearer default-token')
                && $request['to'] === '966501234567'
                && $request['messaging_product'] === 'whatsapp'
                && $request['template']['name'] === 'order_ready'
                && $request['template']['components'][1]['sub_type'] === 'url';
        });
    }

    public function test_sends_from_another_account_and_number(): void
    {
        $this->fakeMeta();

        whatsapp()->account('sales')->to('966500000001')->text('Hi');
        whatsapp()->from('support')->to('966500000002')->text('Hi');
        whatsapp()->to('966500000003')->send(TemplateMessage::make('hello', 'en')->account('sales'));

        $urls = Http::recorded()->map(fn ($pair) => [$pair[0]->url(), $pair[0]->header('Authorization')[0]])->all();

        $this->assertSame([
            ['https://graph.facebook.com/v24.0/2001/messages', 'Bearer sales-token'],
            ['https://graph.facebook.com/v24.0/1002/messages', 'Bearer default-token'],
            ['https://graph.facebook.com/v24.0/2001/messages', 'Bearer sales-token'],
        ], $urls);
    }

    public function test_unknown_account_throws(): void
    {
        $this->expectException(WhatsAppException::class);

        whatsapp()->account('missing');
    }

    public function test_template_alias_from_config(): void
    {
        $this->fakeMeta();
        config()->set('delivra.whatsapp.templates.order_ready', ['name' => 'dorfp_v2', 'language' => 'ar', 'account' => 'sales']);
        $this->app->forgetInstance('delivra-whatsapp');

        whatsapp()->to('966500000001')->template('order_ready', ['name' => 'Acme']);

        Http::assertSent(fn (Request $request) => $request['template']['name'] === 'dorfp_v2'
            && $request['template']['language']['code'] === 'ar'
            && str_contains($request->url(), '/2001/'));
    }

    public function test_default_language_is_applied(): void
    {
        $this->fakeMeta();

        whatsapp()->to('966500000001')->template('hello_world');

        Http::assertSent(fn (Request $request) => $request['template']['language']['code'] === 'en_US');
    }

    public function test_maps_meta_errors_to_exceptions_and_fires_failed_event(): void
    {
        Event::fake([DelivraMessageFailed::class]);
        Http::fake(['*' => Http::response(['error' => [
            'message'    => 'Re-engagement message',
            'type'       => 'OAuthException',
            'code'       => 131047,
            'error_data' => ['details' => 'More than 24 hours have passed'],
            'fbtrace_id' => 'trace-1',
        ]], 400)]);

        try {
            whatsapp()->to('966500000001')->text('Hi');
            $this->fail('Expected exception');
        } catch (ReEngagementRequiredException $e) {
            $this->assertSame(131047, $e->errorCode);
            $this->assertSame('trace-1', $e->traceId);
            $this->assertStringContainsString('More than 24 hours', $e->getMessage());
        }

        Event::assertDispatched(DelivraMessageFailed::class, fn ($event) => $event->channel === 'whatsapp');
    }

    public function test_fires_sending_and_sent_events(): void
    {
        $this->fakeMeta();
        Event::fake([DelivraMessageSending::class, DelivraMessageSent::class]);

        whatsapp()->to('966500000001')->text('Hello');

        Event::assertDispatched(DelivraMessageSending::class, fn ($event) => $event->channel === 'whatsapp' && $event->driver === 'default' && $event->body === 'Hello');
        Event::assertDispatched(DelivraMessageSent::class, fn ($event) => $event->messageId === 'wamid.abc');
    }

    public function test_opt_out_hook_blocks_recipient(): void
    {
        Http::fake();
        whatsapp()->canSendUsing(fn (string $recipient) => $recipient !== '966500000009');

        $this->expectException(RecipientBlockedException::class);

        try {
            whatsapp()->to('0500000009')->text('Hi');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_batch_collects_failures(): void
    {
        $this->fakeMeta();
        whatsapp()->canSendUsing(fn (string $recipient) => $recipient !== '966500000002');

        try {
            whatsapp()->to(['966500000001', '966500000002'])->text('Hi');
            $this->fail('Expected batch exception');
        } catch (WhatsAppBatchException $e) {
            $batch = $e->getBatchResponse();

            $this->assertInstanceOf(WhatsAppBatchResponse::class, $batch);
            $this->assertSame(1, $batch->successfulCount());
            $this->assertArrayHasKey('966500000002', $batch->failed());
        }
    }

    public function test_validates_template_against_meta_definition(): void
    {
        config()->set('delivra.whatsapp.validate_templates', true);
        $this->app->forgetInstance('delivra-whatsapp');

        Http::fake([
            'graph.facebook.com/v24.0/waba-default/message_templates*' => Http::response(['data' => [[
                'name'       => 'order_ready',
                'language'   => 'ar',
                'status'     => 'APPROVED',
                'components' => [
                    ['type' => 'BODY', 'text' => 'Hi {{name}}, order {{id}}'],
                    ['type' => 'BUTTONS', 'buttons' => [['type' => 'URL', 'url' => 'https://x.test/{{1}}']]],
                ],
            ]]]),
            '*' => Http::response(['messages' => [['id' => 'wamid.ok']]]),
        ]);

        try {
            whatsapp()->to('966500000001')->send(TemplateMessage::make('order_ready', 'ar')->body(['name' => 'A', 'total' => 5]));
            $this->fail('Expected validation exception');
        } catch (TemplateValidationException $e) {
            $this->assertCount(3, $e->errors);
        }

        $response = whatsapp()->to('966500000001')->send(
            TemplateMessage::make('order_ready', 'ar')->body(['name' => 'A', 'id' => 5])->buttons([Button::url('x')])
        );

        $this->assertSame('wamid.ok', $response->messageId);
    }

    public function test_registry_follows_pagination(): void
    {
        Http::fake([
            'graph.facebook.com/v24.0/waba-default/message_templates*' => Http::response([
                'data'   => [['name' => 'a', 'language' => 'en', 'status' => 'APPROVED']],
                'paging' => ['next' => 'https://graph.facebook.com/v24.0/next-page'],
            ]),
            'graph.facebook.com/v24.0/next-page' => Http::response([
                'data' => [['name' => 'b', 'language' => 'en', 'status' => 'PAUSED']],
            ]),
        ]);

        $templates = whatsapp()->templates()->all();

        $this->assertSame(['a', 'b'], $templates->pluck('name')->all());
        $this->assertSame('PAUSED', whatsapp()->templates()->find('b')->status);
    }
}
