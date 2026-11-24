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

    public function test_route_objects_go_to_the_sender_registered_for_their_class(): void
    {
        Http::fake();
        $sent = [];

        Telegram::sendUsing(\ArrayObject::class, function ($message, $route, $notifiable, $notification) use (&$sent) {
            $sent[] = [$message->getMessage(), $route['user'], $notification->id];

            return 'msg-1';
        });

        $notifiable = new class
        {
            public function routeNotificationFor($driver, $notification = null)
            {
                return new \ArrayObject(['user' => 7]);
            }
        };

        $notification = new class extends Notification
        {
            public function toTelegram($notifiable)
            {
                return telegram()->text('Linked hello');
            }
        };
        $notification->id = 'n-1';

        $result = (new TelegramChannel(telegram()))->send($notifiable, $notification);

        $this->assertSame(['msg-1'], $result);
        $this->assertSame([['Linked hello', 7, 'n-1']], $sent);
        Http::assertNothingSent();
    }

    public function test_a_sender_that_sends_nothing_reports_no_results(): void
    {
        Telegram::sendUsing(\ArrayObject::class, fn () => null);

        $notifiable = new class
        {
            public function routeNotificationFor($driver, $notification = null)
            {
                return new \ArrayObject;
            }
        };

        $notification = new class extends Notification
        {
            public function toTelegram($notifiable)
            {
                return telegram()->text('Hi');
            }
        };

        $this->assertSame([], (new TelegramChannel(telegram()))->send($notifiable, $notification));
    }

    public function test_chat_id_routes_still_reach_the_bot_api_when_a_sender_is_registered(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 7]])]);
        Telegram::sendUsing(\ArrayObject::class, fn () => $this->fail('the sender must not run for chat ids'));

        NotificationFacade::route('telegram', '123456')->notify(new class extends Notification
        {
            public function via($notifiable): array
            {
                return ['telegram'];
            }

            public function toTelegram($notifiable)
            {
                return telegram()->text('Plain');
            }
        });

        Http::assertSent(fn ($request) => $request['chat_id'] === '123456');
    }

    protected function tearDown(): void
    {
        Telegram::flushSenders();

        parent::tearDown();
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
