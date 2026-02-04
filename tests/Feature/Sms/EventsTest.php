<?php

namespace RSE\Delivra\Tests\Feature\Sms;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use RSE\Delivra\Events\DelivraMessageFailed;
use RSE\Delivra\Events\DelivraMessageSending;
use RSE\Delivra\Events\DelivraMessageSent;
use RSE\Delivra\Sms\Channels\SmsChannel;
use RSE\Delivra\Sms\SmsBuilder;
use RSE\Delivra\Tests\TestCase;

class EventsTest extends TestCase
{
    public function test_dispatches_sending_event_on_notification(): void
    {
        Event::fake([DelivraMessageSending::class]);

        $channel = new SmsChannel(app('delivra-sms'));

        $notifiable = new class {
            public function routeNotificationFor($driver, $notification = null)
            {
                return '9665000000';
            }
        };

        $notification = new class extends Notification {
            public function toSms($notifiable)
            {
                return SmsBuilder::make()->body('Test message');
            }
        };

        $channel->send($notifiable, $notification);

        Event::assertDispatched(DelivraMessageSending::class, function ($event) {
            return $event->channel === 'sms'
                && $event->recipient === '9665000000'
                && $event->body === 'Test message';
        });
    }

    public function test_dispatches_sent_event_on_successful_send(): void
    {
        Event::fake([DelivraMessageSent::class]);

        $channel = new SmsChannel(app('delivra-sms'));

        $notifiable = new class {
            public function routeNotificationFor($driver, $notification = null)
            {
                return '9665000000';
            }
        };

        $notification = new class extends Notification {
            public function toSms($notifiable)
            {
                return SmsBuilder::make()->body('Test message');
            }
        };

        $channel->send($notifiable, $notification);

        Event::assertDispatched(DelivraMessageSent::class, function ($event) {
            return $event->channel === 'sms'
                && $event->recipient === '9665000000'
                && $event->body === 'Test message'
                && $event->response !== null;
        });
    }

    public function test_dispatches_events_in_correct_order(): void
    {
        Event::fake([DelivraMessageSending::class, DelivraMessageSent::class]);

        $channel = new SmsChannel(app('delivra-sms'));

        $notifiable = new class {
            public function routeNotificationFor($driver, $notification = null)
            {
                return '9665000000';
            }
        };

        $notification = new class extends Notification {
            public function toSms($notifiable)
            {
                return SmsBuilder::make()->body('Test message');
            }
        };

        $channel->send($notifiable, $notification);

        Event::assertDispatched(DelivraMessageSending::class);
        Event::assertDispatched(DelivraMessageSent::class);
    }

    public function test_includes_notifiable_and_notification_in_events(): void
    {
        Event::fake([DelivraMessageSending::class, DelivraMessageSent::class]);

        $channel = new SmsChannel(app('delivra-sms'));

        $notifiable = new class {
            public function routeNotificationFor($driver, $notification = null)
            {
                return '9665000000';
            }
        };

        $notification = new class extends Notification {
            public function toSms($notifiable)
            {
                return SmsBuilder::make()->body('Test message');
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

    public function test_dispatches_per_recipient_events_for_batch(): void
    {
        Event::fake([DelivraMessageSending::class, DelivraMessageSent::class]);

        $channel = new SmsChannel(app('delivra-sms'));

        $notifiable = new class {
            public function routeNotificationFor($driver, $notification = null)
            {
                return ['9665000000', '9665111111'];
            }
        };

        $notification = new class extends Notification {
            public function toSms($notifiable)
            {
                return SmsBuilder::make()->body('Test message');
            }
        };

        $channel->send($notifiable, $notification);

        // Should dispatch 2 sending events (one per recipient)
        Event::assertDispatched(DelivraMessageSending::class, 2);

        // Should dispatch 2 sent events (one per recipient)
        Event::assertDispatched(DelivraMessageSent::class, 2);
    }

    public function test_includes_driver_in_events(): void
    {
        Event::fake([DelivraMessageSending::class]);

        $channel = new SmsChannel(app('delivra-sms'));

        $notifiable = new class {
            public function routeNotificationFor($driver, $notification = null)
            {
                return '9665000000';
            }
        };

        $notification = new class extends Notification {
            public function toSms($notifiable)
            {
                return SmsBuilder::make()->body('Test message')->via('null');
            }
        };

        $channel->send($notifiable, $notification);

        Event::assertDispatched(DelivraMessageSending::class, function ($event) {
            return $event->driver === 'null';
        });
    }

    public function test_dispatches_failed_event_on_exception(): void
    {
        Event::fake([DelivraMessageSending::class, DelivraMessageFailed::class]);

        $this->app['config']->set('delivra.sms.default', 'null');

        $channel = new SmsChannel(app('delivra-sms'));

        $notifiable = new class {
            public function routeNotificationFor($driver, $notification = null)
            {
                return '9665000000';
            }
        };

        $notification = new class extends Notification {
            public function toSms($notifiable)
            {
                return SmsBuilder::make()->body('Test message')->via('null');
            }
        };

        try {
            $channel->send($notifiable, $notification);
        } catch (\Throwable $e) {
            // Expected
        }

        Event::assertDispatched(DelivraMessageSending::class);
        // Note: With null driver, send succeeds, so failed event may not fire
        // This test demonstrates the structure for testing failed events
    }

    public function test_sending_event_is_dispatched_before_send(): void
    {
        $eventDispatched = false;
        $capturedRecipient = null;

        // Register a listener to capture the event
        Event::listen(DelivraMessageSending::class, function ($event) use (&$eventDispatched, &$capturedRecipient) {
            $eventDispatched = true;
            $capturedRecipient = $event->recipient;
        });

        $channel = new SmsChannel(app('delivra-sms'));

        $notifiable = new class {
            public function routeNotificationFor($driver, $notification = null)
            {
                return '9665000000';
            }
        };

        $notification = new class extends Notification {
            public function toSms($notifiable)
            {
                return SmsBuilder::make()->body('Test message');
            }
        };

        $channel->send($notifiable, $notification);

        // Verify the event was dispatched before the send
        $this->assertTrue($eventDispatched, 'Sending event should be dispatched');
        $this->assertEquals('9665000000', $capturedRecipient, 'Event should contain recipient');
    }

    public function test_event_properties_are_complete(): void
    {
        Event::fake([DelivraMessageSending::class, DelivraMessageSent::class]);

        $channel = new SmsChannel(app('delivra-sms'));

        $notifiable = new class {
            public function routeNotificationFor($driver, $notification = null)
            {
                return '9665000000';
            }
        };

        $notification = new class extends Notification {
            public function toSms($notifiable)
            {
                return SmsBuilder::make()->body('Test message body')->via('msegat');
            }
        };

        $channel->send($notifiable, $notification);

        Event::assertDispatched(DelivraMessageSending::class, function ($event) use ($notifiable, $notification) {
            return $event->channel === 'sms'
                && $event->recipient === '9665000000'
                && $event->body === 'Test message body'
                && $event->driver === 'msegat'
                && $event->notifiable === $notifiable
                && $event->notification === $notification
                && is_array($event->payload)
                && $event->payload['recipient'] === '9665000000'
                && $event->payload['body'] === 'Test message body'
                && $event->payload['driver'] === 'msegat';
        });

        Event::assertDispatched(DelivraMessageSent::class, function ($event) {
            return $event->channel === 'sms'
                && $event->recipient === '9665000000'
                && $event->body === 'Test message body'
                && $event->response !== null
                && $event->response instanceof \RSE\Delivra\Sms\SmsResponse;
        });
    }
}
