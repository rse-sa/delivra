# In-app notifications for Delivra

Date: 2026-11-25. Status: built (see "Calls made during build").

## Intent

The owner builds many Laravel apps, and each one has its own in-app (bell) notification code. An audit of 25 apps in `D:/AppServ/www/C` found no app with more than one button per notification, and no app with per-button color, icon or params. Text is often stored as a fixed Arabic string, so the bell ignores the reader's locale.

Delivra gets one in-app notification module that every future app uses. Existing apps move to it later.

Parts taken from the audited apps:

- warqa: payload fields (icon, color, title key and params, body key and params, action, category), read-time `__()` rendering.
- lm-realestate: real morph columns for the related model and the causer, links stored as route name plus params and resolved when read.
- lm-hadaf: a version marker in the payload, raw dates and enum values formatted when read.
- lm-legal: grouping notifications into categories.

## Goals

- A fluent builder (`InAppMessage`) in the style of `WebPushMessage`, easy to read in a notification class.
- Text stored as translation keys plus params. It renders in the reader's current locale, every time it is read.
- Typed params that render when read: nested translation keys, plural choices, dates, datetimes.
- Icon and color on the notification.
- Any number of buttons. Each button has a label (translation key plus params), a URL or a route, an HTTP method, a color, an icon, extra params, and a flag that marks the notification as read on click.
- Extra free data, a subject morph (the related model), a causer morph (who did it), a scope morph (tenant), a category.
- Read, unread, archive, unarchive, delete, mark all as read, unread count.
- JSON routes for web UIs and mobile apps.
- An `InAppNotificationCreated` event, with optional broadcasting for real-time bells.
- A render hook (`InApp::renderUsing()`) for the rare type that needs custom text.
- `InApp::fake()` for tests, like `WebPush::fake()`.
- Prunable model. The app schedules `model:prune` itself.

## Non-goals

- No UI: no Blade, Livewire or Vue components. Each app draws its bell from the JSON or from the rendered array.
- No mute or preference settings. Laravel already covers this for every channel: the notification's `shouldSend($notifiable, $channel)` method, or a `NotificationSending` listener that returns `false`. The docs show both.
- No import command for old rows from existing apps in v1. The design keeps it possible (see literal text below).
- No scheduling. Delivra never schedules prune or anything else.

## Anti-goals

These make the module a failure even if it works:

- Overriding Laravel's global `database` channel. lm-legal did this and broke `$user->notifications` for every other package.
- Storing text rendered at send time on the normal path. The queue worker's locale then decides the text.
- App domain columns in the package table (`company_id`, `deed_id`). The scope morph covers tenants.
- A payload that drifts because nothing enforces it. The builder is the only way to write a row, and the payload carries a version.

## Constraints

- PHP 8.2, Laravel 10 and later, same as `composer.json`.
- Owner rules: no `final` classes, no `private` members, Laravel conventions, let exceptions surface.
- Same module layout as `src/WebPush`: `Channels`, `Concerns`, `Events`, `Messages`, `Models`, `Testing`, a facade in `src/Facades`, config under one key in `config/delivra.php`, a migration published from `stubs/`.
- Nothing sent returns `[]`, as on every Delivra channel (commit d9a2a46).

## Approaches considered

- **A. Message builder with typed params.** `toInApp()` returns an `InAppMessage`. The channel stores keys and raw params in versioned JSON. The model renders when read. Stored rows render even after the notification class is renamed or deleted.
- **B. The notification class renders itself.** Only a type and raw data are stored, and reading calls a static method on the class. Any logic is possible, but a renamed class breaks old rows, every class repeats rendering code, and the JSON routes depend on app classes.
- **C. A plus a render hook.** A, plus `InApp::renderUsing()` for custom text per type or category.

**Picked: A and C.** The owner chose both: A is the normal path, C is the escape hatch. Branching text normally picks a different key at send time (`body_with_meeting` versus `body`), since the key choice does not depend on locale.

## Decided by the owner

