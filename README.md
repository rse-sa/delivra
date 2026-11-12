# Delivra - Multi-Channel Laravel Notification Package

Delivra is a developer-friendly, multichannel Laravel notification package that combines SMS and Telegram messaging with a unified API, intelligent error handling, and extensive testing.

## Features

- **SMS Support**: Multiple Saudi gateways (Unifonic, Msegat, Yamamah, ShamelSMS)
- **Telegram Support**: All message types (Text, Photo, Video, Document, Poll, Location, Contact)
- **WhatsApp Support**: Meta Cloud API with many business accounts, class based templates, template validation, webhooks and a test fake
- **Push Notifications (FCM)**: Firebase HTTP v1 for Android, iOS and web, many projects, topics, invalid-token events and a test fake
- **Hybrid Exception Strategy**: Single recipient throws immediately; batch sends all then throws with details
- **HTTP Timeouts**: Configurable timeouts and retries prevent hanging requests
- **Rich Response Objects**: Get message IDs, timestamps, and full API responses
- **Laravel Notifications**: Native integration with Laravel's notification channels
- **Fluent Interface**: Clean, chainable API for all operations

## Installation

```bash
composer require rse-sa/delivra
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=delivra-config
```

## Configuration

Set up your environment variables in `.env`:

```env
# SMS Configuration
DELIVRA_SMS_DRIVER=unifonic
DELIVRA_SMS_CREDITS=true

DELIVRA_SMS_UNIFONIC_APP_SID=your-app-sid
DELIVRA_SMS_UNIFONIC_USERNAME=your-account-username
DELIVRA_SMS_UNIFONIC_PASSWORD=your-account-password
DELIVRA_SMS_UNIFONIC_SENDER=your-sender

# Telegram Configuration
DELIVRA_TELEGRAM_TOKEN=your-bot-token
DELIVRA_TELEGRAM_CHAT_ID=your-default-chat-id
DELIVRA_TELEGRAM_PARSE_MODE=html

# HTTP Client Configuration
DELIVRA_HTTP_TIMEOUT=10
DELIVRA_HTTP_CONNECT_TIMEOUT=5
DELIVRA_HTTP_RETRIES=3
DELIVRA_HTTP_RETRY_DELAY=100
```

## Quick Start

### SMS

```php
use RSE\Delivra\Facades\Sms;

// Single recipient
sms()->to('9665000000')->body('Hello')->send();

// Multiple recipients (with partial failure tracking)
try {
    $batch = sms()->to(['9665000000', '9666000000'])->body('Hello')->send();
} catch (\RSE\Delivra\Exceptions\Sms\SmsBatchException $e) {
    $batch = $e->getBatchResponse();
    echo "Sent: {$batch->successfulCount()}, Failed: {$batch->failedCount()}";
    foreach ($batch->failures() as $failure) {
        echo "Failed to send to {$failure['number']}: {$failure['error']}";
    }
}

// Using facade
Sms::to('9665000000')->body('Hello')->send();

// Send from another sender name
sms()->from('MyBrand')->to('9665000000')->body('Hello')->send();

// Check balance (null when the driver has no balance API)
sms()->getBalance();
```

### SMS driver schema

Each SMS driver describes its settings, so an app can let an admin pick a provider and fill in its settings from a form built at runtime.

```php
use RSE\Delivra\Facades\Sms;
use RSE\Delivra\Sms\SmsFeature;

// every configured driver except null, keyed by driver name
Sms::drivers();                  // Collection of DriverDefinition
Sms::drivers()->toJson();        // send this to your frontend

Sms::definition('msegat')->supports(SmsFeature::Balance); // true
Sms::rules('unifonic');          // Laravel validation rules
```

One driver in the JSON looks like this:

```json
{
  "name": "msegat",
  "label": "Msegat",
  "features": ["balance", "credits"],
  "auth_modes": [
    {
      "name": "api_key",
      "label": "API key",
      "fields": [
        {"name": "username", "label": "Username", "type": "text", "secret": false, "required": true, "rules": ["required", "string"], "help": null, "placeholder": null, "default": null, "options": null},
        {"name": "key", "label": "API key", "type": "password", "secret": true, "required": true, "rules": ["required", "string"], "help": null, "placeholder": null, "default": null, "options": null}
      ]
    }
  ],
  "fields": [
    {"name": "sender", "label": "Sender name", "type": "text", "secret": false, "required": true, "rules": ["required", "string", "max:11"], "help": null, "placeholder": null, "default": null, "options": null}
  ]
}
```

