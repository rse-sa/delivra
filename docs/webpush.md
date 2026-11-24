# Web push (VAPID)

Delivra sends browser push notifications with VAPID keys. It needs no Firebase project and no Google account, so it suits on-premise installs. The `minishlink/web-push` library encrypts the payload and signs the VAPID token.

Use [FCM](fcm.md) when your app already has a Firebase project for Android and iOS. Use web push when browsers are the only target, or when the server cannot reach Google.

- [Requirements](#requirements)
- [Configuration](#configuration)
- [Storing subscriptions](#storing-subscriptions)
- [Service worker](#service-worker)
- [Subscribing in the browser](#subscribing-in-the-browser)
- [Laravel notifications](#laravel-notifications)
- [Sending without a notification](#sending-without-a-notification)
- [Messages](#messages)
- [Results](#results)
- [Expired subscriptions](#expired-subscriptions)
- [Errors](#errors)
- [Send events](#send-events)
- [Custom senders](#custom-senders)
- [Testing](#testing)
- [Moving from laravel-notification-channels/webpush](#moving-from-laravel-notification-channelswebpush)

## Requirements

- PHP 8.2 or newer with the `openssl`, `curl` and `mbstring` extensions, Laravel 10 or newer.
- The library, which Delivra lists under `suggest`:

```bash
composer require minishlink/web-push
```

- HTTPS on the site. Browsers allow service workers and push on HTTPS and on `localhost` only.

Without the library, a send throws `WebPushConfigurationException` with the install command. The rest of Delivra works without it.

## Configuration

Generate the keys once:

```bash
php artisan delivra:webpush-vapid
```

The command prints two lines for `.env`. Add the subject yourself:

```dotenv
DELIVRA_WEBPUSH_PUBLIC_KEY=BD5H3a3x...
DELIVRA_WEBPUSH_PRIVATE_KEY=VRckzOs3...
DELIVRA_WEBPUSH_SUBJECT=mailto:support@example.com
```

Keep the keys. Browsers bind each subscription to the public key. New keys make every saved subscription fail, and users must subscribe again.

The subject is a `mailto:` or `https:` contact. Push services use it to reach you about problems. It falls back to `APP_URL`.

### Options

All options live under `webpush` in `config/delivra.php`:

| Key | Default | Meaning |
|---|---|---|
| `vapid.subject` | `APP_URL` | Contact for the push services. |
| `vapid.public_key` | | The key the browser subscribes with. |
| `vapid.private_key` | | Signs each request. Keep it secret. |
| `channel` | `webpush` | Notification channel name. `null` skips registration. |
| `ttl` | `2419200` | Seconds a push service keeps a message for an offline browser (4 weeks). |
| `urgency` | `null` | `very-low`, `low`, `normal` or `high`. |
| `topic` | `null` | A waiting message with the same topic replaces the older one. |
| `automatic_padding` | `true` | Pads payloads to hide their length. |
| `delete_expired` | `true` | Deletes a subscription model when its push service answers 404 or 410. |
| `subscriptions.model` | `PushSubscription` | Model for the trait. |
| `subscriptions.table` | `push_subscriptions` | Table for the model and the migration. |
| `subscriptions.connection` | `null` | Database connection for both. |
| `subscriptions.morph_key` | `null` | Key type of the owner models for the migration: `int`, `uuid` or `ulid`. |

Your published config replaces the whole `webpush` block. Copy every key you need into it.

Sends go through Guzzle with the `http.timeout` and `http.connect_timeout` values.

## Storing subscriptions

A browser subscription is an endpoint URL plus two keys. Unlike FCM, the server must store all three. Delivra ships an optional trait, model and migration. Any `routeNotificationForWebPush()` that returns subscriptions works too.

Publish the migration and run it:

```bash
php artisan vendor:publish --tag=delivra-webpush-migrations
php artisan migrate
```

The table has `endpoint`, `public_key`, `auth_token` and `content_encoding`, plus a `subscribable` morph. Set `subscriptions.morph_key` to `uuid` or `ulid` before you migrate when your users have those keys. With `null`, the column follows `Schema::morphUsingUuids()` or `Schema::morphUsingUlids()`. Without either, it holds integers.

Add the trait to the notifiable model:

```php
use RSE\Delivra\WebPush\Concerns\HasPushSubscriptions;

class User extends Authenticatable
{
    use HasPushSubscriptions, Notifiable;
}
```

The trait adds:

| Method | Does |
|---|---|
| `pushSubscriptions()` | The `morphMany` relation. |
| `updatePushSubscription($endpoint, $key, $token, $encoding)` | Saves or updates one subscription. A device that belonged to another user moves to this one. |
| `deletePushSubscription($endpoint)` | Deletes this user's subscription for the endpoint. |
| `ownsPushSubscription($subscription)` | Checks the owner. |
| `routeNotificationForWebPush()` | Returns the subscriptions with a query, so it works when lazy loading is off. |

## Service worker

Publish the service worker to `public/sw.js`:

```bash
php artisan vendor:publish --tag=delivra-webpush-sw
```

It shows each push with `showNotification()`. On click, it focuses an open tab with the same URL or opens a new one. It opens only URLs on the site's own origin. Send a path such as `/orders/15` in `url()`. Then a wrong `APP_URL` in a cron job cannot send users to another host.

## Subscribing in the browser

Pass the public key to the page with `webpush()->publicKey()`. Then register the worker and post the subscription to your app:

```js
const registration = await navigator.serviceWorker.register('/sw.js');

const subscription = await registration.pushManager.subscribe({
    userVisibleOnly: true,
    applicationServerKey: publicKey,
});

await fetch('/profile/push', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
    body: JSON.stringify({
        ...subscription.toJSON(),
        contentEncoding: (PushManager.supportedContentEncodings || ['aes128gcm'])[0],
    }),
});
```

A controller saves it:

```php
use RSE\Delivra\WebPush\Models\PushSubscription;

public function store(Request $request)
{
    $data = $request->validate([
        'endpoint'        => ['required', 'string', 'url:https', 'max:' . PushSubscription::ENDPOINT_MAX_LENGTH],
        'keys.p256dh'     => ['required', 'string', 'max:255'],
        'keys.auth'       => ['required', 'string', 'max:255'],
        'contentEncoding' => ['nullable', 'in:aesgcm,aes128gcm'],
    ]);

    $request->user()->updatePushSubscription(
        $data['endpoint'], $data['keys']['p256dh'], $data['keys']['auth'], $data['contentEncoding'] ?? 'aes128gcm',
    );

    return response()->noContent();
}
```

Accept `https` endpoints only. Your server posts to the endpoint. Without this rule, a user could point your server at any host.

`webpush()->configured()` is `true` when the keys are set and the library is installed. Use it to hide the subscribe button.

## Laravel notifications

Build the message inside `toWebPush()`:

```php
use Illuminate\Notifications\Notification;
use RSE\Delivra\WebPush\Channels\WebPushChannel;

class OrderReady extends Notification
{
    public function __construct(public Order $order) {}

    public function via($notifiable): array
    {
        return [WebPushChannel::class]; // or 'webpush'
    }

    public function toWebPush($notifiable, $notification)
    {
        return webpush()->message('Order ready', "Your order #{$this->order->code} is ready")
            ->icon('/icons/icon-192x192.png')
            ->tag("order-{$this->order->id}")
            ->url(route('orders.show', $this->order, absolute: false));
    }
}
```

The channel calls `toWebPush($notifiable, $notification)`. A one-argument method works too.

The channel reads subscriptions from `routeNotificationForWebPush()`. It accepts:

- an Eloquent collection or array of models with `endpoint`, `public_key`, `auth_token` and `content_encoding`,
- the browser's subscription JSON: `['endpoint' => ..., 'keys' => ['p256dh' => ..., 'auth' => ...]]`,
- a list of either.

The channel drops duplicate endpoints. A missing `content_encoding` means `aes128gcm`.

Other rules:

- `toWebPush()` may return `null` to skip the notifiable.
- The channel returns a `WebPushReport`, or `[]` when it sends nothing.
- It never throws for delivery failures, only for configuration errors.
- For an anonymous notifiable: `Notification::route('webpush', $subscriptionJson)->notify(...)`.

### Channel name

Delivra registers the channel as `webpush`. Set `channel` to `null` when your app has its own `webpush` channel, and use `WebPushChannel::class` in `via()`.

## Sending without a notification

```php
$report = webpush()->to($user->pushSubscriptions)->send(
    webpush()->message('Backup done', 'The nightly backup finished')->url('/backups')
);
```

`to()` takes the same shapes as the route. With no subscriptions, `send()` throws `InvalidMessageException`.

## Messages

`WebPushMessage::make($title, $body)` and `webpush()->message($title, $body)` build the same message.

The payload is the title plus the options of [`showNotification()`](https://developer.mozilla.org/en-US/docs/Web/API/ServiceWorkerRegistration/showNotification):

| Method | Payload key |
|---|---|
| `title()`, `body()` | `title`, `body` |
| `icon()`, `badge()`, `image()` | `icon`, `badge`, `image` |
| `lang()`, `dir()` | `lang`, `dir` (`auto`, `ltr` or `rtl`) |
| `tag()`, `renotify()` | `tag`, `renotify`. A push with the same tag replaces the old one on the device. |
| `requireInteraction()`, `silent()` | `requireInteraction`, `silent` |
| `timestamp($ms)`, `vibrate([...])` | `timestamp`, `vibrate` |
| `action($title, $action, $icon)` | one entry in `actions` |
| `data([...])`, `url($path)` | `data`, `data.url` |

`toArray()` returns the payload. The published service worker reads it as is. The payload must stay under about 4 KB after encryption.

These methods set push service options. They never reach the browser:

| Method | Meaning |
|---|---|
| `ttl($seconds)` | How long the push service keeps the message for an offline browser. `0` drops it if the browser is offline. |
| `urgency($level)` | `very-low`, `low`, `normal` or `high`. Low urgency saves battery on phones. |
| `topic($name)` | Up to 32 letters, digits, `-` or `_`. A newer waiting message with the same topic replaces the older one. |

Unset options use the config defaults. A bad urgency or topic throws `InvalidMessageException`.

## Results

`send()` and the channel return a `WebPushReport`, one `WebPushResult` per subscription:

```php
$report->successfulCount();
$report->failedCount();
$report->expiredEndpoints(); // push service said 404 or 410
$report->failures();        // Collection of WebPushResult

foreach ($report as $result) {
    $result->status;     // sent, expired or failed
    $result->httpStatus; // 201, 410, 500 ...
    $result->reason;
    $result->endpoint();
}
```

Delivra sends one request per subscription, in sequence. A subscription with broken keys fails alone. The others still go out.

## Expired subscriptions

A push service answers 404 or 410 when the browser dropped the subscription. The user cleared site data, revoked permission or uninstalled the browser. Delivra then:

1. fires `WebPushSubscriptionExpired` with `endpoint`, `status`, `subscription`, `notifiable` and `notification`,
2. deletes the model when `delete_expired` is on and the subscription is an Eloquent model.

The event fires before the delete. `subscription` is a `Subscription` value object, so queued listeners still get it after the delete. `$event->subscription->source` is the model or array you passed in.

Turn `delete_expired` off to handle it yourself:

```php
Event::listen(WebPushSubscriptionExpired::class, function ($event) {
    Device::where('endpoint', $event->endpoint)->update(['active' => false]);
});
```

## Errors

Only setup errors throw, as `WebPushConfigurationException`:

- `minishlink/web-push` is not installed,
- the public or private key is empty,
- the keys are not valid VAPID keys.

Delivery failures never throw. They show in the report and fire `DelivraMessageFailed`. Queue retries are up to your app.

## Send events

Delivra fires the shared events with channel `webpush`, once per subscription:

| Event | When |
|---|---|
| `DelivraMessageSending` | Before the send. Return `false` from a listener to skip that subscription. |
| `DelivraMessageSent` | The push service accepted the message. `response` is the `WebPushResult`. |
| `DelivraMessageFailed` | Expired or failed. `error` starts with the HTTP status. |

`recipient` is the masked endpoint, such as `fcm.googleapis.com/...a1b2c3`. `payload` in `DelivraMessageSending` is `toArray()`. No event carries the subscription keys.

When listeners skip every subscription, the channel returns `[]`.

## Custom senders

`routeNotificationForWebPush()` may return an object that another package knows how to reach. Register a sender for its class:

```php
use RSE\Delivra\Facades\WebPush;

WebPush::sendUsing(Kiosk::class, function ($message, Kiosk $kiosk, $notifiable, $notification) {
    return $kiosk->show($message->toArray()); // null when nothing was sent
});
```

`WebPushManager::sendUsing()` does the same. See [Custom senders](../README.md#custom-senders).

## Testing

```php
use RSE\Delivra\Facades\WebPush;
use RSE\Delivra\WebPush\Messages\WebPushMessage;
use RSE\Delivra\WebPush\Testing\SentWebPush;

public function test_order_ready_push(): void
{
    $fake = WebPush::fake();

    $user->notify(new OrderReady($order));

    $fake->assertSentTo($endpoint, fn (WebPushMessage $message) => $message->getTitle() === 'Order ready');
    $fake->assertSent(fn (WebPushMessage $message, SentWebPush $push) => $push->payload['data']['url'] === '/orders/15');
    $fake->assertSentCount(1);
}

public function test_gone_devices_are_removed(): void
{
    WebPush::fake()->expire($endpoint);

    $user->notify(new OrderReady($order));

    $this->assertSame(0, $user->pushSubscriptions()->count());
}
```

| Assertion | Passes when |
|---|---|
| `assertSent(?Closure $callback)` | A matching push was sent. The callback gets the `WebPushMessage` and the `SentWebPush`. |
| `assertSentTo(string $endpoint, ?Closure $callback)` | A matching push went to the endpoint. |
| `assertNotSent(?Closure $callback)` | No matching push was sent. |
| `assertNothingSent()` | Nothing was sent. |
| `assertSentCount(int $count)` | Total sends, one per subscription. |

`expire($endpoints)` makes the fake answer 410. `failFor($endpoints, $status)` answers with any status. `SentWebPush` has `subscription`, `message`, `payload`, `options` and `endpoint()`.

The fake needs no keys and no library. To test real encryption and headers, give the manager a Guzzle client with a mock handler:

```php
webpush()->usingHttpClient(new \GuzzleHttp\Client(['handler' => HandlerStack::create($mock)]));
```

## Moving from laravel-notification-channels/webpush

The table columns are the same, so the existing `push_subscriptions` table stays.

1. `composer remove laravel-notification-channels/webpush` and `composer require minishlink/web-push`.
2. Replace the imports:

| Old | New |
|---|---|
| `NotificationChannels\WebPush\HasPushSubscriptions` | `RSE\Delivra\WebPush\Concerns\HasPushSubscriptions` |
| `NotificationChannels\WebPush\PushSubscription` | `RSE\Delivra\WebPush\Models\PushSubscription` |
| `NotificationChannels\WebPush\WebPushChannel` | `RSE\Delivra\WebPush\Channels\WebPushChannel` |
| `NotificationChannels\WebPush\WebPushMessage` | `RSE\Delivra\WebPush\Messages\WebPushMessage` |

3. Move the keys from `config('webpush.vapid.*')` to `config('delivra.webpush.vapid.*')`. To keep the old `.env` names, map them in your published config: `env('DELIVRA_WEBPUSH_PUBLIC_KEY', env('VAPID_PUBLIC_KEY'))`.
4. Fix migrations that import the old `PushSubscription` class.

Differences:

- `content_encoding` is a plain string. The old model cast it to a library enum.
- `WebPushMessage::options(['TTL' => ...])` is now `ttl()`, `urgency()` and `topic()`.
- The old package fired its own `NotificationSent` and `NotificationFailed` events. Delivra fires the shared Delivra events and `WebPushSubscriptionExpired`.
- `pem_file` keys are not supported. Use the base64url keys.
