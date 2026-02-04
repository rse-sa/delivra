<?php

namespace RSE\Delivra\Tests\Unit\Events;

use RSE\Delivra\Events\DelivraMessageSent;
use RSE\Delivra\Sms\SmsResponse;
use RSE\Delivra\Telegram\Messages\TextMessage;
use RSE\Delivra\Telegram\TelegramResponse;
use RSE\Delivra\Tests\TestCase;

class DelivraMessageSentTest extends TestCase
{
    public function test_creates_event_with_all_properties(): void
    {
        $response = new SmsResponse('unifonic', '9665000000', 'Test message');
        $message = TextMessage::make()->message('Test message');
        $event = new DelivraMessageSent(
            'sms',
            '9665000000',
            'Test body',
            'msg-123',
            $response,
            $notifiable = new class {},
            $notification = new class {},
            $message,
        );

        $this->assertEquals('sms', $event->channel);
        $this->assertEquals('9665000000', $event->recipient);
        $this->assertEquals('Test body', $event->body);
        $this->assertEquals('msg-123', $event->messageId);
        $this->assertSame($response, $event->response);
        $this->assertSame($notifiable, $event->notifiable);
        $this->assertSame($notification, $event->notification);
        $this->assertSame($message, $event->message);
    }

    public function test_creates_minimal_event(): void
    {
        $response = new SmsResponse('unifonic', '9665000000', 'Test message');
        $event = new DelivraMessageSent(
            'telegram',
            '123456',
            'Test body',
            'msg-456',
            $response,
        );

        $this->assertEquals('telegram', $event->channel);
        $this->assertEquals('123456', $event->recipient);
        $this->assertEquals('Test body', $event->body);
        $this->assertEquals('msg-456', $event->messageId);
        $this->assertSame($response, $event->response);
        $this->assertNull($event->notifiable);
        $this->assertNull($event->notification);
        $this->assertNull($event->message);
    }

    public function test_creates_event_with_null_message_id(): void
    {
        $response = new SmsResponse('null', '9665000000', 'Test message');
        $event = new DelivraMessageSent(
            'sms',
            '9665000000',
            'Test body',
            null,
            $response,
        );

        $this->assertNull($event->messageId);
    }

    public function test_creates_event_with_telegram_response(): void
    {
        $telegramResponse = new TelegramResponse('123456', [
            'ok' => true,
            'result' => ['message_id' => 789],
        ]);

        $event = new DelivraMessageSent(
            'telegram',
            '123456',
            'Photo caption',
            '789',
            $telegramResponse,
        );

        $this->assertEquals('telegram', $event->channel);
        $this->assertEquals('Photo caption', $event->body);
        $this->assertSame($telegramResponse, $event->response);
    }
}