- `name` is the driver's config key. Post it back and pass it to `Sms::via()`.
- `auth_modes` lists the ways a provider accepts credentials. When a driver has more than one, show a mode selector and send the chosen mode as `auth_mode`. The rules only validate the chosen mode's fields.
- `fields` holds settings every mode needs, like the sender name.
- `secret` fields are credentials. Store them encrypted and never send them back to the browser.
- `features` lists what the driver supports beyond sending: `bulk`, `balance` and `credits`.
- Labels follow the app locale. English and Arabic ship with the package. Publish them with `php artisan vendor:publish --tag=delivra-lang` to change them.

Validate the admin's input, store it, then load it into config before the first SMS call, for example in a service provider:

```php
$validated = $request->validate(Sms::rules($request->input('driver')));

// later, at boot
config([
    'delivra.sms.default'                 => $settings->driver,
    "delivra.sms.drivers.{$settings->driver}" => $settings->values,
]);
```

The `Sms` singleton reads config the first time it is resolved. A long-running queue worker keeps that config until it restarts. For one-off sends, `Sms::via($driver, $settings)` takes the settings directly.

Delivra validates a driver's settings when it sends, not when you call `via()`. Invalid settings throw `InvalidDriverSettingsException`, and `getErrors()` returns the messages per setting.

### Telegram

```php
use RSE\Delivra\Facades\Telegram;

// Send text message
telegram()->text('Hello <b>World</b>!')->to('123456789')->send();

// Send photo
telegram()->photo('/path/to/photo.jpg')
    ->caption('Look at this!')
    ->to('123456789')
    ->send();

// Send video
telegram()->video('/path/to/video.mp4')
    ->caption('Check this video')
    ->duration(30)
    ->to('123456789')
    ->send();

// Send document
telegram()->document('/path/to/file.pdf')
    ->caption('Here is the file')
    ->to('123456789')
    ->send();

// Send poll
telegram()->poll('What is your favorite color?', ['Red', 'Blue', 'Green'])
    ->to('123456789')
    ->send();

// Send location
telegram()->location(24.7136, 46.6753)
    ->to('123456789')
    ->send();

// Send contact
telegram()->contact('9665000000', 'John Doe')
    ->to('123456789')
    ->send();

// With buttons
telegram()->text('Click below')
    ->addUrlButton('Visit Site', 'https://example.com')
    ->to('123456789')
    ->send();

// Silent message
telegram()->text('Quiet message')->silently()->send();

// Protected content (prevents forwarding)
telegram()->text('Secret')->withProtectedContent()->send();
```

### Multiple Telegram Recipients

```php
// Send to multiple recipients
try {
    $batch = telegram()->text('Broadcast message')
        ->to(['chat-1', 'chat-2', 'chat-3'])
        ->send();
} catch (\RSE\Delivra\Exceptions\Telegram\TelegramBatchException $e) {
    $batch = $e->getBatchResponse();
    foreach ($batch->failures() as $failure) {
        echo "Failed to send to {$failure['chatId']}: {$failure['error']}";
    }
}
```

## WhatsApp

Delivra sends WhatsApp messages through the Meta Cloud API. It needs PHP 8.2 and Laravel 10 or newer.

The full guide is in [docs/whatsapp.md](docs/whatsapp.md). It covers configuration, every message type, templates, webhooks, errors, notifications and testing.

```dotenv
DELIVRA_WHATSAPP_TOKEN=EAAG...
DELIVRA_WHATSAPP_BUSINESS_ACCOUNT_ID=102030405060708
DELIVRA_WHATSAPP_APP_SECRET=abc123...
DELIVRA_WHATSAPP_PHONE_NUMBER_ID=109876543210987
DELIVRA_WHATSAPP_COUNTRY_CODE=966
```

```php
use RSE\Delivra\WhatsApp\Messages\TemplateMessage;

// template, inline
whatsapp()->to('0501234567')->send(
    TemplateMessage::make('order_ready', 'ar')
        ->body(['name' => $order->company, 'id' => $order->code])
        ->urlButton($order->uuid)
);

// template, as a class
whatsapp()->account('sales')->from('support')->to('0501234567')->send(new OrderReadyForPayment($order));

// text, inside the 24 hour window
whatsapp()->to('0501234567')->text('Your order is ready.');
```

Features:

- Many business accounts, each with many numbers.
- Template builder with named or positional variables, typed params (currency, date, media headers) and buttons.
- One class per template, with `make:whatsapp-template` to generate it from the Meta definition.
- Template aliases in config, so each environment can use a different template.
- Optional check against the approved Meta definition before each send.
- Session messages: text, media, reply buttons, lists, reactions, location.
- Meta error codes mapped to exceptions.
- Webhook route with signature check and events for inbound messages, statuses and template changes.
- `whatsapp` notification channel, opt-out hook and `WhatsApp::fake()` for tests.

## Push notifications (FCM)

Delivra sends push notifications to Android, iOS and web apps through the Firebase Cloud Messaging HTTP v1 API. It uses the service account key directly and needs no Firebase SDK.

The full guide is in [docs/fcm.md](docs/fcm.md).

