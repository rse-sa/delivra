<?php

namespace RSE\Delivra\Tests\Unit\WhatsApp;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;
use RSE\Delivra\WhatsApp\Messages\TemplateMessage;
use RSE\Delivra\WhatsApp\Templates\Button;
use RSE\Delivra\WhatsApp\Templates\Parameter;
use RSE\Delivra\WhatsApp\Templates\Template;

class TemplateMessageTest extends TestCase
{
    public function test_builds_named_body_parameters(): void
    {
        $payload = TemplateMessage::make('order_ready', 'ar')
            ->body(['name' => 'Acme', 'id' => 15])
            ->toPayload();

        $this->assertSame('template', $payload['type']);
        $this->assertSame('order_ready', $payload['template']['name']);
        $this->assertSame(['code' => 'ar'], $payload['template']['language']);
        $this->assertSame([
            'type'       => 'body',
            'parameters' => [
                ['type' => 'text', 'text' => 'Acme', 'parameter_name' => 'name'],
                ['type' => 'text', 'text' => '15', 'parameter_name' => 'id'],
            ],
        ], $payload['template']['components'][0]);
    }

    public function test_builds_positional_body_parameters(): void
    {
        $components = TemplateMessage::make('hello', 'en')->body(['Ali', 'Sara'])->toPayload()['template']['components'];

        $this->assertSame([
            ['type' => 'text', 'text' => 'Ali'],
            ['type' => 'text', 'text' => 'Sara'],
        ], $components[0]['parameters']);
    }

    public function test_omits_components_without_parameters(): void
    {
        $template = TemplateMessage::make('hello_world', 'en_US')->toPayload()['template'];

        $this->assertArrayNotHasKey('components', $template);
    }

    public function test_builds_header_and_buttons_in_index_order(): void
    {
        $components = TemplateMessage::make('promo', 'en')
            ->header(Parameter::image('https://example.com/a.jpg'))
            ->button(2, Button::copyCode('SAVE10'))
            ->urlButton('abc-123')
            ->toPayload()['template']['components'];

        $this->assertSame(['type' => 'header', 'parameters' => [['type' => 'image', 'image' => ['link' => 'https://example.com/a.jpg']]]], $components[0]);
        $this->assertSame(['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => 'abc-123']]], $components[1]);
        $this->assertSame(['type' => 'button', 'sub_type' => 'copy_code', 'index' => '2', 'parameters' => [['type' => 'coupon_code', 'coupon_code' => 'SAVE10']]], $components[2]);
    }

    public function test_typed_parameters(): void
    {
        $this->assertSame(
            ['type' => 'currency', 'currency' => ['fallback_value' => '1,250.50 SAR', 'code' => 'SAR', 'amount_1000' => 1250500]],
            Parameter::currency(1250.5, 'SAR')->toArray()
        );

        $this->assertSame(
            ['type' => 'date_time', 'date_time' => ['fallback_value' => '2026-01-02 10:30']],
            Parameter::from(CarbonImmutable::parse('2026-01-02 10:30'))->toArray()
        );

        $this->assertSame(
            ['type' => 'document', 'document' => ['id' => '998877', 'filename' => 'invoice.pdf']],
            Parameter::document('998877', 'invoice.pdf')->toArray()
        );
    }

    public function test_adds_context_and_tracking(): void
    {
        $payload = TemplateMessage::make('hello', 'en')->replyTo('wamid.1')->tracking('order:5')->toPayload();

        $this->assertSame(['message_id' => 'wamid.1'], $payload['context']);
        $this->assertSame('order:5', $payload['biz_opaque_callback_data']);
    }

    public function test_class_template_builds_message(): void
    {
        $message = (new OrderShipped('Acme', 'uuid-1'))->toMessage();

        $this->assertSame('order_shipped', $message->getName());
        $this->assertSame('ar', $message->getLanguage());
        $this->assertSame('sales', $message->getAccount());
        $this->assertTrue($message->is(OrderShipped::class));
        $this->assertTrue($message->is('order_shipped'));
        $this->assertSame('uuid-1', $message->toPayload()['template']['components'][1]['parameters'][0]['text']);
    }
}

class OrderShipped extends Template
{
    protected ?string $language = 'ar';

    protected ?string $account = 'sales';

    public function __construct(public string $company, public string $uuid) {}

    public function body(): array
    {
        return ['name' => $this->company];
    }

    public function buttons(): array
    {
        return [Button::url($this->uuid)];
    }
}
