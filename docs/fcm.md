# Firebase Cloud Messaging (FCM)

Delivra sends push notifications to Android, iOS and web apps through the FCM HTTP v1 API. It talks to Google directly with the service account key. It needs no Firebase SDK and no extra Composer package.

- [Requirements](#requirements)
- [Configuration](#configuration)
- [Sending](#sending)
- [Messages](#messages)
- [Platform options](#platform-options)
- [Laravel notifications](#laravel-notifications)
- [Results](#results)
- [Invalid tokens](#invalid-tokens)
- [Errors](#errors)
- [Topics](#topics)
- [Send events](#send-events)
- [Web: service worker](#web-service-worker)
- [Testing](#testing)
- [Artisan command](#artisan-command)
- [Not supported yet](#not-supported-yet)

## Requirements

- PHP 8.2 or newer with the `openssl` extension, Laravel 10 or newer.
- A Firebase project with Cloud Messaging enabled.
- A service account key. In the Firebase console, open **Project settings**, then **Service accounts**, then **Generate new private key**.
- For iOS: an APNs auth key uploaded in **Project settings**, **Cloud Messaging**.

Delivra stores no device tokens. Your app keeps them in its own table and gives them to Delivra when it sends.

## Configuration

Set the path to the service account JSON file:

```dotenv
DELIVRA_FCM_CREDENTIALS=storage/app/firebase.json
```

Or set the three values, when you keep secrets in `.env` only:

```dotenv
DELIVRA_FCM_PROJECT_ID=my-app
DELIVRA_FCM_CLIENT_EMAIL=firebase-adminsdk-x@my-app.iam.gserviceaccount.com
DELIVRA_FCM_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\nMIIE...\n-----END PRIVATE KEY-----\n"
```

Delivra turns the literal `\n` in the key into line breaks. `credentials` also accepts the JSON text itself. A relative path resolves from the app root.

### Many projects

Each entry under `projects` is one Firebase project:

```php
'fcm' => [
    'default' => 'default',

    'projects' => [
        'default'   => ['credentials' => env('DELIVRA_FCM_CREDENTIALS')],
        'customers' => ['credentials' => env('CUSTOMERS_FCM_CREDENTIALS')],
    ],
],
```

```php
fcm()->project('customers')->to($token)->send($message);
```

### Options

| Key | Default | Purpose |
|---|---|---|
| `default` | `default` | Project used when none is given. |
| `channel` | `fcm` | Notification channel name. `null` registers no name. See [Laravel notifications](#laravel-notifications). |
| `concurrency` | `50` | Parallel requests per chunk of tokens. |
| `retry_max_delay` | `5` | Maximum seconds to wait before the one retry of a transient failure. |
| `url`, `iid_url`, `oauth_url` | Google URLs | Change only for proxies or tests. |

Timeouts come from `delivra.http.timeout` and `delivra.http.connect_timeout`.

Delivra caches the OAuth access token in the default cache store, per project, for about 55 minutes.

## Sending

```php
use RSE\Delivra\Fcm\Messages\FcmMessage;

$message = FcmMessage::make('Order ready', 'Your order #15 is ready');

fcm()->to($token)->send($message);                     // one token
fcm()->to($user->devices->pluck('token'))->send($message); // many tokens
fcm()->topic('news')->send($message);
fcm()->condition("'news' in topics && 'ar' in topics")->send($message);
fcm()->project('customers')->to($token)->send($message);
```

FCM v1 has no batch endpoint. Delivra sends one request per token and runs `concurrency` requests in parallel.

`send()` returns an `FcmReport`. It throws only for [configuration errors](#errors). A dead token or a failed delivery never throws.

The `Fcm` facade has the same methods: `Fcm::to($token)->send($message)`.

## Messages

```php
FcmMessage::make('Order ready', 'Your order #15 is ready')
    ->image('https://example.com/order.png')
    ->data(['notify_type' => 'order', 'notify_id' => 15])
    ->ttl(3600)
    ->collapseKey('order-15');
```

| Method | Purpose |
|---|---|
| `make(?string $title, ?string $body)` | New message with a notification. |
| `silent(array $data)` | Data only, delivered in the background. See below. |
| `title()`, `body()`, `image()` | Notification fields. |
| `data(array $data)` | Key/value data for the app. Calls merge. |
| `ttl(int $seconds)` | How long FCM, Apple and browsers keep the message for an offline device. |
| `collapseKey(string $key)` | A newer message with the same key replaces an older pending one. |
| `analyticsLabel(string $label)` | Label for Firebase analytics. |
| `android()`, `apns()`, `webpush()` | [Platform options](#platform-options). |
| `with(array $fields)` | Raw fields merged into the FCM `message` object, for options Delivra does not cover. |
| `project(?string $name)` | Firebase project from config. |
| `topic()`, `condition()` | Send to a topic or condition instead of tokens. |
| `validateOnly()` | FCM checks the message and delivers nothing. |

### Data values

FCM accepts only strings in `data`. Delivra converts each value:

| Value | Sent as |
|---|---|
| `null` | key is left out |
| `true`, `false` | `"true"`, `"false"` |
| int, float | `"15"`, `"12.5"` |
| backed enum | its value |
| `DateTimeInterface` | ISO 8601 |
| array | JSON |

Keys `from`, `notification`, `message_type` and keys that start with `google` or `gcm` are reserved by FCM. Delivra throws `InvalidMessageException` for them.

### Silent pushes

```php
fcm()->topic('mobile-settings')->send(FcmMessage::silent(['notify_type' => 'settings_changed']));
```

A silent message has no notification. Delivra sets Android priority `normal`, and the APNs headers `apns-push-type: background` and `apns-priority: 5` with `content-available: 1`. Apple requires these values for background pushes.

### TTL and collapse key

FCM v1 has no common field for these. Delivra writes the field of each platform:

| Shortcut | Android | APNs | Web |
|---|---|---|---|
| `ttl(3600)` | `ttl: "3600s"` | `apns-expiration` header, now + 3600 | `TTL` header |
| `collapseKey('x')` | `collapse_key` | `apns-collapse-id` header | none |

Delivra computes `apns-expiration` when it sends, so a queued notification does not expire early. A value set in a platform builder wins over the shortcut.

## Platform options

Pass a closure, an array of raw fields, or a config object:

```php
use RSE\Delivra\Fcm\Messages\AndroidConfig;
use RSE\Delivra\Fcm\Messages\ApnsConfig;
use RSE\Delivra\Fcm\Messages\WebpushConfig;

FcmMessage::make('Order ready', 'Your order #15 is ready')
    ->android(fn (AndroidConfig $android) => $android->channel('orders')->sound('default')->priority('high'))
    ->apns(fn (ApnsConfig $apns) => $apns->sound('default')->badge(1))
    ->webpush(fn (WebpushConfig $web) => $web->link('https://app.example.com/orders/15')->icon('/icons/192.png'));
```

**Android** (`AndroidConfig`): `priority('high'|'normal')`, `ttl(int)`, `collapseKey()`, `restrictedPackageName()`, `channel()`, `sound()`, `icon()`, `color('#rrggbb')`, `tag()`, `clickAction()`, `image()`, `notificationPriority()`, `visibility()`, `notificationCount()`.

**APNs** (`ApnsConfig`): `header($name, $value)`, `priority(5|10)`, `pushType()`, `expiration(int $timestamp)`, `collapseId()`, `sound()`, `badge()`, `category()`, `threadId()`, `contentAvailable()`, `mutableContent()`, `payload(array)` for custom keys next to `aps`, `image()`.

**Web** (`WebpushConfig`): `link($url)`, `header()`, `ttl()`, `urgency()`, `topic()`, `icon()`, `badge()`, `image()`, `tag()`, `renotify()`, `requireInteraction()`, `silent()`, `dir()`, `lang()`, `action($action, $title, $icon)`, `data(array)`.

Every config class also has `with(array $fields)` for raw fields and `set('notification.sound', 'x')` for one field by path.

Set `link()` for web. Without it, a click on a notification that the Firebase SDK shows opens nothing useful. The link must use `https`.

## Laravel notifications

```php
use Illuminate\Notifications\Notification;
use RSE\Delivra\Fcm\Messages\FcmMessage;

class OrderReady extends Notification
{
    public function __construct(public Order $order) {}

    public function via($notifiable): array
    {
        return ['fcm'];
    }

    public function toFcm($notifiable, ?string $platform = null): ?FcmMessage
    {
        return FcmMessage::make('Order ready', "Your order #{$this->order->code} is ready")
            ->data(['notify_type' => 'order', 'notify_id' => $this->order->id])
            ->webpush(fn ($web) => $web->link(route('orders.show', $this->order)));
    }
}
```

The notifiable returns its tokens from `routeNotificationForFcm()`. Three shapes work:

```php
// one token
public function routeNotificationForFcm($notification): ?string
{
    return $this->fcm_token;
}

// a list
public function routeNotificationForFcm($notification): array
{
    return $this->devices()->where('active', true)->pluck('token')->all();
}

// tokens per platform
public function routeNotificationForFcm($notification): array
{
    return $this->devices->groupBy('platform')->map->pluck('token')->all();
    // ['android' => [...], 'ios' => [...], 'web' => [...]]
}
```

With tokens per platform, the channel calls `toFcm($notifiable, $platform)` once for each platform. Use it to send different text or data per platform. The channel sends only `android`, `ios` and `web`. It skips other keys, for example `huawei`, because FCM cannot reach those devices.

The method receives the notification, so it can filter. For example, return only web tokens for some notification types.

Other rules:

- `toFcm()` may return `null` to skip the notifiable, or to skip one platform.
- A message with `topic()` or `condition()` goes there, and the channel ignores the tokens.
- The channel returns the `FcmReport`. It never throws for token or delivery failures.
- For an anonymous notifiable: `Notification::route('fcm', $token)->notify(new OrderReady($order))`.

### Channel name

Delivra registers the channel as `fcm`. If your app already has its own `fcm` channel, the last registration wins. During a migration, set `channel` to `null` (or another name) and use the class in `via()`:

```php
public function via($notifiable): array
{
    return [\RSE\Delivra\Fcm\Channels\FcmChannel::class];
}
```

The channel still reads tokens from `routeNotificationForFcm()`.

## Results

`FcmReport` holds one `FcmResult` per token, topic or condition.

```php
$report = fcm()->to($tokens)->send($message);

$report->successfulCount();
$report->failedCount();
$report->invalidTokens();   // ['token...'] to delete
$report->firstMessageId();  // 'projects/my-app/messages/0:1700...'

foreach ($report as $result) {
    $result->status;        // sent, invalid_token, wrong_project, failed
    $result->token();       // null for topics and conditions
    $result->error;         // FCM error code, e.g. UNREGISTERED
    $result->errorMessage;
    $result->retryable;     // true for 429, 5xx and connection errors
}
```

How Delivra reads FCM answers:

| FCM answer | Status | Token invalid? |
|---|---|---|
| 200 | `sent` | no |
| `UNREGISTERED` | `invalid_token` | yes |
| `SENDER_ID_MISMATCH` | `wrong_project` | no. The token belongs to another Firebase project. Check that you send through the right project. |
| `INVALID_ARGUMENT` | `failed` | no. The cause may be the payload or the token. Read `errorMessage`. |
| `THIRD_PARTY_AUTH_ERROR` | `failed` | no. The APNs key or web credential in Firebase is missing or wrong. Other platforms still receive the message. |
| 429, 500, 503, `UNAVAILABLE`, `INTERNAL`, `QUOTA_EXCEEDED` | `failed`, retryable | no. Delivra retries once, after `Retry-After` up to `retry_max_delay`. |
| 401 without an error code | | Delivra fetches a new OAuth token and retries once. A second 401 throws. |
| 403 or 404 without an error code | | throws `FcmConfigurationException` |

## Invalid tokens

FCM answers `UNREGISTERED` when the app was removed or the token expired. Delete or disable such tokens, or every later push to them fails.

Delivra fires `RSE\Delivra\Fcm\Events\FcmTokenInvalid` for each one. It has `token`, `reason`, `project`, `notifiable` and `notification`.

```php
use RSE\Delivra\Fcm\Events\FcmTokenInvalid;

// app with a devices table
Event::listen(function (FcmTokenInvalid $event) {
    Device::where('device_token', $event->token)->update(['status' => false]);
});

// app with one token column on users
Event::listen(function (FcmTokenInvalid $event) {
    User::where('fcm_token', $event->token)->update(['fcm_token' => null]);
});
```

Or register a callback once, for example in a service provider:

```php
fcm()->onInvalidToken(function (string $token, string $reason, $notifiable, $project) {
    Device::where('device_token', $token)->delete();
});
```

## Errors

Delivra throws only for configuration errors: `RSE\Delivra\Exceptions\Fcm\FcmConfigurationException`.

- A missing credential, a JSON file that does not exist, or a private key that `openssl` cannot read.
- The OAuth token exchange failed, for example with `invalid_grant`.
- FCM answered 403 or 404 without a token error code: a wrong project id or a missing permission.
- FCM answered 401 twice.

A configuration error affects every token, so nothing in that send was delivered. Delivra stops at the first one and throws. A queued notification can retry without sending twice.

`InvalidMessageException` is for programming errors: a reserved data key, a topic with invalid characters, or a send with no target.

Exception messages and event recipients never contain the private key, the OAuth token or a full device token. Tokens appear masked, for example `cW3x9a...Qk2f`.

## Topics

```php
fcm()->subscribe('news', $tokens);
fcm()->unsubscribe('news', $tokens);
fcm()->project('customers')->subscribe('offers', $token);

fcm()->topic('news')->send($message);
```

Delivra calls the Instance ID API, 1,000 tokens per request. Both methods return an `FcmReport`. A token that the API reports as `NOT_FOUND` is invalid and fires `FcmTokenInvalid`.

Topic names may contain letters, digits and `-_.~%`. A leading `/topics/` is removed.

## Send events

Delivra fires the shared Delivra events with channel `fcm`, once per token or topic:

| Event | When |
|---|---|
| `DelivraMessageSending` | Before the send. Return `false` from a listener to skip that target. |
| `DelivraMessageSent` | FCM accepted the message. `messageId` is the FCM message name, `response` is the `FcmResult`. |
| `DelivraMessageFailed` | Any status other than `sent`. |

`recipient` holds the masked token or `topic:news`. `payload` in `DelivraMessageSending` is the FCM message without the target.

## Web: service worker

The Firebase JS SDK needs `firebase-messaging-sw.js` at the site root. Delivra does not publish it. This sample shows the two parts that depend on the payload:

```js
importScripts("https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js");
importScripts("https://www.gstatic.com/firebasejs/10.12.0/firebase-messaging-compat.js");

firebase.initializeApp({ /* your Firebase web config */ });

const messaging = firebase.messaging();

// Messages with a notification block: the SDK shows them and opens webpush.fcm_options.link on click.
// Data-only messages reach this handler, and the page shows them.
messaging.onBackgroundMessage((payload) => {
  if (payload.notification) {
    return;
  }

  const data = payload.data || {};

  self.registration.showNotification(data.title || "", {
    body: data.body || "",
    icon: "/icons/icon-192x192.png",
    data: { url: data.link || "/" },
  });
});

self.addEventListener("notificationclick", (event) => {
  event.notification.close();
  event.waitUntil(self.clients.openWindow(event.notification.data?.url || "/"));
});
```

Two ways to send to browsers:

1. Send a notification with `webpush()->link($url)`. The SDK shows it and opens the link. Your handler must skip it, or the user sees it twice.
2. Send data only, with `title`, `body` and `link` in `data`. Your handler shows it and controls sound, actions and vibration.

## Testing

```php
use RSE\Delivra\Facades\Fcm;
use RSE\Delivra\Fcm\Messages\FcmMessage;
use RSE\Delivra\Fcm\Testing\SentPush;

public function test_order_ready_push(): void
{
    $fake = Fcm::fake();

    $user->notify(new OrderReady($order));

    $fake->assertSentTo('token-1', fn (FcmMessage $message) => $message->getTitle() === 'Order ready');
    $fake->assertSent(fn (FcmMessage $message, SentPush $push) => $push->payload['data']['notify_id'] === (string) $order->id);
    $fake->assertSentCount(1);
}

public function test_dead_tokens_are_disabled(): void
{
    Fcm::fake()->failFor('dead-token', 'UNREGISTERED');

    $user->notify(new OrderReady($order));

    $this->assertFalse($device->fresh()->status);
}
```

| Assertion | Passes when |
|---|---|
| `assertSent(?Closure $callback)` | A matching message was sent. The callback gets the `FcmMessage` and the `SentPush`. |
| `assertSentTo(string $token, ?Closure $callback)` | A matching message went to the token. |
| `assertSentToTopic(string $topic, ?Closure $callback)` | A matching message went to the topic. |
| `assertNotSent(?Closure $callback)` | No matching message was sent. |
| `assertNothingSent()` | Nothing was sent. |
| `assertSentCount(int $count)` | Total sends, one per target. |
| `assertSubscribed(string $topic, ?string $token)` | A token was subscribed to the topic. |
| `assertUnsubscribed(string $topic, ?string $token)` | A token was unsubscribed. |

`failFor($tokens, $error)` makes the fake answer with an FCM error code. `UNREGISTERED` reports the token invalid and fires `FcmTokenInvalid`. `SENDER_ID_MISMATCH` gives `wrong_project`. Other codes give `failed`.

`SentPush` has `project`, `target`, `message`, `payload`, `token()`, `topic()` and `condition()`. `$fake->sent(?Closure $filter)` and `$fake->topicChanges()` return collections.

The fake makes no HTTP calls. To test against the HTTP layer, use `Http::fake()`. Delivra calls `https://oauth2.googleapis.com/token`, `https://fcm.googleapis.com/v1/projects/{project_id}/messages:send` and `https://iid.googleapis.com/iid/v1:batchAdd`.

## Artisan command

```bash
php artisan delivra:fcm-test {token} --project=customers
php artisan delivra:fcm-test {token} --validate
```

The command checks the credentials, the OAuth token exchange and one send, and shows which step failed. `--validate` asks FCM to check the message without delivering it.

## Not supported yet

- Web Push with VAPID keys, without Firebase.
- Huawei Push Kit for Huawei devices without Google services.
- Device group messaging.
