<?php

namespace RSE\Delivra\Tests\Unit\Events;

use RSE\Delivra\Events\DelivraMessageSending;
use RSE\Delivra\Telegram\Messages\TextMessage;
use RSE\Delivra\Tests\TestCase;

class DelivraMessageSendingTest extends TestCase
{
    public function test_creates_event_with_all_properties(): void
    {
        $message = TextMessage::make()->message('Test message');
        $event = new DelivraMessageSending(
            'sms',
            '9665000000',
            'Test body',
            ['key' => 'value'],
            'unifonic',
            null,
            $notifiable = new class {},
            $notification = new class {},
            $message,
        );

        $this->assertEquals('sms', $event->channel);
        $this->assertEquals('9665000000', $event->recipient);
        $this->assertEquals('Test body', $event->body);
        $this->assertEquals(['key' => 'value'], $event->payload);
        $this->assertEquals('unifonic', $event->driver);
        $this->assertNull($event->token);
        $this->assertSame($notifiable, $event->notifiable);
        $this->assertSame($notification, $event->notification);
        $this->assertSame($message, $event->message);
    }

    public function test_creates_minimal_event(): void
    {
        $event = new DelivraMessageSending(
            'telegram',
            '123456',
            'Test body',
            [],
        );

        $this->assertEquals('telegram', $event->channel);
        $this->assertEquals('123456', $event->recipient);
        $this->assertEquals('Test body', $event->body);
        $this->assertEmpty($event->payload);
        $this->assertNull($event->driver);
        $this->assertNull($event->token);
        $this->assertNull($event->notifiable);
        $this->assertNull($event->notification);
        $this->assertNull($event->message);
    }

    public function test_creates_event_with_token_for_telegram(): void
    {
        $event = new DelivraMessageSending(
            'telegram',
            '123456',
            'Test body',
            ['chat_id' => '123456'],
            null,
            'bot-token-123',
        );

        $this->assertEquals('telegram', $event->channel);
        $this->assertEquals('bot-token-123', $event->token);
        $this->assertNull($event->driver);
    }

    public function test_creates_event_with_driver_for_sms(): void
    {
        $event = new DelivraMessageSending(
            'sms',
            '9665000000',
            'Test body',
            ['recipient' => '9665000000'],
            'msegat',
            null,
        );

        $this->assertEquals('sms', $event->channel);
        $this->assertEquals('msegat', $event->driver);
        $this->assertNull($event->token);
    }
}
