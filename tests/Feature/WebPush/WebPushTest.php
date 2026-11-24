<?php

namespace RSE\Delivra\Tests\Feature\WebPush;

use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use RSE\Delivra\Events\DelivraMessageFailed;
use RSE\Delivra\Events\DelivraMessageSending;
use RSE\Delivra\Events\DelivraMessageSent;
use RSE\Delivra\Exceptions\WebPush\InvalidMessageException;
use RSE\Delivra\Exceptions\WebPush\WebPushConfigurationException;
use RSE\Delivra\Facades\WebPush;
use RSE\Delivra\WebPush\Channels\WebPushChannel;
use RSE\Delivra\WebPush\Concerns\HasPushSubscriptions;
use RSE\Delivra\WebPush\Events\WebPushSubscriptionExpired;
use RSE\Delivra\WebPush\Messages\WebPushMessage;
use RSE\Delivra\WebPush\Models\PushSubscription;
use RSE\Delivra\WebPush\Testing\SentWebPush;
use RSE\Delivra\WebPush\WebPushManager;
use RSE\Delivra\WebPush\WebPushReport;
use RSE\Delivra\WebPush\WebPushResult;

class WebPushTest extends WebPushTestCase
{
    protected function user(string $name = 'Sara'): PushUser
    {
        return PushUser::query()->create(['name' => $name]);
    }

    protected function subscribe(PushUser $user, string $endpoint = 'https://push.example.com/send/abc'): PushSubscription
    {
        $browser = $this->browserSubscription($endpoint);

        return $user->updatePushSubscription($endpoint, $browser['keys']['p256dh'], $browser['keys']['auth'], 'aes128gcm');
    }

    /*
     * Real sends through minishlink/web-push, with a mocked push service
     */

    public function test_sends_an_encrypted_push_with_vapid_headers(): void
    {
        $this->fakePushServices(new Response(201));
        Event::fake([DelivraMessageSent::class]);
        $user = $this->user();
        $this->subscribe($user);

        $user->notify(new RequestWaiting);

        $this->assertCount(1, $this->pushRequests);
        $request = $this->pushRequests[0]['request'];

        $this->assertSame('https://push.example.com/send/abc', (string) $request->getUri());
        $this->assertStringStartsWith('vapid t=', $request->getHeaderLine('Authorization'));
        $this->assertStringContainsString('k=' . static::$vapid['publicKey'], $request->getHeaderLine('Authorization'));
        $this->assertSame('aes128gcm', $request->getHeaderLine('Content-Encoding'));
        $this->assertSame('3600', $request->getHeaderLine('TTL'));
        $this->assertSame('high', $request->getHeaderLine('Urgency'));
        // encrypted: the title is not readable on the wire
        $this->assertStringNotContainsString('Request waiting', (string) $request->getBody());

        Event::assertDispatched(DelivraMessageSent::class, fn (DelivraMessageSent $event) => $event->channel === 'webpush'
            && $event->recipient === 'push.example.com/...nd/abc'
            && $event->body === 'Request waiting'
            && $event->notifiable === $user);
    }

    public function test_config_defaults_apply_when_the_message_sets_none(): void
    {
        config()->set('delivra.webpush.ttl', 60);
        $this->app->forgetInstance('delivra-webpush');
        $this->fakePushServices(new Response(201));
        $user = $this->user();
        $this->subscribe($user);

        $user->notify(new RequestWaiting(plain: true));

        $this->assertSame('60', $this->pushRequests[0]['request']->getHeaderLine('TTL'));
        $this->assertFalse($this->pushRequests[0]['request']->hasHeader('Urgency'));
    }

    public function test_the_channel_returns_a_report_per_subscription(): void
    {
        $this->fakePushServices(new Response(201), new Response(500));
        $user = $this->user();
        $this->subscribe($user, 'https://push.example.com/send/a');
        $this->subscribe($user, 'https://push.example.com/send/b');

        $report = (new WebPushChannel)->send($user, new RequestWaiting);

        $this->assertInstanceOf(WebPushReport::class, $report);
        $this->assertSame(1, $report->successfulCount());
        $this->assertSame(1, $report->failedCount());
        $this->assertSame(500, $report->failures()->first()->httpStatus);
    }

