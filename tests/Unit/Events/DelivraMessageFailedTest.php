<?php

namespace RSE\Delivra\Tests\Unit\Events;

use Exception;
use RSE\Delivra\Events\DelivraMessageFailed;
use RSE\Delivra\Telegram\Messages\TextMessage;
use RSE\Delivra\Tests\TestCase;

class DelivraMessageFailedTest extends TestCase
{
    public function test_creates_event_with_all_properties(): void
    {
        $exception = new Exception('Something went wrong');
        $message   = TextMessage::make()->message('Test message');
        $event     = new DelivraMessageFailed(
            'sms',
            '9665000000',
            'Test body',
            'Failed to send',
            $exception,
            $notifiable   = new class {},
            $notification = new class {},
            $message,
        );

        $this->assertEquals('sms', $event->channel);
        $this->assertEquals('9665000000', $event->recipient);
        $this->assertEquals('Test body', $event->body);
        $this->assertEquals('Failed to send', $event->error);
        $this->assertSame($exception, $event->exception);
        $this->assertSame($notifiable, $event->notifiable);
        $this->assertSame($notification, $event->notification);
        $this->assertSame($message, $event->message);
    }

    public function test_creates_minimal_event(): void
    {
        $event = new DelivraMessageFailed(
            'telegram',
            '123456',
            'Test body',
            'API error',
        );

        $this->assertEquals('telegram', $event->channel);
        $this->assertEquals('123456', $event->recipient);
        $this->assertEquals('Test body', $event->body);
        $this->assertEquals('API error', $event->error);
        $this->assertNull($event->exception);
        $this->assertNull($event->notifiable);
        $this->assertNull($event->notification);
        $this->assertNull($event->message);
    }

    public function test_creates_event_with_exception(): void
    {
        $exception = new Exception('Connection timeout');

        $event = new DelivraMessageFailed(
            'sms',
            '9665000000',
            'Test body',
            'Connection timeout',
            $exception,
        );

        $this->assertEquals('Connection timeout', $event->error);
        $this->assertSame($exception, $event->exception);
        $this->assertInstanceOf(Exception::class, $event->exception);
    }

    public function test_creates_event_with_message_instance(): void
    {
        $message = TextMessage::make()->message('Test');
        $event   = new DelivraMessageFailed(
            'telegram',
            '123456',
            'Test',
            'Failed',
            null,
            null,
            null,
            $message,
        );

        $this->assertSame($message, $event->message);
    }
}