- Backend only, plus JSON routes.
- Own table, name in config, default `in_app_notifications`. lm-legal already has a table with that name and a different schema. Its future migration renames the old table first.
- v1 extras: real-time event, tenant scope column, archive and prune.
- Mute is a Laravel hook, not a Delivra feature.
- Prune is never scheduled by Delivra.
- Tenant scope rules, see "Tenant scope" below.
- No implementation plan document: the module is built inline from this spec.

## Calls made after the builder check

Marked as Claude's calls, for the owner to check:

- Broadcast channel default follows Laravel's `BroadcastNotificationCreated`: `str_replace('\\', '.', get_class($notifiable)) . '.' . $key`, for example `App.Models.User.5`. Apps reuse their existing `routes/channels.php` rule.
- Timezone: `InApp::date()` stores `Y-m-d` only, so a date never shifts by a day. `InApp::datetime()` stores UTC ISO 8601 and renders in the app timezone, or in the reader timezone from `InApp::timezoneUsing(fn ($notifiable) => ...)` when set. `render()` also takes an optional `$timezone`.
- Delivra shared events: the channel fires `DelivraMessageSending` (a listener returning `false` cancels the store, as on other channels) and `DelivraMessageSent`. Channel name `in_app`, recipient `{morph class}:{key}`, body is the title key (never rendered text), payload is the stored payload, response is the model.

## Design

### Namespace and files

`RSE\Delivra\InApp`:

- `Messages\InAppMessage`: the builder.
- `Messages\InAppButton`: one button.
- `Values\Text`, `Values\Trans`, `Values\Choice`, `Values\Date`: typed values for titles, bodies, labels and params. Each one serializes to a tagged array and renders itself.
- `Channels\InAppChannel`: stores the message.
- `Models\InAppNotification`: the Eloquent model.
- `Concerns\HasInAppNotifications`: trait for the notifiable model.
- `InAppRenderer`: turns a stored row into the rendered array in the current locale. Applies the `renderUsing` hooks.
- `InAppManager`: behind the `InApp` facade. Holds `renderUsing`, `scopeUsing`, `routes()`, `fake()` and the value helpers.
- `Http\Controllers\InAppNotificationController`, `Http\Resources\InAppNotificationResource`.
- `Events\InAppNotificationCreated`, `Events\BroadcastInAppNotificationCreated`.
- `Testing\InAppFake`.
- `routes/in-app.php`, `stubs/create_in_app_notifications_table.php.stub`, `docs/in-app.md`.

### The builder

```php
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
        ->scope($this->task->company)
        ->route('tasks.show', ['task' => $this->task])
        ->button('notifications.actions.open', fn (InAppButton $button) => $button
            ->route('tasks.show', ['task' => $this->task])
            ->color('primary')
            ->icon('fa-solid fa-eye'))
        ->button('notifications.actions.approve', fn (InAppButton $button) => $button
            ->url(route('tasks.approve', $this->task))
            ->post()
            ->color('success')
            ->params(['step' => 2])
            ->markAsRead())
        ->data(['priority' => 'high']);
}
```

Builder methods on `InAppMessage`:

- `make(string|Value $title = null, array $params = [])`, `title(string|Value $key, array $params = [])`, `body(string|Value $key, array $params = [])`.
- `icon(?string)`, `color(string|BackedEnum|null)`. Both are free strings, so each app keeps its own icon set and color names. The docs suggest `primary`, `success`, `warning`, `danger`, `info`, `neutral`.
- `category(?string)`.
- `subject(?Model)`, `causer(?Model)`, `scope(?Model)`.
- `url(?string)` or `route(string $name, array $params = [])`: the main link when the row is clicked. A route is stored as name plus params and resolved when read, so a domain change does not break old rows. Model params are stored as their route key.
- `button(string|Value $label, ?Closure $configure = null, array $params = [])`, and `buttons(InAppButton ...$buttons)`.
- `data(array)`: free data, merged on repeat calls. Values must be JSON-safe.
- `when()` and `unless()` from Laravel's `Conditionable` trait.