    public function test_an_expired_subscription_fires_the_event_and_is_deleted(): void
    {
        $this->fakePushServices(new Response(410), new Response(201));
        Event::fake([WebPushSubscriptionExpired::class, DelivraMessageFailed::class]);
        $user = $this->user();
        $gone = $this->subscribe($user, 'https://push.example.com/send/gone');
        $this->subscribe($user, 'https://push.example.com/send/live');

        $report = (new WebPushChannel)->send($user, new RequestWaiting);

        $this->assertSame(['https://push.example.com/send/gone'], $report->expiredEndpoints());
        Event::assertDispatched(WebPushSubscriptionExpired::class, fn (WebPushSubscriptionExpired $event) => $event->endpoint === 'https://push.example.com/send/gone'
            && $event->status === 410
            && $event->subscription->model()->is($gone)
            && $event->notifiable === $user);
        Event::assertDispatched(DelivraMessageFailed::class, fn (DelivraMessageFailed $event) => str_starts_with($event->error, 'HTTP 410'));
        $this->assertSame(['https://push.example.com/send/live'], PushSubscription::query()->pluck('endpoint')->all());
    }

    public function test_a_queued_listener_gets_the_expired_event_after_the_delete(): void
    {
        $this->fakePushServices(new Response(410));
        Event::fake([WebPushSubscriptionExpired::class]);
        $user = $this->user();
        $this->subscribe($user);

        $user->notify(new RequestWaiting);

        $event = Event::dispatched(WebPushSubscriptionExpired::class)->first()[0];
        $this->assertSame(0, PushSubscription::query()->count());

        // what the queue does with a listener's event
        $restored = unserialize(serialize($event));

        $this->assertSame('https://push.example.com/send/abc', $restored->endpoint);
        $this->assertSame('https://push.example.com/send/abc', $restored->subscription->endpoint);
        $this->assertTrue($restored->notifiable->is($user));
    }

    public function test_404_also_means_expired(): void
    {
        $this->fakePushServices(new Response(404));
        $user = $this->user();
        $this->subscribe($user);

        $report = (new WebPushChannel)->send($user, new RequestWaiting);

        $this->assertTrue($report->all()[0]->isExpired());
        $this->assertSame(0, PushSubscription::query()->count());
    }

    public function test_expired_subscriptions_stay_when_delete_is_off(): void
    {
        config()->set('delivra.webpush.delete_expired', false);
        $this->app->forgetInstance('delivra-webpush');
        $this->fakePushServices(new Response(410));
        Event::fake([WebPushSubscriptionExpired::class]);
        $user = $this->user();
        $this->subscribe($user);

        $user->notify(new RequestWaiting);

        Event::assertDispatched(WebPushSubscriptionExpired::class);
        $this->assertSame(1, PushSubscription::query()->count());
    }

    public function test_other_failures_fire_failed_but_not_expired(): void
    {
        $this->fakePushServices(new Response(500));
        Event::fake([WebPushSubscriptionExpired::class, DelivraMessageFailed::class]);
        $user = $this->user();
        $this->subscribe($user);

        $user->notify(new RequestWaiting);

        Event::assertDispatched(DelivraMessageFailed::class, fn (DelivraMessageFailed $event) => $event->channel === 'webpush' && $event->exception !== null);
        Event::assertNotDispatched(WebPushSubscriptionExpired::class);
        $this->assertSame(1, PushSubscription::query()->count());
    }

