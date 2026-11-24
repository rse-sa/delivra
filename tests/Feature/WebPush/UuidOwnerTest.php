<?php

namespace RSE\Delivra\Tests\Feature\WebPush;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use RSE\Delivra\Facades\WebPush;
use RSE\Delivra\WebPush\Concerns\HasPushSubscriptions;

class UuidOwnerTest extends WebPushTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('delivra.webpush.subscriptions.morph_key', 'uuid');
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
        });

        $migration = require __DIR__ . '/../../../stubs/create_push_subscriptions_table.php.stub';
        $migration->up();
    }

    public function test_the_stub_takes_uuid_owners(): void
    {
        $this->assertSame('varchar', Schema::getColumnType('push_subscriptions', 'subscribable_id'));

        $fake  = WebPush::fake();
        $user  = UuidPushUser::query()->create(['name' => 'Sara']);
        $other = UuidPushUser::query()->create(['name' => 'Omar']);
        $user->updatePushSubscription('https://push.example.com/send/uuid', 'key', 'token');

        $this->assertSame($user->id, $user->pushSubscriptions()->sole()->subscribable_id);
        $this->assertCount(0, $other->routeNotificationForWebPush());

        $user->notify(new RequestWaiting);

        $fake->assertSentTo('https://push.example.com/send/uuid');
    }
}

class UuidPushUser extends Model
{
    use HasPushSubscriptions;
    use HasUuids;
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;
}
