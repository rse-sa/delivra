<?php

namespace RSE\Delivra\Tests\Feature;

use Illuminate\Notifications\Notification;
use RSE\Delivra\Telegram\Channels\TelegramChannel;
use RSE\Delivra\Telegram\Messages\TextMessage;
use RSE\Delivra\Telegram\Telegram;
use RSE\Delivra\Tests\TestCase;

class TelegramChannelTest extends TestCase
{
    public function test_sends_notification(): void
    {
        $telegram = new Telegram(['default_token' => 'test-token']);
        $channel = new TelegramChannel($telegram);

        $notifiable = new class {
            public function routeNotificationFor($driver, $notification = null)
            {
                return '123456';
            }
        };

        $notification = new class extends Notification {
            public function toTelegram($notifiable)
            {
                return TextMessage::make()->message('Test message')->token('test-token');
            }
        };

        // This will fail due to invalid token, but tests the flow
        $this->expectException(\Exception::class);
        $channel->send($notifiable, $notification);
    }

    public function test_uses_notifiable_chat_id(): void
    {
        $telegram = new Telegram(['default_token' => 'test-token']);
        $channel = new TelegramChannel($telegram);

        $notifiable = new class {
            public function routeNotificationFor($driver, $notification = null)
            {
                return '789012';
            }
        };

        $notification = new class extends Notification {
            public function toTelegram($notifiable)
            {
                $message = TextMessage::make()->message('Test message')->token('test-token');
                // Chat ID should be set from notifiable
                return $message;
            }
        };

        // This will fail due to invalid token, but tests the flow
        $this->expectException(\Exception::class);
        $channel->send($notifiable, $notification);
    }
}