    public function test_a_subscription_with_broken_keys_fails_alone(): void
    {
        $this->fakePushServices(new Response(201));
        $user = $this->user();
        $user->updatePushSubscription('https://push.example.com/send/broken', 'not-a-key', 'nope');
        $this->subscribe($user, 'https://push.example.com/send/good');

        $report = (new WebPushChannel)->send($user, new RequestWaiting);

        $this->assertSame(WebPushResult::FAILED, $report->all()[0]->status);
        $this->assertNotNull($report->all()[0]->exception);
        $this->assertTrue($report->all()[1]->successful());
        $this->assertCount(1, $this->pushRequests);
    }

    /*
     * Nothing sent and cancelling
     */

    public function test_nothing_sent_returns_an_empty_array(): void
    {
        $fake = WebPush::fake();
        $user = $this->user();

        $this->assertSame([], (new WebPushChannel)->send($user, new RequestWaiting));

        $this->subscribe($user);
        $this->assertSame([], (new WebPushChannel)->send($user, new RequestWaiting(skip: true)));

        $fake->assertNothingSent();
    }

    public function test_a_sending_listener_cancels_the_push(): void
    {
        $fake = WebPush::fake();
        Event::listen(DelivraMessageSending::class, fn (DelivraMessageSending $event) => $event->channel === 'webpush' ? false : null);
        $user = $this->user();
        $this->subscribe($user);

        $this->assertSame([], (new WebPushChannel)->send($user, new RequestWaiting));
        $fake->assertNothingSent();
    }

    public function test_the_sending_event_carries_the_payload_without_keys(): void
    {
        WebPush::fake();
        Event::fake([DelivraMessageSending::class]);
        $user = $this->user();
        $this->subscribe($user);

        $user->notify(new RequestWaiting);

        Event::assertDispatched(DelivraMessageSending::class, function (DelivraMessageSending $event) {
            return $event->payload['title'] === 'Request waiting'
                && ! str_contains(json_encode($event->payload) . $event->recipient, 'p256dh');
        });
    }

    /*
     * Setup errors
     */

    public function test_a_missing_library_throws_a_clear_configuration_error(): void
    {
        $this->app->instance('delivra-webpush', new class(config('delivra.webpush')) extends WebPushManager
        {
            public function libraryInstalled(): bool
            {
                return false;
            }
        });
        $user = $this->user();
        $this->subscribe($user);

        $this->assertFalse(webpush()->configured());

        $this->expectException(WebPushConfigurationException::class);
        $this->expectExceptionMessage('composer require minishlink/web-push');

        (new WebPushChannel)->send($user, new RequestWaiting);
    }

    public function test_missing_vapid_keys_throw(): void
    {
        config()->set('delivra.webpush.vapid.private_key', null);
        $this->app->forgetInstance('delivra-webpush');
        $user = $this->user();
        $this->subscribe($user);

        $this->assertFalse(webpush()->configured());

        $this->expectException(WebPushConfigurationException::class);
        $this->expectExceptionMessage('VAPID keys');

        (new WebPushChannel)->send($user, new RequestWaiting);
    }

    /*
     * The fake
     */

    public function test_the_fake_records_pushes_and_asserts(): void
    {
        $fake = WebPush::fake();
        $user = $this->user();
        $this->subscribe($user);

        $user->notify(new RequestWaiting);

        $fake->assertSentCount(1)
            ->assertSentTo('https://push.example.com/send/abc', fn (WebPushMessage $message) => $message->getTitle() === 'Request waiting')
            ->assertSent(fn (WebPushMessage $message, SentWebPush $push) => $push->payload['data']['url'] === '/requests/5' && $push->options['TTL'] === 3600)
            ->assertNotSent(fn (WebPushMessage $message) => $message->getTitle() === 'Other');
    }

    public function test_the_fake_can_expire_endpoints(): void
    {
        $fake = WebPush::fake()->expire('https://push.example.com/send/abc');
        Event::fake([WebPushSubscriptionExpired::class]);
        $user = $this->user();
        $this->subscribe($user);

        $user->notify(new RequestWaiting);

        $fake->assertSentTo('https://push.example.com/send/abc');
        Event::assertDispatched(WebPushSubscriptionExpired::class);
        $this->assertSame(0, PushSubscription::query()->count());
    }