```dotenv
DELIVRA_FCM_CREDENTIALS=storage/app/firebase.json
```

```php
use RSE\Delivra\Fcm\Messages\FcmMessage;

$report = fcm()->to($tokens)->send(
    FcmMessage::make('Order ready', 'Your order #15 is ready')
        ->data(['notify_type' => 'order', 'notify_id' => 15])
        ->webpush(fn ($web) => $web->link('https://app.example.com/orders/15'))
);

$report->invalidTokens(); // delete these

fcm()->topic('news')->send(FcmMessage::make('News'));
fcm()->subscribe('news', $tokens);
```

Features:

- Many Firebase projects. The OAuth token is cached per project.
- Android, APNs and web options, silent pushes, TTL and collapse keys.
- Send to tokens, topics or conditions, with parallel requests.
- Topic subscribe and unsubscribe.
- Per-token results. Dead tokens fire `FcmTokenInvalid`, so your app cleans its own token table.
- Only configuration errors throw. A failed push never breaks the caller.
- `fcm` notification channel that accepts a token, a list, or tokens per platform.
- `Fcm::fake()` for tests and `delivra:fcm-test` to check the setup.

## Laravel Notifications

### SMS Notification

```php
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use RSE\Delivra\Sms\SmsBuilder;

class OrderShipped extends Notification
{
    use Queueable;

    public function via($notifiable): array
    {
        return ['sms']; // Use string alias
    }

    public function toSms($notifiable): SmsBuilder
    {
        return SmsBuilder::make()
            ->body("Your order has been shipped!");
    }
}
```

A notification can pick the driver and the sender name for its own message:

```php
return SmsBuilder::make()
    ->via('msegat')
    ->from('MyBrand')
    ->body('Your order has been shipped!');
```

### Telegram Notification

```php
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewAlert extends Notification
{
    use Queueable;

    public function via($notifiable): array
    {
        return ['telegram'];
    }

    public function toTelegram($notifiable)
    {
        return telegram()->text('<b>New Alert!</b>')
            ->addUrlButton('View Details', 'https://example.com');
    }
}
```

`telegram()` also has `photo()`, `video()`, `document()`, `poll()`, `location()` and `contact()`. Each returns a message with the default token and parse mode from the config.

### WhatsApp and FCM notifications

Build the message the same way inside `toWhatsApp()` and `toFcm()`:

```php
public function toWhatsApp($notifiable)
{
    return whatsapp()->template('order_ready', 'ar')->body(['name' => $notifiable->name]);
}

public function toFcm($notifiable, ?string $platform = null)
{
    return fcm()->message('Order ready', 'Your order #15 is ready')->data(['notify_id' => 15]);
}
```

