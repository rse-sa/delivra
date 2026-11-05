<?php

namespace RSE\Delivra\Tests\Unit\WhatsApp;

use PHPUnit\Framework\TestCase;
use RSE\Delivra\Exceptions\WhatsApp\InvalidMessageException;
use RSE\Delivra\WhatsApp\Messages\ButtonsMessage;
use RSE\Delivra\WhatsApp\Messages\ListMessage;
use RSE\Delivra\WhatsApp\Messages\MediaMessage;
use RSE\Delivra\WhatsApp\Messages\ReactionMessage;
use RSE\Delivra\WhatsApp\Messages\TextMessage;
use RSE\Delivra\WhatsApp\Templates\TemplateDefinition;

class MessagesTest extends TestCase
{
    public function test_text_message(): void
    {
        $this->assertSame(
            ['type' => 'text', 'text' => ['body' => 'Hi', 'preview_url' => true]],
            TextMessage::make('Hi')->previewUrl()->toPayload()
        );
    }

    public function test_media_message_uses_link_or_id(): void
    {
        $this->assertSame(['link' => 'https://x.test/a.png', 'caption' => 'Look'], MediaMessage::image('https://x.test/a.png', 'Look')->toPayload()['image']);
        $this->assertSame(['id' => '123', 'filename' => 'a.pdf'], MediaMessage::document('123', 'a.pdf')->toPayload()['document']);
    }

    public function test_buttons_message(): void
    {
        $payload = ButtonsMessage::make('Choose')->button('yes', 'Yes')->button('no', 'No')->toPayload();

        $this->assertSame('button', $payload['interactive']['type']);
        $this->assertSame(['type' => 'reply', 'reply' => ['id' => 'no', 'title' => 'No']], $payload['interactive']['action']['buttons'][1]);
    }

    public function test_buttons_message_rejects_more_than_three(): void
    {
        $this->expectException(InvalidMessageException::class);

        ButtonsMessage::make('Choose')->buttons(['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'])->toPayload();
    }

    public function test_list_message(): void
    {
        $payload = ListMessage::make('Pick one', 'Menu')
            ->section('Sizes', ['s' => 'Small', 'l' => ['title' => 'Large', 'description' => 'Big one']])
            ->toPayload();

        $this->assertSame('Menu', $payload['interactive']['action']['button']);
        $this->assertSame(['id' => 'l', 'title' => 'Large', 'description' => 'Big one'], $payload['interactive']['action']['sections'][0]['rows'][1]);
    }

    public function test_reaction_message(): void
    {
        $this->assertSame(['message_id' => 'wamid.1', 'emoji' => '+'], ReactionMessage::make('wamid.1', '+')->toPayload()['reaction']);
    }

    public function test_template_definition_reads_variables_and_buttons(): void
    {
        $definition = TemplateDefinition::fromArray([
            'name'       => 'order_ready',
            'language'   => 'ar',
            'status'     => 'APPROVED',
            'components' => [
                ['type' => 'HEADER', 'format' => 'IMAGE'],
                ['type' => 'BODY', 'text' => 'Hi {{name}}, order {{id}} costs {{amount}} {{name}}'],
                ['type' => 'BUTTONS', 'buttons' => [
                    ['type' => 'QUICK_REPLY', 'text' => 'Stop'],
                    ['type' => 'URL', 'text' => 'Pay', 'url' => 'https://x.test/pay/{{1}}'],
                ]],
            ],
        ]);

        $this->assertSame(['name', 'id', 'amount'], $definition->bodyVariables());
        $this->assertTrue($definition->usesNamedParameters());
        $this->assertTrue($definition->headerNeedsParameter());
        $this->assertFalse($definition->buttons()[0]['needs_parameter']);
        $this->assertTrue($definition->buttons()[1]['needs_parameter']);
    }
}
