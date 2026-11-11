<?php

namespace RSE\Delivra\Tests\Feature\Fcm;

use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use InvalidArgumentException;
use RSE\Delivra\Facades\Fcm;
use RSE\Delivra\Fcm\Channels\FcmChannel;
use RSE\Delivra\Fcm\Events\FcmTokenInvalid;
use RSE\Delivra\Fcm\Messages\FcmMessage;
use RSE\Delivra\Fcm\Testing\SentPush;

class ChannelAndFakeTest extends FcmTestCase
{
    public function test_channel_sends_to_token_list(): void
    {
        Http::fake();
        $fake = Fcm::fake();

        (new FcmUser(['token-a', 'token-b']))->notify(new OrderReady);

        $fake->assertSentCount(2)
            ->assertSentTo('token-a', fn (FcmMessage $message) => $message->getTitle() === 'Order ready')
            ->assertSent(fn (FcmMessage $message, SentPush $push) => $push->payload['data']['platform'] === 'all');

        Http::assertNothingSent();
    }

    public function test_platform_map_builds_one_message_per_platform_and_skips_unknown(): void
    {
        $fake = Fcm::fake();

        (new FcmUser(['android' => ['a1', 'a2'], 'ios' => ['i1'], 'huawei' => ['h1']]))->notify(new OrderReady);

        $fake->assertSentCount(3)
            ->assertSentTo('a1', fn (FcmMessage $message) => $message->getData()['platform'] === 'android')
            ->assertSentTo('i1', fn (FcmMessage $message) => $message->getData()['platform'] === 'ios')
            ->assertNotSent(fn (FcmMessage $message, SentPush $push) => $push->token() === 'h1');
    }

    public function test_message_topic_wins_over_tokens(): void
    {
        $fake = Fcm::fake();

        (new FcmUser(['token-a']))->notify(new OrderReady(topic: 'orders'));

        $fake->assertSentCount(1)->assertSentToTopic('orders');
    }

    public function test_skips_when_no_tokens_or_null_message(): void
    {
        $fake = Fcm::fake();

        (new FcmUser([]))->notify(new OrderReady);
        (new FcmUser(['token-a']))->notify(new OrderReady(skip: true));

        $fake->assertNothingSent();
    }

    public function test_anonymous_notifiable_route(): void
    {
        $fake = Fcm::fake();

        NotificationFacade::route('fcm', 'token-x')->notify(new OrderReady);

        $fake->assertSentTo('token-x');
    }

    public function test_fake_failures_fire_invalid_token_event(): void
    {
        Event::fake([FcmTokenInvalid::class]);
        $fake = Fcm::fake()->failFor('dead-token');

        $user = new FcmUser(['dead-token', 'live-token']);
        $user->notify(new OrderReady);

        Event::assertDispatched(FcmTokenInvalid::class, fn (FcmTokenInvalid $event) => $event->token === 'dead-token' && $event->notifiable === $user);
        $fake->assertSentTo('live-token');
    }

    public function test_fake_records_topic_changes(): void
    {
        $fake = Fcm::fake();

        fcm()->subscribe('news', ['a', 'b']);
        fcm()->unsubscribe('news', 'a');

        $fake->assertSubscribed('news', 'b')->assertUnsubscribed('/topics/news', 'a');
        $this->assertCount(3, $fake->topicChanges());
    }

    public function test_channel_name_can_be_disabled(): void
    {
        config()->set('delivra.fcm.channel', null);
        $this->app->forgetInstance(ChannelManager::class);
        $fake = Fcm::fake();

        (new FcmUser(['token-a']))->notify(new OrderReady(via: FcmChannel::class));
        $fake->assertSentTo('token-a');

        $this->expectException(InvalidArgumentException::class);
        (new FcmUser(['token-a']))->notify(new OrderReady);
    }
}

class FcmUser
{
    use Notifiable;

    public function __construct(public array $tokens) {}

    public function routeNotificationForFcm($notification): array
    {
        return $this->tokens;
    }
}

class OrderReady extends Notification
{
    public function __construct(public ?string $topic = null, public bool $skip = false, public string $via = 'fcm') {}

    public function via($notifiable): array
    {
        return [$this->via];
    }

    public function toFcm($notifiable, ?string $platform = null): ?FcmMessage
    {
        if ($this->skip) {
            return null;
        }

        return FcmMessage::make('Order ready', 'Your order is ready')
            ->data(['platform' => $platform ?? 'all'])
            ->topic($this->topic);
    }
}
