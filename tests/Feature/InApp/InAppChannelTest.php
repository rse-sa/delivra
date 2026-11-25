<?php

namespace RSE\Delivra\Tests\Feature\InApp;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RSE\Delivra\Events\DelivraMessageSending;
use RSE\Delivra\Events\DelivraMessageSent;
use RSE\Delivra\Facades\InApp;
use RSE\Delivra\InApp\Channels\InAppChannel;
use RSE\Delivra\InApp\Events\BroadcastInAppNotificationCreated;
use RSE\Delivra\InApp\Events\InAppNotificationCreated;
use RSE\Delivra\InApp\Messages\InAppMessage;
use RSE\Delivra\InApp\Models\InAppNotification;

class InAppChannelTest extends InAppTestCase
{
    public function test_notify_stores_one_row_with_its_morphs(): void
    {
        $user    = $this->user();
        $causer  = $this->user('Omar');
        $task    = Task::query()->create(['title' => 'Roof']);
        $company = Company::query()->create(['name' => 'Acme']);

        $user->notify(new MessageNotification(InAppMessage::make('x')->category('tasks')->subject($task)->causer($causer)->scope($company)));

        $row = InAppNotification::query()->sole();

        $this->assertTrue($row->notifiable->is($user));
        $this->assertTrue($row->subject->is($task));
        $this->assertTrue($row->causer->is($causer));
        $this->assertTrue($row->scopeable->is($company));
        $this->assertSame(MessageNotification::class, $row->type);
        $this->assertTrue(Str::isUuid($row->notification_id));
        $this->assertSame('tasks', $row->category);
        $this->assertSame(1, $row->payload['v']);
    }

    public function test_the_channel_works_without_the_trait(): void
    {
        $user = InAppPlainUser::query()->create(['name' => 'Sara']);

        $result = (new InAppChannel)->send($user, new MessageNotification(InAppMessage::make('x')));

        $this->assertInstanceOf(InAppNotification::class, $result);
        $this->assertTrue($result->notifiable->is($user));
    }

    public function test_a_null_message_stores_nothing(): void
    {
        $result = (new InAppChannel)->send($this->user(), new MessageNotification(null));

        $this->assertSame([], $result);
        $this->assertSame(0, InAppNotification::query()->count());
    }

    public function test_an_on_demand_notifiable_stores_nothing(): void
    {
        Notification::route('mail', 'a@example.com')->notify(new MessageNotification(InAppMessage::make('x')));

        $this->assertSame(0, InAppNotification::query()->count());
    }

    public function test_a_sending_listener_can_cancel(): void
    {
        Event::listen(DelivraMessageSending::class, fn (DelivraMessageSending $event) => $event->channel === 'in_app' ? false : null);

        $this->assertSame([], (new InAppChannel)->send($this->user(), new MessageNotification(InAppMessage::make('x'))));
        $this->assertSame(0, InAppNotification::query()->count());
    }

    public function test_it_fires_the_events(): void
    {
        Event::fake([InAppNotificationCreated::class, DelivraMessageSending::class, DelivraMessageSent::class, BroadcastInAppNotificationCreated::class]);

        $user = $this->user();
        $user->notify(new MessageNotification(InAppMessage::make('notifications.welcome.title')));

        Event::assertDispatched(DelivraMessageSending::class, fn ($event) => $event->recipient === $user->getMorphClass() . ':' . $user->id && $event->body === 'notifications.welcome.title');
        Event::assertDispatched(InAppNotificationCreated::class, fn ($event) => $event->notifiable->is($user));
        Event::assertDispatched(DelivraMessageSent::class, fn ($event) => $event->channel === 'in_app' && $event->response instanceof InAppNotification);
        Event::assertNotDispatched(BroadcastInAppNotificationCreated::class);
    }

    public function test_it_broadcasts_on_laravels_default_channel_when_enabled(): void
    {
        config(['delivra.in_app.broadcast' => true]);
        $this->app->forgetInstance('delivra-in-app');
        Event::fake([BroadcastInAppNotificationCreated::class]);

        $user = $this->user();
        $user->notify(new MessageNotification(InAppMessage::make('notifications.welcome.title', ['name' => 'Sara'])));

        Event::assertDispatched(BroadcastInAppNotificationCreated::class, function (BroadcastInAppNotificationCreated $event) use ($user) {
            $channel = $event->broadcastOn()[0];

            return $channel->name === 'private-' . str_replace('\\', '.', InAppUser::class) . '.' . $user->id
                && $event->broadcastWith()['title'] === 'Welcome Sara'
                && $event->broadcastAs() === 'in-app-notification.created';
        });
    }

    public function test_an_explicit_scope_wins_over_the_resolver(): void
    {
        $acme  = Company::query()->create(['name' => 'Acme']);
        $other = Company::query()->create(['name' => 'Other']);
        InApp::scopeUsing(fn () => $acme);

        $user = $this->user();
        $user->notify(new MessageNotification(InAppMessage::make('resolved')));
        $user->notify(new MessageNotification(InAppMessage::make('explicit')->scope($other)));
        $user->notify(new MessageNotification(InAppMessage::make('global')->withoutScope()));

        $scopes = InAppNotification::query()->get()->mapWithKeys(fn ($row) => [$row->payload['title']['k'] => $row->scope_id]);

        $this->assertSame($acme->id, $scopes['resolved']);
        $this->assertSame($other->id, $scopes['explicit']);
        $this->assertNull($scopes['global']);
    }

