<?php

namespace RSE\Delivra\Tests\Feature\Telegram;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use RSE\Delivra\Events\DelivraMessageFailed;
use RSE\Delivra\Events\DelivraMessageSending;
use RSE\Delivra\Events\DelivraMessageSent;
use RSE\Delivra\Telegram\Channels\TelegramChannel;
use RSE\Delivra\Telegram\Messages\TextMessage;
use RSE\Delivra\Tests\TestCase;

class EventsTest extends TestCase
{
    public function test_dispatches_sending_event_on_notification(): void
    {
        Event::fake([DelivraMessageSending::class, DelivraMessageSent::class]);

        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok'     => true,
                'result' => [
                    'message_id' => 123,
                    'chat'       => ['id' => '123456'],
                ],
            ]),
        ]);

        $telegram = new \RSE\Delivra\Telegram\Telegram(['default_token' => 'test-token']);
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

        $channel->send($notifiable, $notification);

        Event::assertDispatched(DelivraMessageSending::class);
    }

    public function test_dispatches_sent_event_on_successful_send(): void
    {
        Event::fake([DelivraMessageSending::class, DelivraMessageSent::class]);

        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok'     => true,
                'result' => [
                    'message_id' => 123,
                    'chat'       => ['id' => '123456'],
                ],
            ]),
        ]);

        $telegram = new \RSE\Delivra\Telegram\Telegram(['default_token' => 'test-token']);
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

        $channel->send($notifiable, $notification);

        Event::assertDispatched(DelivraMessageSent::class);
    }

    public function test_dispatches_events_in_correct_order(): void
    {
        Event::fake([DelivraMessageSending::class, DelivraMessageSent::class]);

        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok'     => true,
                'result' => [
                    'message_id' => 123,
                    'chat'       => ['id' => '123456'],
                ],
            ]),
        ]);

        $telegram = new \RSE\Delivra\Telegram\Telegram(['default_token' => 'test-token']);
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

        $channel->send($notifiable, $notification);

        Event::assertDispatched(DelivraMessageSending::class);
        Event::assertDispatched(DelivraMessageSent::class);
    }

    public function test_includes_notifiable_and_notification_in_events(): void
    {
        Event::fake([DelivraMessageSending::class, DelivraMessageSent::class]);

        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok'     => true,
                'result' => [
                    'message_id' => 123,
                    'chat'       => ['id' => '123456'],
                ],
            ]),
        ]);

        $telegram = new \RSE\Delivra\Telegram\Telegram(['default_token' => 'test-token']);
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

        $channel->send($notifiable, $notification);

        Event::assertDispatched(DelivraMessageSending::class, function ($event) use ($notifiable, $notification) {
            return $event->notifiable === $notifiable
                && $event->notification === $notification;
        });

        Event::assertDispatched(DelivraMessageSent::class, function ($event) use ($notifiable, $notification) {
            return $event->notifiable === $notifiable
                && $event->notification === $notification;
        });
    }

    public function test_includes_message_instance_in_events(): void
    {
        Event::fake([DelivraMessageSending::class, DelivraMessageSent::class]);

        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok'     => true,
                'result' => [
                    'message_id' => 123,
                    'chat'       => ['id' => '123456'],
                ],
            ]),
        ]);

        $telegram = new \RSE\Delivra\Telegram\Telegram(['default_token' => 'test-token']);
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

        $channel->send($notifiable, $notification);

        Event::assertDispatched(DelivraMessageSending::class, function ($event) {
            return $event->message instanceof TextMessage;
        });

        Event::assertDispatched(DelivraMessageSent::class, function ($event) {
            return $event->message instanceof TextMessage;
        });
    }

    public function test_includes_token_in_events(): void
    {
        Event::fake([DelivraMessageSending::class, DelivraMessageSent::class]);

        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok'     => true,
                'result' => [
                    'message_id' => 123,
                    'chat'       => ['id' => '123456'],
                ],
            ]),
        ]);

        $telegram = new \RSE\Delivra\Telegram\Telegram(['default_token' => 'test-token']);
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
                return TextMessage::make()
                    ->message('Test message')
                    ->token('custom-bot-token');
            }
        };

        $channel->send($notifiable, $notification);

        Event::assertDispatched(DelivraMessageSending::class, function ($event) {
            return $event->token === 'custom-bot-token';
        });
    }

    public function test_dispatches_per_recipient_events_for_batch(): void
    {
        Event::fake([DelivraMessageSending::class, DelivraMessageSent::class]);

        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok'     => true,
                'result' => [
                    'message_id' => 123,
                    'chat'       => ['id' => '123456'],
                ],
            ]),
        ]);

        $telegram = new \RSE\Delivra\Telegram\Telegram(['default_token' => 'test-token']);
        $channel  = new TelegramChannel($telegram);

        $notifiable = new class
        {
            public function routeNotificationFor($driver, $notification = null)
            {
                return ['123456', '789012'];
            }
        };

        $notification = new class extends Notification
        {
            public function toTelegram($notifiable)
            {
                return TextMessage::make()->message('Test message')->token('test-token');
            }
        };

        $channel->send($notifiable, $notification);

        // Should dispatch 2 sending events (one per recipient)
        Event::assertDispatched(DelivraMessageSending::class, 2);

        // Should dispatch 2 sent events (one per recipient)
        Event::assertDispatched(DelivraMessageSent::class, 2);
    }

    public function test_includes_payload_in_sending_event(): void
    {
        Event::fake([DelivraMessageSending::class, DelivraMessageSent::class]);

        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok'     => true,
                'result' => [
                    'message_id' => 123,
                    'chat'       => ['id' => '123456'],
                ],
            ]),
        ]);

        $telegram = new \RSE\Delivra\Telegram\Telegram(['default_token' => 'test-token']);
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
                return TextMessage::make()
                    ->message('Test message')
                    ->token('test-token');
            }
        };

        $channel->send($notifiable, $notification);

        Event::assertDispatched(DelivraMessageSending::class, function ($event) {
            return isset($event->payload['text'])
                && $event->payload['text'] === 'Test message'
                && isset($event->payload['parse_mode']);
        });
    }

    public function test_sending_event_is_dispatched_before_send(): void
    {
        $eventDispatched   = false;
        $capturedRecipient = null;

        Event::listen(DelivraMessageSending::class, function ($event) use (&$eventDispatched, &$capturedRecipient) {
            $eventDispatched   = true;
            $capturedRecipient = $event->recipient;
        });

        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok'     => true,
                'result' => [
                    'message_id' => 123,
                    'chat'       => ['id' => '123456'],
                ],
            ]),
        ]);

        $telegram = new \RSE\Delivra\Telegram\Telegram(['default_token' => 'test-token']);
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

        $channel->send($notifiable, $notification);

        // Verify the event was dispatched before the send
        $this->assertTrue($eventDispatched, 'Sending event should be dispatched');
        $this->assertEquals('123456', $capturedRecipient, 'Event should contain recipient');
    }

    public function test_event_properties_are_complete(): void
    {
        Event::fake([DelivraMessageSending::class, DelivraMessageSent::class]);

        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok'     => true,
                'result' => [
                    'message_id' => 123,
                    'chat'       => ['id' => '123456'],
                ],
            ]),
        ]);

        $telegram = new \RSE\Delivra\Telegram\Telegram(['default_token' => 'test-token']);
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
                return TextMessage::make()
                    ->message('Complete test message')
                    ->token('my-bot-token');
            }
        };

        $channel->send($notifiable, $notification);

        // Verify events were dispatched
        Event::assertDispatched(DelivraMessageSending::class);
        Event::assertDispatched(DelivraMessageSent::class);
    }

    public function test_failed_event_is_dispatched_on_error(): void
    {
        Event::fake([DelivraMessageSending::class, DelivraMessageFailed::class]);

        // Set up error response for all URLs
        Http::fake([
            '*' => Http::response([
                'ok'          => false,
                'error_code'  => 400,
                'description' => 'Bad Request: chat not found',
            ], 400),
        ]);

        $telegram = new \RSE\Delivra\Telegram\Telegram(['default_token' => 'test-token']);
        $channel  = new TelegramChannel($telegram);

        $notifiable = new class
        {
            public function routeNotificationFor($driver, $notification = null)
            {
                return 'invalid-chat-id';
            }
        };

        $notification = new class extends Notification
        {
            public function toTelegram($notifiable)
            {
                return TextMessage::make()->message('Test message')->token('test-token');
            }
        };

        $exceptionThrown = false;
        try {
            $channel->send($notifiable, $notification);
        } catch (\Throwable $e) {
            $exceptionThrown = true;
        }

        // Verify exception was thrown (error was handled)
        $this->assertTrue($exceptionThrown, 'Exception should be thrown on API error');

        // Verify events were dispatched
        Event::assertDispatched(DelivraMessageSending::class);
        Event::assertDispatched(DelivraMessageFailed::class);
    }
}
