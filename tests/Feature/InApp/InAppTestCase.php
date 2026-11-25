<?php

namespace RSE\Delivra\Tests\Feature\InApp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use RSE\Delivra\InApp\Concerns\HasInAppNotifications;
use RSE\Delivra\InApp\InAppManager;
use RSE\Delivra\InApp\Messages\InAppMessage;
use RSE\Delivra\Tests\TestCase;

abstract class InAppTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('app.timezone', 'UTC');
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('timezone')->nullable();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
        });

        // the published stub, so it is tested too
        $migration = require __DIR__ . '/../../../stubs/create_in_app_notifications_table.php.stub';
        $migration->up();
    }

    protected function defineRoutes($router): void
    {
        $router->get('tasks/{task}', fn () => 'task')->name('tasks.show');
    }

    protected function setUp(): void
    {
        parent::setUp();

        InAppManager::flushHooks();

        $translator = $this->app['translator'];

        $translator->addLines([
            'notifications.task.title'    => 'Task :task assigned',
            'notifications.task.body'     => 'Due :due, status :status, :files',
            'notifications.actions.open'  => 'Open',
            'notifications.files'         => '{0} no files|{1} one file|[2,*] :count files',
            'tasks.status.open'           => 'open',
            'notifications.welcome.title' => 'Welcome :name',
        ], 'en');

        $translator->addLines([
            'notifications.task.title'    => 'تم إسناد المهمة :task',
            'notifications.task.body'     => 'تاريخ الاستحقاق :due، الحالة :status، :files',
            'notifications.actions.open'  => 'فتح',
            'notifications.files'         => '{0} لا توجد ملفات|{1} ملف واحد|[2,*] :count ملفات',
            'tasks.status.open'           => 'مفتوحة',
            'notifications.welcome.title' => 'مرحبًا :name',
        ], 'ar');
    }

    protected function tearDown(): void
    {
        InAppManager::flushHooks();

        parent::tearDown();
    }

    protected function user(string $name = 'Sara'): InAppUser
    {
        return InAppUser::query()->create(['name' => $name]);
    }
}

class InAppUser extends \Illuminate\Foundation\Auth\User
{
    use HasInAppNotifications;
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;
}

class BroadcastingUser extends InAppUser
{
    public function receivesBroadcastNotificationsOn(Notification $notification): string
    {
        return 'users.custom';
    }
}

class InAppPlainUser extends Model
{
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;
}

class Company extends Model
{
    use \Illuminate\Database\Eloquent\Concerns\HasUlids;

    protected $guarded = [];

    public $timestamps = false;
}

class Task extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}

class MessageNotification extends Notification
{
    public function __construct(public ?InAppMessage $message) {}

    public function via(object $notifiable): array
    {
        return ['in_app'];
    }

    public function toInApp(object $notifiable): ?InAppMessage
    {
        return $this->message;
    }
}