    public function test_the_resolver_gets_the_notification_when_sending(): void
    {
        $seen = null;
        InApp::scopeUsing(function ($notifiable, $notification) use (&$seen) {
            $seen = $notification;

            return null;
        });

        // laravel clones the notification per channel
        $this->user()->notify(new MessageNotification(InAppMessage::make('x')));

        $this->assertInstanceOf(MessageNotification::class, $seen);
    }

    public function test_reading_shows_the_current_scope_plus_global_rows(): void
    {
        $acme  = Company::query()->create(['name' => 'Acme']);
        $other = Company::query()->create(['name' => 'Other']);
        $user  = $this->user();

        InApp::send($user, InAppMessage::make('acme')->scope($acme));
        InApp::send($user, InAppMessage::make('other')->scope($other));
        InApp::send($user, InAppMessage::make('global'));

        $this->assertSame(3, $user->unreadInAppNotificationsCount());

        InApp::scopeUsing(fn () => $acme);

        $this->assertSame(2, $user->unreadInAppNotificationsCount());
        $this->assertSame(2, $user->unreadInAppNotificationsCount(null, $other));
        $this->assertSame(3, $user->unreadInAppNotificationsCount(null, null));

        $this->assertSame(2, $user->visibleInAppNotifications()->count());
        $this->assertSame(2, $user->visibleInAppNotifications($other)->count());
        $this->assertSame(3, $user->visibleInAppNotifications(null)->count());

        $this->assertSame(2, $user->markInAppNotificationsAsRead());

        // the plain relation is every row of the receiver
        $this->assertSame(1, $user->unreadInAppNotifications()->count());
    }

    public function test_broadcast_uses_the_custom_channel_with_the_notification_class(): void
    {
        config(['delivra.in_app.broadcast' => true]);
        $this->app->forgetInstance('delivra-in-app');
        Event::fake([BroadcastInAppNotificationCreated::class]);

        $user = BroadcastingUser::query()->create(['name' => 'Sara']);
        $user->notify(new MessageNotification(InAppMessage::make('x')));
        InApp::send($user, InAppMessage::make('y'));

        $channels = [];
        Event::assertDispatched(BroadcastInAppNotificationCreated::class, function (BroadcastInAppNotificationCreated $event) use (&$channels) {
            $channels[] = $event->broadcastOn()[0]->name;

            return true;
        });

        // the typed method needs a notification class, so InApp::send() falls back to the default name
        $this->assertSame(['private-users.custom', 'private-' . str_replace('\\', '.', BroadcastingUser::class) . '.' . $user->id], $channels);
    }

    public function test_trait_helpers_filter_by_category_and_skip_archived(): void
    {
        $user = $this->user();

        InApp::send($user, InAppMessage::make('a')->category('tasks'));
        InApp::send($user, InAppMessage::make('b')->category('tasks'))->archive();
        InApp::send($user, InAppMessage::make('c')->category('comments'));

        $this->assertSame(1, $user->unreadInAppNotificationsCount('tasks'));
        $this->assertSame(2, $user->unreadInAppNotificationsCount());
        $this->assertSame(2, $user->markInAppNotificationsAsRead('tasks'));
        $this->assertSame(1, $user->unreadInAppNotificationsCount());
    }

    public function test_model_state_actions(): void
    {
        $row = InApp::send($this->user(), InAppMessage::make('x'));

        $this->assertTrue($row->markAsRead()->isRead());
        $this->assertFalse($row->markAsUnread()->isRead());
        $this->assertTrue($row->archive()->isArchived());
        $this->assertFalse($row->unarchive()->isArchived());
        $this->assertNull($row->fresh()->read_at);
    }

    public function test_prune_removes_old_rows_only_when_configured(): void
    {
        $user = $this->user();
        $old  = InApp::send($user, InAppMessage::make('old'));
        $old->forceFill(['created_at' => now()->subDays(40)])->save();
        InApp::send($user, InAppMessage::make('new'));

        $this->artisan('model:prune', ['--model' => InAppNotification::class])->assertSuccessful();
        $this->assertSame(2, InAppNotification::query()->count());

        config(['delivra.in_app.prune_after_days' => 30]);

        $this->artisan('model:prune', ['--model' => InAppNotification::class])->assertSuccessful();
        $this->assertSame(['new'], InAppNotification::query()->get()->map(fn ($row) => $row->payload['title']['k'])->all());
    }

    public function test_the_fake_records_without_writing(): void
    {
        $fake = InApp::fake();
        $user = $this->user();

        $user->notify(new MessageNotification(InAppMessage::make('notifications.welcome.title')->category('auth')));

        $this->assertSame(0, InAppNotification::query()->count());
        $fake->assertSentCount(1)
            ->assertSentTo($user, fn (InAppMessage $message) => $message->getCategory() === 'auth')
            ->assertNotSentTo($this->user('Omar'));
    }

    public function test_the_fake_asserts_nothing_sent(): void
    {
        InApp::fake()->assertNothingSent();
    }
}
