<?php

namespace RSE\Delivra\Tests\Unit\Telegram;

use RSE\Delivra\Telegram\TelegramResponse;
use RSE\Delivra\Tests\TestCase;

class TelegramResponseTest extends TestCase
{
    public function test_creates_successful_response(): void
    {
        $apiResponse = [
            'ok' => true,
            'result' => ['message_id' => 123],
        ];

        $response = new TelegramResponse('chat-123', $apiResponse);

        $this->assertTrue($response->successful());
        $this->assertEquals('chat-123', $response->getChatId());
        $this->assertEquals(123, $response->getMessageId());
    }

    public function test_creates_failed_response(): void
    {
        $apiResponse = [
            'ok' => false,
            'description' => 'Bad request',
        ];

        $response = new TelegramResponse('chat-123', $apiResponse);

        $this->assertTrue($response->failed());
        $this->assertEquals('Bad request', $response->getError());
    }

    public function test_to_array(): void
    {
        $apiResponse = [
            'ok' => true,
            'result' => ['message_id' => 123],
        ];

        $response = new TelegramResponse('chat-123', $apiResponse);

        $array = $response->toArray();

        $this->assertTrue($array['success']);
        $this->assertEquals('chat-123', $array['chat_id']);
        $this->assertEquals(123, $array['message_id']);
        $this->assertArrayHasKey('timestamp', $array);
    }
}
