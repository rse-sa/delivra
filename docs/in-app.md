# In-app notifications

Delivra stores bell notifications in its own table. A notification stores translation keys and raw params, not finished text. Each read renders the text in the reader's locale and timezone. An Arabic user and an English user see the same row in their own language.

Delivra ships no UI. Your app draws the bell from the rendered array, through the JSON routes or from Blade, Livewire or Vue.

## Setup

Publish and run the migration:

```bash
php artisan vendor:publish --tag=delivra-in-app-migrations
php artisan migrate
```

Add the trait to the model that receives notifications:

```php
use RSE\Delivra\InApp\Concerns\HasInAppNotifications;

class User extends Authenticatable
{
    use HasInAppNotifications, Notifiable;
}
```

The trait is optional for sending. The channel stores rows for any Eloquent notifiable. The trait adds the relations and the helpers below.

## Configuration

All options are under `delivra.in_app` in `config/delivra.php`.

| Option | Default | Use |
| --- | --- | --- |
| `channel` | `in_app` | Channel name for `via()`. Null skips registration. |
| `model` | `InAppNotification::class` | Extend the model and set your class here. |
| `table` | `in_app_notifications` | Table name. |
| `connection` | `null` | Database connection. |
| `morph_key` | `null` | Key type of the receivers: `int`, `uuid` or `ulid`. Null follows `Schema::morphUsingUuids()` or `morphUsingUlids()`. |
| `date_format` | `j F Y` | Format for `InApp::date()`. |
| `datetime_format` | `j F Y, g:i a` | Format for `InApp::datetime()`. |
| `prune_after_days` | `null` | Age at which `model:prune` deletes rows. Null keeps every row. |
| `broadcast` | `false` | Broadcast each new row to the receiver. |
| `resource` | `InAppNotificationResource::class` | JSON shape of one row in the routes. |
| `routes.*` | off | See [JSON routes](#json-routes). |

`morph_key` sets only the receiver columns. The subject, causer and scope columns hold string ids, so they take models with int, uuid or ulid keys.

## Sending

Add `in_app` to `via()` and return an `InAppMessage` from `toInApp()`:

```php
use RSE\Delivra\Facades\InApp;
use RSE\Delivra\InApp\Messages\InAppButton;
use RSE\Delivra\InApp\Messages\InAppMessage;

class TaskAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Task $task, public User $by) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'in_app'];
    }

    public function toInApp(User $user): ?InAppMessage
    {
        return InAppMessage::make('notifications.task.assigned.title', ['task' => $this->task->title])
            ->body('notifications.task.assigned.body', [
                'by'     => $this->by->name,
                'due'    => InApp::date($this->task->due_at),
                'status' => InApp::trans('tasks.status.' . $this->task->status->value),
                'files'  => InApp::choice('notifications.files', $this->task->files_count),
            ])
            ->icon('fa-solid fa-list-check')
            ->color('warning')
            ->category('tasks')
            ->subject($this->task)
            ->causer($this->by)
            ->route('tasks.show', ['task' => $this->task])
            ->button('notifications.actions.open', fn (InAppButton $button) => $button
                ->route('tasks.show', ['task' => $this->task])
                ->color('primary')
                ->icon('fa-solid fa-eye'))
            ->button('notifications.actions.approve', fn (InAppButton $button) => $button
                ->route('tasks.approve', ['task' => $this->task])
                ->post()
                ->color('success')
                ->params(['step' => 2])
                ->markAsRead())
            ->data(['priority' => 'high']);
    }
}
```

When `toInApp()` returns `null`, nothing is stored and the channel returns `[]`. An on-demand notifiable (`Notification::route()`) has no row to own a notification, so it is skipped too.

To store a message without a notification class:

```php
InApp::send($user, InAppMessage::make('notifications.welcome.title', ['name' => $user->name]));
```

`InApp::send()` returns the stored model, or `null` when nothing was stored.

## Messages

| Method | Use |
| --- | --- |
| `make($title, $params)`, `title($key, $params)` | Required. A translation key with its params, or a value such as `InApp::text()`. |
| `body($key, $params)` | Optional second line. |
| `icon($icon)` | Any string: a Font Awesome class, a Heroicon name, an image URL. |
| `color($color)` | Any string or backed enum. Suggested names: `primary`, `success`, `warning`, `danger`, `info`, `neutral`. |
| `category($category)` | Groups rows for filters and counts. String or backed enum. |
| `subject($model)` | The model the notification is about. |
| `causer($model)` | Who caused it. |
| `scope($model)`, `withoutScope()` | The tenant. See [Tenant scope](#tenant-scope). |
| `url($url)`, `route($name, $params)` | The main link, opened when the row is clicked. |
| `button($label, $configure, $labelParams)`, `buttons(...)` | Buttons, see below. |
| `data($array)` | Free data for the UI. Repeated calls merge. JSON-safe values only. |
| `when()`, `unless()` | From Laravel's `Conditionable`. |

`route()` stores the route name and params, not the URL. The URL is built on each read, so a new domain does not break old rows. Model params are stored as their route key.

The builder throws `InvalidMessageException` when the title is missing, when a link has both `url()` and `route()`, when a button method is unknown, or when two buttons share a key.

### Buttons

```php
->button('notifications.actions.reject', fn (InAppButton $button) => $button
    ->route('tasks.reject', ['task' => $this->task])
    ->delete()
    ->color('danger')
    ->icon('fa-solid fa-xmark')
    ->params(['reason' => 'late'])
    ->markAsRead()
    ->key('reject'))
```

| Method | Use |
| --- | --- |
| `url()`, `route()` | The button link. |
| `method()`, `get()`, `post()`, `put()`, `patch()`, `delete()` | HTTP method for the UI to use. Default `GET`. |
| `color()`, `icon()` | Same as on the message. |
| `params($array)` | Data the UI sends with the request, for example a form body. |
| `markAsRead()` | Tells the UI to mark the row as read on click. |
| `key($key)` | A stable id for the UI. Defaults to the button's position. |

A button can also be built on its own and passed in: `->buttons(InAppButton::make('notifications.actions.open')->url('/inbox'))`.

The button label is translated like the title. Delivra does not call the button link. The UI decides what to do with the method and params.

## Values that render when read

A plain string or number param is stored as it is. Use it for names, titles and serial numbers. Use these helpers for params that change with the reader:

| Helper | Renders as |
| --- | --- |
| `InApp::trans($key, $params)` | A translation in the reader's locale. Its params can be values too. |
| `InApp::choice($key, $count, $params)` | `trans_choice()` in the reader's locale. |
| `InApp::date($value, $format)` | A date in the reader's locale. Stored as `Y-m-d`, so it never moves a day between timezones. `null` renders as an empty string. |
| `InApp::datetime($value, $format)` | A date and time in the reader's locale and timezone. Stored in UTC. |
| `InApp::text($text)` | Literal text, the same in every locale. For imported rows or text the app has in one language only. |
| `InApp::translations(['ar' => ..., 'en' => ...])` | Text the app holds per locale. Renders the reader's locale, then `app.fallback_locale`, then the first text. |
| `InApp::translated($model, 'name')` | Every translation of a translatable attribute (`getTranslations()` from spatie/laravel-translatable). A plain attribute becomes `InApp::text()`. |

`translated()` copies the translations when the notification is sent. A workflow renamed later keeps its old name in old rows, and a deleted one does not break them.

```php
->body('notifications.workflow.pending', [
    'workflow' => InApp::translated($instance->dynaflow, 'name'),
    'step'     => InApp::translated($step, 'name'),
])
```

A backed enum param is stored as its value. A `DateTimeInterface` param is stored as a datetime.

Branching text picks a key when sending, for example `body_with_meeting` or `body`. The choice of key does not depend on the reader's locale, so it is safe at send time.

### Reader timezone

Datetimes render in the app timezone. To use each reader's own timezone:

```php
// AppServiceProvider::boot()
InApp::timezoneUsing(fn (User $user) => $user->timezone);
```

A `null` from the resolver falls back to the app timezone.

## Reading

```php
$user->visibleInAppNotifications()->withoutArchived()->paginate(20);   // the bell: current tenant scope, newest first
$user->inAppNotifications;              // every row of the user, in every tenant scope
$user->unreadInAppNotifications;        // every unread row, in every tenant scope
$user->unreadInAppNotificationsCount();          // unread, not archived, current tenant scope
$user->unreadInAppNotificationsCount('tasks');
$user->markInAppNotificationsAsRead();           // returns how many changed
$user->markInAppNotificationsAsRead('tasks');
```

The rendered array of one row:

```php
$notification->render();                 // app locale
$notification->render('ar');             // this locale, the app locale stays as it is
$notification->render('ar', 'Asia/Riyadh');
```

```json
{
  "id": "01JD...",
  "type": "App\\Notifications\\TaskAssigned",
  "category": "tasks",
  "title": "Task assigned: Fix roof",
  "body": "Due on 30 November 2026",
  "icon": "fa-solid fa-list-check",
  "color": "warning",
  "url": "https://app.test/tasks/5",
  "buttons": [
    {"key": "0", "label": "Open", "url": "https://app.test/tasks/5", "method": "GET", "color": "primary", "icon": "fa-solid fa-eye", "params": {}, "mark_read": false}
  ],
  "data": {"priority": "high"},
  "subject": {"type": "App\\Models\\Task", "id": "5"},
  "causer": {"type": "App\\Models\\User", "id": "2"},
  "read_at": null,
  "archived_at": null,
  "created_at": "2026-11-25T10:00:00+00:00"
}
```

With `InApp::timezoneUsing()` set, `render()` loads the receiver of each row to find its timezone. For a list, pass the timezone once:

```php
$timezone = InAppManager::resolveTimezone($user);

$rows = $user->visibleInAppNotifications()->paginate(20)
    ->through(fn ($notification) => $notification->render(null, $timezone));
```

`render()` returns `data` and button `params` as PHP arrays. The JSON routes and the broadcast send them as objects, so an empty value is `{}`. Use `InAppRenderer::forJson($rendered)` for the same shape in your own JSON.

A link to a removed route, or a route with a missing param, renders `url: null`. One old row cannot break the bell. A missing translation key renders as the key, like `__()`.

### Model actions and scopes

```php
$notification->markAsRead();
$notification->markAsUnread();
$notification->archive();
$notification->unarchive();
$notification->isRead();
$notification->isArchived();

InAppNotification::query()->unread()->withoutArchived()->category(['tasks', 'comments'])->get();
InAppNotification::query()->forSubject($task)->delete();   // when the task is deleted
```

Each row keeps Laravel's notification id in `notification_id`. Every channel of one send gets the same id, so a phone inbox entry can find its bell row: `$user->inAppNotifications()->where('notification_id', $id)`. Rows from `InApp::send()` have no id.

Query scopes: `unread()`, `read()`, `archived()`, `withoutArchived()`, `category()`, `forScope($model)`, `forSubject($model)`, `visibleInScope($model)`. Relations: `notifiable`, `subject`, `causer`, `scopeable`.

## Custom text with render hooks

A hook changes the rendered array of one notification class or one category. It gets the model, the rendered array and the locale, and returns the array:

```php
// AppServiceProvider::boot()
InApp::renderUsing(MeetingReminder::class, function (InAppNotification $notification, array $rendered, string $locale) {
    $rendered['body'] .= ' - ' . trans('meetings.online', locale: $locale);

    return $rendered;
});

InApp::renderUsing('tasks', fn ($notification, array $rendered) => ['icon' => 'fa-solid fa-list-check'] + $rendered);
```

Each class or category has one hook. Registering it again replaces it, and `InApp::renderUsing('tasks', null)` removes it. The hook for the class runs before the hook for the category. Use hooks only when a key and params cannot express the text.

## Tenant scope

Multi-tenant apps tie each row to a tenant model, such as a company or a team.

```php
// AppServiceProvider::boot()
InApp::scopeUsing(fn (User $user, ?Notification $notification) => $user->currentCompany);
```

When sending:

- `->scope($company)` on the message wins.
- `->withoutScope()` stores a system-wide row and skips the resolver.
- Otherwise the resolver picks the scope. It gets the notification as the second argument.

When reading (JSON routes, `visibleInAppNotifications()`, `unreadInAppNotificationsCount()`, `markInAppNotificationsAsRead()`), the user sees rows of the current scope plus system-wide rows. The resolver gets `null` as the notification here.

The relations `inAppNotifications` and `unreadInAppNotifications` are not filtered by scope. Build a tenant bell from `visibleInAppNotifications()`, or add `->visibleInScope($company)` to your own query.

When the resolver is not set or returns `null`, no scope filter applies. The helpers take an optional scope: `$user->visibleInAppNotifications($company)` and `$user->unreadInAppNotificationsCount(null, $company)`. Pass `null` to skip the filter.

## JSON routes

The routes are off by default. Turn them on in `.env`:

```dotenv
DELIVRA_IN_APP_ROUTES=true
```

They use `routes.prefix` (`in-app-notifications`), `routes.middleware` (`['api', 'auth:sanctum']`) and `routes.name` (`delivra.in-app.`). An app that needs two groups, such as a web session group and a mobile token group, leaves them off and registers them in its own route files:

```php
// routes/web.php
InApp::routes(['middleware' => ['web', 'auth'], 'prefix' => 'notifications', 'name' => 'notifications.']);

// routes/api.php
InApp::routes(['middleware' => ['auth:sanctum'], 'prefix' => 'v1/notifications', 'name' => 'api.notifications.']);
```

| Method | Path | Result |
| --- | --- | --- |
| GET | `/` | Paginated rendered rows. Filters: `status=unread\|read\|all`, `category` (one or many), `archived=0\|1`, `per_page` (max `routes.max_per_page`). Archived rows are hidden unless `archived=1`. |
| GET | `/unread-count` | `{"count": 3, "categories": {"tasks": 2, "comments": 1}}`. Rows without a category count in `count` only. |
| POST | `/read-all` | Optional `category`. 204. |
| POST | `/{id}/read` | The rendered row. |
| POST | `/{id}/unread` | The rendered row. |
| POST | `/{id}/archive` | The rendered row. |
| POST | `/{id}/unarchive` | The rendered row. |
| DELETE | `/{id}` | 204. |

Every query starts from the signed-in user and the current tenant scope. Another user's row returns 404. Text renders in the request locale, so your locale middleware decides the language.

To add fields, extend `InAppNotificationResource` and set it in `resource`.

## Real-time

Each new row fires `InAppNotificationCreated` with the model, the notifiable, the notification and the message. Listen to it for anything.

With `broadcast` on, Delivra also broadcasts `BroadcastInAppNotificationCreated` after the database transaction commits:

- Channel: the notifiable's `receivesBroadcastNotificationsOn($notification)`, or Laravel's default private channel (`App.Models.User.5`). Apps that already broadcast Laravel notifications reuse their `routes/channels.php` rule. A row from `InApp::send()` has no notification class. If the method's parameter cannot take `null`, that row goes to the default channel.
- Event name: `in-app-notification.created`.
- Payload: the rendered array in the notifiable's preferred locale (`HasLocalePreference`), or the app locale.

```js
Echo.private(`App.Models.User.${userId}`)
    .listen('.in-app-notification.created', (notification) => bell.prepend(notification));
```

## Muting

Delivra has no mute settings of its own. Laravel already has two ways, and they work for every channel:

```php
// in the notification
public function shouldSend(object $notifiable, string $channel): bool
{
    return ! ($channel === 'in_app' && $notifiable->muted('tasks'));
}
```

```php
// a listener for all notifications
Event::listen(function (NotificationSending $event) {
    return ! $event->notifiable->hasMuted($event->notification::class, $event->channel);
});
```

Delivra's own `DelivraMessageSending` event works too: a listener that returns `false` cancels the row.

## Send events

Like every Delivra channel, the in-app channel fires `DelivraMessageSending` before storing and `DelivraMessageSent` after. The channel name is `in_app`, the recipient is `{morph class}:{key}`, and the body is the title key (or, for a title without a key, the title rendered in the app locale). The sent event's response is the stored model.

## Pruning

Set `prune_after_days`, then schedule Laravel's prune command. The model lives outside `app/Models`, so name it:

```php
// routes/console.php
Schedule::command('model:prune', ['--model' => [\RSE\Delivra\InApp\Models\InAppNotification::class]])->daily();
```

Delivra schedules nothing itself.

## Testing

```php
use RSE\Delivra\Facades\InApp;

$fake = InApp::fake();

$user->notify(new TaskAssigned($task, $manager));

$fake->assertSentTo($user, fn (InAppMessage $message) => $message->getCategory() === 'tasks');
$fake->assertNotSentTo($manager);
$fake->assertSentCount(1);
InApp::fake()->assertNothingSent();
```

The fake writes nothing to the database. Hooks set with `renderUsing()`, `scopeUsing()` and `timezoneUsing()` stay in place. `$message->toArray()` returns the payload to check fields.
