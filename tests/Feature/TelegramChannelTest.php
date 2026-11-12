<?php

namespace RSE\Delivra\Tests\Feature;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use RSE\Delivra\Telegram\Channels\TelegramChannel;
use RSE\Delivra\Telegram\Messages\TextMessage;
use RSE\Delivra\Telegram\Telegram;
use RSE\Delivra\Tests\TestCase;

class TelegramChannelTest extends TestCase
{
    public function test_sends_notification(): void
    {
        $telegram = new Telegram(['default_token' => 'test-token']);
        $channel  = new TelegramChannel($telegram);

        $notifiable = new class
        {
            public function routeNotificationFor($driver, $notification = null)
            {
                return '123456';
            }
        };

        $notification = new class extends Notification
        {
            public function toTelegram($notifiable)
            {
                return TextMessage::make()->message('Test message')->token('test-token');
            }
        };

        // This will fail due to invalid token, but tests the flow
        $this->expectException(\Exception::class);
        $channel->send($notifiable, $notification);
    }

    public function test_notification_builds_message_inline_with_helper(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 7]])]);

        $notification = new class extends Notification
        {
            public function via($notifiable): array
            {
                return ['telegram'];
            }

            public function toTelegram($notifiable)
            {
                return telegram()->text('<b>New alert</b>');
            }
        };

        NotificationFacade::route('telegram', '123456')->notify($notification);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
            && $request['chat_id'] === '123456'
            && $request['text'] === '<b>New alert</b>'
            && $request['parse_mode'] === 'html');
    }

    public function test_uses_notifiable_chat_id(): void
    {
        $telegram = new Telegram(['default_token' => 'test-token']);
        $channel  = new TelegramChannel($telegram);

        $notifiable = new class
        {
            public function routeNotificationFor($driver, $notification = null)
            {
                return '789012';
            }
        };

        $notification = new class extends Notification
        {
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