    /*
     * Routes, the trait and the helper
     */

    public function test_anonymous_notifiable_takes_the_browser_json(): void
    {
        $fake = WebPush::fake();

        NotificationFacade::route('webpush', [$this->browserSubscription('https://push.example.com/send/guest')])->notify(new RequestWaiting);

        $fake->assertSentTo('https://push.example.com/send/guest');
    }

    public function test_the_helper_sends_without_a_notification(): void
    {
        $this->fakePushServices(new Response(201));

        $report = webpush()->to($this->browserSubscription())->send(webpush()->message('Hello', 'From the helper')->url('/inbox'));

        $this->assertSame(1, $report->successfulCount());
    }

    public function test_the_helper_needs_a_subscription(): void
    {
        $this->expectException(InvalidMessageException::class);

        webpush()->to([])->send(webpush()->message('Hello'));
    }

    public function test_a_device_moves_to_the_user_who_subscribes_on_it(): void
    {
        $first  = $this->user('First');
        $second = $this->user('Second');
        $this->subscribe($first);

        $this->subscribe($second);

        $this->assertSame(0, $first->routeNotificationForWebPush()->count());
        $this->assertSame(1, $second->routeNotificationForWebPush()->count());

        $second->deletePushSubscription('https://push.example.com/send/abc');
        $this->assertSame(0, PushSubscription::query()->count());
    }

    public function test_the_channel_is_registered_under_its_name(): void
    {
        $fake = WebPush::fake();
        $user = $this->user();
        $this->subscribe($user);

        $user->notify(new RequestWaiting(via: 'webpush'));

        $fake->assertSentCount(1);
    }

    /*
     * The message
     */

    public function test_the_payload_is_flat_for_the_service_worker(): void
    {
        $message = WebPushMessage::make('Title', 'Body')
            ->icon('/icon.png')->lang('ar')->dir('rtl')->tag('t1')
            ->action('Open', 'open')
            ->data(['id' => 5])->url('/requests/5')
            ->ttl(60)->urgency('low')->topic('requests');

        $this->assertSame([
            'title'   => 'Title',
            'body'    => 'Body',
            'icon'    => '/icon.png',
            'lang'    => 'ar',
            'dir'     => 'rtl',
            'tag'     => 't1',
            'actions' => [['action' => 'open', 'title' => 'Open']],
            'data'    => ['id' => 5, 'url' => '/requests/5'],
        ], $message->toArray());
        $this->assertSame(['TTL' => 60, 'urgency' => 'low', 'topic' => 'requests'], $message->getOptions());
    }

    public function test_the_message_rejects_a_bad_urgency_or_topic(): void
    {
        $this->expectException(InvalidMessageException::class);

        WebPushMessage::make('Title')->urgency('urgent');
    }

    public function test_the_message_rejects_a_long_topic(): void
    {
        $this->expectException(InvalidMessageException::class);

        WebPushMessage::make('Title')->topic(str_repeat('a', 33));
    }

    public function test_the_vapid_command_prints_new_keys(): void
    {
        $this->artisan('delivra:webpush-vapid')
            ->expectsOutputToContain('DELIVRA_WEBPUSH_PUBLIC_KEY=B')
            ->expectsOutputToContain('DELIVRA_WEBPUSH_PRIVATE_KEY=')
            ->assertSuccessful();
    }
}

class PushUser extends Model
{
    use HasPushSubscriptions;
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;
}

class RequestWaiting extends Notification
{
    public function __construct(public bool $skip = false, public bool $plain = false, public string $via = WebPushChannel::class) {}

    public function via($notifiable): array
    {
        return [$this->via];
    }

    public function toWebPush($notifiable, Notification $notification): ?WebPushMessage
    {
        if ($this->skip) {
            return null;
        }

        $message = WebPushMessage::make('Request waiting', 'New laptops')->url('/requests/5');

        return $this->plain ? $message : $message->ttl(3600)->urgency('high');
    }
}
