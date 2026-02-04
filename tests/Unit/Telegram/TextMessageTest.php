<?php

namespace RSE\Delivra\Tests\Unit\Telegram;

use RSE\Delivra\Telegram\Messages\TextMessage;
use RSE\Delivra\Tests\TestCase;

class TextMessageTest extends TestCase
{
    public function test_sets_message(): void
    {
        $message = TextMessage::make()->message('Hello');

        $this->assertEquals('Hello', $message->toArray()['text']);
    }

    public function test_sets_receivers(): void
    {
        $message = TextMessage::make()->to('123456');

        $this->assertEquals(['123456'], $message->getReceivers());
    }

    public function test_sets_multiple_receivers(): void
    {
        $message = TextMessage::make()->to(['123456', '789012']);

        $this->assertEquals(['123456', '789012'], $message->getReceivers());
    }

    public function test_sets_token(): void
    {
        $message = TextMessage::make()->token('test-token');

        $this->assertEquals('test-token', $message->getToken());
    }

    public function test_sets_title(): void
    {
        $message = TextMessage::make()->title('Alert');

        $prepared = $message->toArray()['text'] ?? '';

        $this->assertStringContainsString('Alert', $prepared);
    }

    public function test_as_html(): void
    {
        $message = TextMessage::make()->asHtml();

        $this->assertEquals('html', $message->toArray()['parse_mode']);
    }

    public function test_as_markdown(): void
    {
        $message = TextMessage::make()->asMarkdown();

        $this->assertEquals('MarkdownV2', $message->toArray()['parse_mode']);
    }

    public function test_with_protected_content(): void
    {
        $message = TextMessage::make()->withProtectedContent();

        $this->assertTrue($message->toArray()['protect_content']);
    }

    public function test_silently(): void
    {
        $message = TextMessage::make()->silently();

        $this->assertTrue($message->toArray()['disable_notification']);
    }

    public function test_adds_url_button(): void
    {
        $message = TextMessage::make()->addUrlButton('Click', 'https://example.com');

        $this->assertArrayHasKey('reply_markup', $message->toArray());
    }

    public function test_preconverts_br_to_newline(): void
    {
        $message = TextMessage::make()->message('Line 1<br>Line 2');

        $prepared = $message->toArray()['text'] ?? '';

        $this->assertStringNotContainsString('<br>', $prepared);
        $this->assertStringContainsString("\n", $prepared);
    }
}