`InAppButton` methods: `url()`, `route()`, `method(string)`, shortcuts `post()`, `put()`, `patch()`, `delete()`, `color()`, `icon()`, `params(array)`, `markAsRead(bool = true)`, `key(string)` (a stable id the UI can use, default is the button's position).

The builder throws `InvalidMessageException` (under `src/Exceptions/InApp`) for a missing title, an unknown HTTP method, or a button with both `url` and `route`.

`toInApp()` returning `null` stores nothing, and the channel returns `[]`.

### Typed values

The facade gives short helpers:

- `InApp::trans(string $key, array $params = [])`: a translation key rendered in the reader's locale. Its params can be typed values too.
- `InApp::choice(string $key, int|float $count, array $params = [])`: `trans_choice()` when read.
- `InApp::date($value, ?string $format = null)`: stored as `Y-m-d`. `InApp::datetime($value, ?string $format = null)`: stored as UTC ISO 8601, rendered in the reader timezone (see the timezone call above). Both render with Carbon `translatedFormat()` in the reader's locale. Default formats come from config.
- `InApp::text(string $text)`: literal text, never translated. It exists for imported legacy rows and for text the app already has in one language. The docs say it ignores the locale.

A plain string or number param is stored as it is, which fits names and serial numbers. A `BackedEnum` param is stored as its value. Stored shape of a typed value: `{"t": "trans", "k": "tasks.status.open", "p": {}}`, `{"t": "date", "v": "2026-11-25T10:00:00+03:00", "f": null}`.

### Storage

Migration stub `create_in_app_notifications_table.php.stub`, published with tag `delivra-in-app-migrations`:

| Column | Type | Note |
| --- | --- | --- |
| `id` | ulid, primary | |
| `notifiable_type`, `notifiable_id` | morph | who receives it |
| `scope_type`, `scope_id` | nullable morph | tenant, team, company |
| `subject_type`, `subject_id` | nullable morph | the related model |
| `causer_type`, `causer_id` | nullable morph | who caused it |
| `type` | string | the notification class, like Laravel's table |
| `category` | nullable string, indexed | |
| `payload` | json | versioned, see below |
| `read_at` | nullable timestamp | |
| `archived_at` | nullable timestamp | |
| `created_at`, `updated_at` | timestamps | |

Indexes: `(notifiable_type, notifiable_id, archived_at, read_at)` for the bell, `category`, the scope morph, the subject morph.

Morph key type follows the WebPush pattern: config `morph_key` (`int`, `uuid`, `ulid`), null follows `Schema::morphUsingUuids()` or `morphUsingUlids()`.

Payload v1:

```json
{
  "v": 1,
  "title": {"t": "trans", "k": "notifications.task.assigned.title", "p": {"task": "Fix roof"}},
  "body": {"t": "trans", "k": "notifications.task.assigned.body", "p": {"due": {"t": "date", "v": "2026-11-30T00:00:00+03:00", "f": null}}},
  "icon": "fa-solid fa-list-check",
  "color": "warning",
  "link": {"route": "tasks.show", "params": {"task": "01J..."}},
  "buttons": [
    {"key": "0", "label": {"t": "trans", "k": "notifications.actions.open", "p": {}}, "link": {"route": "tasks.show", "params": {"task": "01J..."}}, "method": "GET", "color": "primary", "icon": "fa-solid fa-eye", "params": {}, "mark_read": false}
  ],
  "data": {"priority": "high"}
}
```

### Model

`InAppNotification`:

- Relations: `notifiable()`, `scope()` (method name `scopeable()` if `scope` clashes with Eloquent, builder's call), `subject()`, `causer()`.
- Query scopes: `unread()`, `read()`, `archived()`, `withoutArchived()`, `category(string|array)`, `forScope(Model)`, `forSubject(Model)`.
- Actions: `markAsRead()`, `markAsUnread()`, `archive()`, `unarchive()`. Each one is a no-op when already in that state.
- `render(?string $locale = null): array` through `InAppRenderer`.
- `MassPrunable`: `prunable()` returns rows older than config `prune_after_days` (null disables). The app schedules `model:prune`.
- Table, connection and model class come from config, so an app can extend the model.

### Notifiable trait

`HasInAppNotifications`:

- `inAppNotifications()` (morphMany, newest first), `unreadInAppNotifications()`.
- `unreadInAppNotificationsCount(?string $category = null)`.
- `markInAppNotificationsAsRead(?string $category = null)`.

### Rendering

`InAppRenderer::render(InAppNotification $notification, ?string $locale = null): array` returns:

```json
{
  "id": "01J...",
  "type": "App\\Notifications\\TaskAssigned",
  "category": "tasks",
  "title": "Task assigned: Fix roof",
  "body": "Due on 30 November 2026",
  "icon": "fa-solid fa-list-check",
  "color": "warning",
  "url": "https://app.test/tasks/01J...",
  "buttons": [{"key": "0", "label": "Open", "url": "https://app.test/tasks/01J...", "method": "GET", "color": "primary", "icon": "fa-solid fa-eye", "params": {}, "mark_read": false}],
  "data": {"priority": "high"},
  "subject": {"type": "task", "id": "01J..."},
  "causer": {"type": "user", "id": "01J..."},
  "read_at": null,
  "archived_at": null,
  "created_at": "2026-11-25T10:00:00+03:00"
}
```

Rules:

- When `$locale` is given, the renderer uses it for this call only and restores the old locale after.
- A missing translation key renders as the key, like `__()`.
- A route that no longer exists renders `url: null`. It does not throw, so one bad row cannot break the bell.
- An unknown payload version throws. Versions are only added by Delivra.
- After the default render, hooks run: `InApp::renderUsing(string $typeOrCategory, Closure $hook)`. The hook gets the model and the rendered array and returns the array. A hook for the exact `type` runs before a hook for the `category`.

### Channel

`InAppChannel::send($notifiable, $notification)`:

1. Calls `$notification->toInApp($notifiable)`. `null` returns `[]`.
2. Picks the scope as described under "Tenant scope".
3. Fires `DelivraMessageSending`. A listener returning `false` stops here and the channel returns `[]`.
4. Creates the row through `$notifiable->inAppNotifications()` when the notifiable uses the trait, otherwise through the model with the morph set.
5. Fires `InAppNotificationCreated` and `DelivraMessageSent`, and dispatches the broadcast event when enabled.
6. Returns the model.

Registered as channel `in_app` (config `channel`, null skips it). `via()` can also use `InAppChannel::class`.

### Tenant scope

Decided by the owner.

- One resolver: `InApp::scopeUsing(fn (object $notifiable, ?Notification $notification) => ?Model)`. At send time it gets the notification. At read time it gets `null`.
- Send: an explicit `->scope($model)` wins. `->withoutScope()` stores no scope and skips the resolver. Otherwise the resolver picks the scope, for example the user's current company.
- Read (JSON routes, `unreadInAppNotificationsCount()`, `markInAppNotificationsAsRead()`, the `/unread-count` breakdown): the user sees rows of the current scope plus rows with no scope (system-wide notices).
- When the resolver is not set or returns `null`, no scope filter applies.
- The trait helpers take an optional `?Model $scope` argument. When it is omitted they use the resolver. The model has `visibleInScope(?Model $scope)` for this rule.

### Events and real-time

- `InAppNotificationCreated` is a plain event with the model. Apps listen to it for any reason.
- When config `broadcast` is true, the channel also dispatches `BroadcastInAppNotificationCreated`, which implements `ShouldBroadcast`. It broadcasts on the notifiable's `receivesBroadcastNotificationsOn()` channel if the method exists, otherwise on a private channel named like Laravel's default (`App.Models.User.5`). The payload is the rendered array in the notifiable's preferred locale (`HasLocalePreference`) or the app locale. The event name is `in-app-notification.created`.
- The broadcast is queued after commit.

### JSON routes

Off by default. Config `routes.enabled`, `routes.prefix` (default `in-app-notifications`), `routes.middleware` (default `['api', 'auth:sanctum']`), `routes.name` (default `delivra.in-app.`). Apps that need two groups (web session and mobile token) call `InApp::routes(['middleware' => [...], 'prefix' => ...])` from their own route files.

| Method | Path | Action |
| --- | --- | --- |
| GET | `/` | paginated list, rendered. Filters: `status=unread\|read\|all`, `category` (one or many), `archived=0\|1`, `per_page` (max from config, default 50) |
| GET | `/unread-count` | `{"count": 3, "categories": {"tasks": 2, "comments": 1}}` |
| POST | `/read-all` | optional `category` |
| POST | `/{id}/read` | |
| POST | `/{id}/unread` | |
| POST | `/{id}/archive` | |
| POST | `/{id}/unarchive` | |
| DELETE | `/{id}` | |

Every query goes through `$request->user()->inAppNotifications()`, so a row of another user returns 404. When an `InApp::scopeUsing()` resolver is set, queries also filter by the resolved scope. Writes return the rendered row, except delete and read-all, which return 204. The list uses `InAppNotificationResource`, which the app can swap through config.

### Testing fake

`InApp::fake()` swaps the channel's storage for a recorder. Nothing is written to the database.

- `InApp::assertSentTo($notifiable, ?Closure $callback = null)`: the callback gets the `InAppMessage`.
- `InApp::assertNotSentTo($notifiable)`, `InApp::assertNothingSent()`, `InApp::assertSentCount(int)`.
- `InAppMessage::toArray()` exposes the payload so tests can check fields.

### Config

New key `delivra.in_app`:

```php
'in_app' => [
    'channel'          => env('DELIVRA_IN_APP_CHANNEL', 'in_app'),
    'model'            => RSE\Delivra\InApp\Models\InAppNotification::class,
    'table'            => 'in_app_notifications',
    'connection'       => null,
    'morph_key'        => env('DELIVRA_IN_APP_MORPH_KEY'),
    'date_format'      => 'j F Y',
    'datetime_format'  => 'j F Y, g:i a',
    'prune_after_days' => null,
    'broadcast'        => env('DELIVRA_IN_APP_BROADCAST', false),
    'resource'         => RSE\Delivra\InApp\Http\Resources\InAppNotificationResource::class,
    'routes' => [
        'enabled'    => env('DELIVRA_IN_APP_ROUTES', false),
        'prefix'     => 'in-app-notifications',
        'middleware' => ['api', 'auth:sanctum'],
        'name'       => 'delivra.in-app.',
        'max_per_page' => 50,
    ],
],
```

### Docs and tests

- `docs/in-app.md` and a short README section, in the style of `docs/webpush.md`. They cover the builder, typed values, the render hook, routes, broadcasting, muting with `shouldSend()` or `NotificationSending`, and pruning.
- Testbench tests: builder and validation, payload shape, rendering in two locales (en and ar), typed values, route links (including a removed route), render hooks, channel storage with and without the trait, scope resolver, events and broadcast, every JSON route including ownership 404 and filters, prune, the fake.

## Calls made during build

- `subject`, `causer` and `scope` id columns are strings, so they take int, uuid and ulid models. `morph_key` sets only the `notifiable` columns.
- No `sendUsing()` on this channel: it has no route object to hand over. `InApp::send($notifiable, $message)` is the direct send, so `type` is nullable.
- The renderer never switches the app locale. It passes the locale to the translator and Carbon. Render hooks get `$locale` as a third argument.
- `render()` returns PHP arrays for `data` and button `params`. The resource and the broadcast use `InAppRenderer::forJson()` so empty values are `{}`.
- The resource always wraps a single row in `data`, because the rendered row has its own `data` key.
- `model:prune` needs `--model=` because the model is outside `app/Models`. The docs show the schedule line.
- The scope relation is `scopeable()`.
- One render hook per type or category. Registering again replaces it, so a provider booted once per test does not stack hooks. `renderUsing($key, null)` removes it.
- The trait has `visibleInAppNotifications(?Model $scope)`, the scoped query for bells. The plain relations stay unscoped and the docs say so.
- The broadcast passes the source notification to `receivesBroadcastNotificationsOn()`, like Laravel. For `InApp::send()` rows (no notification) a method whose parameter cannot take null is skipped, and the default channel is used.
- The list route uses length-aware pagination, 20 per page by default.

## Left to the builder

- Internal class layout beyond the files named above, and the names of protected helpers.
- Whether `InAppMessage` and `InAppButton` share a trait for the link logic.
- The relation name for the scope morph if `scope()` clashes with Eloquent.
- Exact pagination type (length-aware or simple) for the list route.
- Exact exception messages.
