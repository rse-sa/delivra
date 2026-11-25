<?php

namespace RSE\Delivra\Tests\Feature\InApp;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use RSE\Delivra\Facades\InApp;
use RSE\Delivra\InApp\Concerns\HasInAppNotifications;
use RSE\Delivra\InApp\Messages\InAppMessage;

class UlidReceiverTest extends InAppTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('delivra.in_app.morph_key', 'ulid');
        $app['config']->set('delivra.in_app.routes.enabled', true);
        $app['config']->set('delivra.in_app.routes.middleware', []);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('ulid_users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
        });

        $migration = require __DIR__ . '/../../../stubs/create_in_app_notifications_table.php.stub';
        $migration->up();
    }

    public function test_the_stub_takes_ulid_receivers(): void
    {
        $user  = UlidUser::query()->create(['name' => 'Sara']);
        $other = UlidUser::query()->create(['name' => 'Omar']);
        $task  = Task::query()->create(['title' => 'Roof']);

        $user->notify(new MessageNotification(InAppMessage::make('notifications.welcome.title', ['name' => 'Sara'])->subject($task)));

        $this->assertSame(1, $user->unreadInAppNotificationsCount());
        $this->assertSame(0, $other->unreadInAppNotificationsCount());
        $this->assertTrue($user->inAppNotifications()->sole()->subject->is($task));

        $this->actingAs($user)
            ->getJson('in-app-notifications')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Welcome Sara');

        $fake = InApp::fake();
        $user->notify(new MessageNotification(InAppMessage::make('x')));
        $fake->assertSentTo($user)->assertNotSentTo($other);
    }
}

class UlidUser extends User
{
    use HasInAppNotifications;
    use HasUlids;
    use Notifiable;

    protected $table = 'ulid_users';

    protected $guarded = [];

    public $timestamps = false;
}