See [docs/whatsapp.md](docs/whatsapp.md#laravel-notifications) and [docs/fcm.md](docs/fcm.md#laravel-notifications).

### Mixed Notification

```php
public function via($notifiable): array
{
    return ['mail', 'sms', 'telegram'];
}
```

### Notifiable Model

```php
use Illuminate\Notifications\Notifiable;

class User extends Model
{
    use Notifiable;

    public function routeNotificationForSms($notification): string
    {
        return $this->phone;
    }

    public function routeNotificationForTelegram($notification): string
    {
        return $this->telegram_chat_id;
    }
}
```

## Exception Handling

Delivra throws specific exceptions for different error scenarios:

### SMS Exceptions

```php
use RSE\Delivra\Exceptions\Sms\{
    SmsBatchException,
    InvalidPhoneNumberException,
    OutOfBalanceException,
    DriverNotFoundException,
    InvalidDriverSettingsException,
    ApiErrorException
};

try {
    sms()->to('invalid')->body('Test')->send();
} catch (InvalidDriverSettingsException $e) {
    // Driver settings failed the driver's rules
    $errors = $e->getErrors(); // ['sender' => ['The sender field is required.']]
} catch (InvalidPhoneNumberException $e) {
    // Handle invalid number
    echo "Invalid number: {$e->getNumber()}";
} catch (OutOfBalanceException $e) {
    // Handle insufficient balance
    echo "Insufficient balance for driver: {$e->getDriver()}";
} catch (SmsBatchException $e) {
    // Handle batch failures
    $batch = $e->getBatchResponse();
    // Handle partial successes/failures
}
```

### Telegram Exceptions

```php
use RSE\Delivra\Exceptions\Telegram\{
    TelegramBatchException,
    InvalidTokenException,
    InvalidChatIdException,
    ApiErrorException,
    FileUploadException
};

try {
    telegram()->text('Test')->to('invalid-chat')->send();
} catch (InvalidChatIdException $e) {
    // Handle invalid chat ID
    echo "Invalid chat ID: {$e->getChatId()}";
} catch (TelegramBatchException $e) {
    // Handle batch failures
    $batch = $e->getBatchResponse();
    // Handle partial successes/failures
}
```

## Advanced Usage

### Custom SMS Driver

```php
use RSE\Delivra\Contracts\SmsDriver;
use RSE\Delivra\Sms\Schema\AuthMode;
use RSE\Delivra\Sms\Schema\DriverDefinition;
use RSE\Delivra\Sms\Schema\Field;
use RSE\Delivra\Sms\SmsFeature;
use RSE\Delivra\Sms\SmsResponse;

class CustomDriver extends SmsDriver
{
    // Optional. Without it the driver has no fields, rules or features.
    public static function definition(): DriverDefinition
    {
        return DriverDefinition::make()
            ->label('Custom SMS')
            ->features([SmsFeature::Balance])
            ->authModes([
                AuthMode::make('api_key')->fields([
                    Field::make('api_key')->secret()->rules('required|string'),
                ]),
                AuthMode::make('credentials')->fields([
                    Field::make('username')->rules('required|string'),
                    Field::make('password')->secret()->rules('required|string'),
                ]),
            ])
            ->fields([
                Field::make('sender')->rules('required|string|max:11'),
            ]);
    }

    public function formatNumber(string $number): string
    {
        // Custom number formatting
        return $number;
    }

    protected function sendSingle(string $recipient): SmsResponse
    {
        // Your implementation
        $response = new SmsResponse($this->driver, $recipient, $this->builder->getBody());
        return $response->setSuccessful();
    }

    public function getBalance(): ?int
    {
        // Return account balance
        return 100;
    }
}

// Register in config/delivra.php:
'sms' => [
    'drivers' => [
        'custom' => [
            'class'     => CustomDriver::class,
            'auth_mode' => 'api_key',
            'api_key'   => 'your-key',
            'sender'    => 'MyBrand',
        ],
    ],
],
```

Inside the driver, `$this->authMode()` returns the chosen mode and `$this->sender()` returns the message's sender override or the configured sender.

### Response Objects

```php
use RSE\Delivra\Telegram\TelegramResponse;
use RSE\Delivra\Sms\SmsResponse;

// SMS Response
$smsResponse = sms()->to('9665000000')->body('Test')->send();
if ($smsResponse->successful()) {
    echo "Message ID: {$smsResponse->getMessageId()}";
    echo "Credits used: {$smsResponse->getCredits()}";
    echo "Balance after send: {$smsResponse->getBalance()}"; // Unifonic only
}

// Telegram Response
$telegramResponse = telegram()->text('Test')->to('123456')->send();
if ($telegramResponse->successful()) {
    echo "Message ID: {$telegramResponse->getMessageId()}";
    echo "Sent at: {$telegramResponse->getTimestamp()}";
}
```

## Configuration Reference

### SMS Configuration

```php
'sms' => [
    'default' => env('DELIVRA_SMS_DRIVER', 'null'),
    'credits' => env('DELIVRA_SMS_CREDITS', true),
    'drivers' => [
        'unifonic' => [
            'app_sid'  => env('DELIVRA_SMS_UNIFONIC_APP_SID'),
            'username' => env('DELIVRA_SMS_UNIFONIC_USERNAME'),
            'password' => env('DELIVRA_SMS_UNIFONIC_PASSWORD'),
            'sender'   => env('DELIVRA_SMS_UNIFONIC_SENDER'),
        ],
        // ... other drivers
    ],
],
```

Unifonic sends through `el.cloud.unifonic.com` with the App SID and Basic auth from the account username and password. Bulk sends go through the older `api.unifonic.com/rest/Messages/SendBulk` endpoint in one request. Unifonic has no balance API, so `getBalance()` returns `null`; read `getBalance()` on a send response instead.

Upgrading: the Unifonic `key` setting is now `app_sid`, and `username` and `password` are required.

### Telegram Configuration

```php
'telegram' => [
    'default_token' => env('DELIVRA_TELEGRAM_TOKEN'),
    'default_chat_id' => env('DELIVRA_TELEGRAM_CHAT_ID'),
    'parse_mode' => env('DELIVRA_TELEGRAM_PARSE_MODE', 'html'),
],
```

### HTTP Configuration

```php
'http' => [
    'timeout' => env('DELIVRA_HTTP_TIMEOUT', 10),         // Request timeout in seconds
    'connect_timeout' => env('DELIVRA_HTTP_CONNECT_TIMEOUT', 5),  // Connection timeout in seconds
    'retries' => env('DELIVRA_HTTP_RETRIES', 3),          // Number of retries
    'retry_delay' => env('DELIVRA_HTTP_RETRY_DELAY', 100), // Delay between retries in milliseconds
],
```

## Testing

```bash
composer test
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Security

If you discover any security related issues, please email security@rse.sa instead of using the issue tracker.


## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
