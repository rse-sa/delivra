# Delivra - Multi-Channel Laravel Notification Package

Delivra is a developer-friendly, multichannel Laravel notification package that combines SMS and Telegram messaging with a unified API, intelligent error handling, and extensive testing.

## Features

- **SMS Support**: Multiple Saudi gateways (Unifonic, Msegat, Yamamah, ShamelSMS)
- **Telegram Support**: All message types (Text, Photo, Video, Document, Poll, Location, Contact)
- **WhatsApp Support**: Meta Cloud API with many business accounts, class based templates, template validation, webhooks and a test fake
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

DELIVRA_SMS_UNIFONIC_KEY=your-key
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

// Check balance
sms()->getBalance();
```

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

Delivra sends WhatsApp messages through the Meta Cloud API. Requires PHP 8.2 and Laravel 10 or newer.

### Accounts and numbers

Each entry in `delivra.whatsapp.accounts` is one WhatsApp Business Account (WABA). An account has one token and any number of phone numbers.

```php
'whatsapp' => [
    'default'              => 'default',
    'graph_version'        => 'v24.0',
    'default_country_code' => '966',   // 0501234567 becomes 966501234567
    'default_language'     => 'ar',

    'accounts' => [
        'default' => [
            'token'               => env('DELIVRA_WHATSAPP_TOKEN'),
            'business_account_id' => env('DELIVRA_WHATSAPP_BUSINESS_ACCOUNT_ID'),
            'app_secret'          => env('DELIVRA_WHATSAPP_APP_SECRET'),
            'default_number'      => 'main',
            'numbers'             => [
                'main'    => env('DELIVRA_WHATSAPP_PHONE_NUMBER_ID'),
                'support' => env('DELIVRA_WHATSAPP_SUPPORT_PHONE_NUMBER_ID'),
            ],
        ],
        'sales' => [
            'token'               => env('SALES_WHATSAPP_TOKEN'),
            'business_account_id' => env('SALES_WHATSAPP_BUSINESS_ACCOUNT_ID'),
            'app_secret'          => env('SALES_WHATSAPP_APP_SECRET'),
            'numbers'             => ['main' => env('SALES_WHATSAPP_PHONE_NUMBER_ID')],
        ],
    ],
],
```

```php
whatsapp()->to('0501234567')->text('Hello');                         // default account, default number
whatsapp()->from('support')->to('0501234567')->text('Hello');        // another number
whatsapp()->account('sales')->to('0501234567')->text('Hello');       // another account
whatsapp()->account('sales')->from('main')->to($mobile)->send($message);
```

A message can also pick its account: `TemplateMessage::make(...)->account('sales')->from('main')`. An explicit `whatsapp()->account()` call wins over the message.

### Templates

Use a template builder for one-off sends:

```php
use RSE\Delivra\WhatsApp\Messages\TemplateMessage;
use RSE\Delivra\WhatsApp\Templates\Button;
use RSE\Delivra\WhatsApp\Templates\Parameter;

whatsapp()->to($mobile)->send(
    TemplateMessage::make('order_ready', 'ar')
        ->header(Parameter::document($pdfUrl, 'invoice.pdf'))
        ->body([
            'name'   => $order->company,                       // named param {{name}}
            'amount' => Parameter::currency(1250.5, 'SAR'),
            'due'    => $order->due_at,                        // dates become date_time params
        ])
        ->urlButton($order->uuid)                              // button 0, the {{1}} in the URL
        ->button(1, Button::quickReply('wa_unsubscribe'))
        ->tracking("order:{$order->id}")                       // returned in status webhooks
);

// positional templates ({{1}}, {{2}}) take a list
whatsapp()->to($mobile)->template('hello', ['Ali', 'Sara'], 'en');
```

Body keys that are strings become named params. A plain list becomes positional params. Button array keys are the button index in the template.

#### Class based templates

Give each approved template its own class:

```php
use RSE\Delivra\WhatsApp\Templates\Button;
use RSE\Delivra\WhatsApp\Templates\Parameter;
use RSE\Delivra\WhatsApp\Templates\Template;

class OrderReadyForPayment extends Template
{
    protected ?string $name = 'order_ready_for_payment';   // defaults to the snake cased class name
    protected ?string $language = 'ar';
    protected ?string $account = null;                      // optional
    protected ?string $from = null;                         // optional

    public function __construct(public DistOrder $order) {}

    public function body(): array
    {
        return [
            'name'   => $this->order->user->company,
            'id'     => $this->order->code,
            'amount' => Parameter::currency($this->order->getRemainingAmount(), 'SAR'),
            'hours'  => 24,
        ];
    }

    public function buttons(): array
    {
        return [Button::url($this->order->uuid)];
    }
}

whatsapp()->to($mobile)->send(new OrderReadyForPayment($order));
```

Generate the class from the approved Meta definition. The command fills in the header, body variables and buttons that need a value:

```bash
php artisan make:whatsapp-template OrderReadyForPayment --template=order_ready_for_payment --account=default
```

List the templates of an account with their status, variables and buttons:

```bash
php artisan delivra:whatsapp-templates --account=sales --fresh
```

#### Template aliases

Code uses a stable alias. The config maps it to the real Meta name, so each environment can use a different template:

```php
'templates' => [
    'order_ready_for_payment' => [
        'name'     => env('WHATSAPP_ORDER_READY_TEMPLATE'),
        'language' => 'ar',
        'account'  => 'sales',
        'from'     => 'main',
    ],
],
```

#### Validation before send

Set `DELIVRA_WHATSAPP_VALIDATE_TEMPLATES=true` to check each template message against its Meta definition before Delivra calls the API. Delivra caches the definitions for `templates_cache_ttl` seconds. A mismatch throws `TemplateValidationException` with every problem in `$e->errors`, for example a missing named param, a wrong param count, a missing media header, or a missing URL button value.

```php
whatsapp()->account('sales')->templates()->all();           // Collection of TemplateDefinition
whatsapp()->templates()->find('order_ready', 'ar');
whatsapp()->templates()->refresh();
```

### Other messages

These types work only inside the 24 hour customer service window:

```php
use RSE\Delivra\WhatsApp\Messages\ButtonsMessage;
use RSE\Delivra\WhatsApp\Messages\ListMessage;
use RSE\Delivra\WhatsApp\Messages\LocationMessage;
use RSE\Delivra\WhatsApp\Messages\MediaMessage;
use RSE\Delivra\WhatsApp\Messages\ReactionMessage;

whatsapp()->to($mobile)->send(MediaMessage::document($url, 'invoice.pdf', 'Your invoice'));
whatsapp()->to($mobile)->send(ButtonsMessage::make('Stop messages?')->button('wa_unsubscribe', 'Unsubscribe'));
whatsapp()->to($mobile)->send(ListMessage::make('Pick a branch', 'Branches')->section('Riyadh', ['r1' => 'Olaya', 'r2' => 'Malqa']));
whatsapp()->to($mobile)->send(ReactionMessage::make($incomingId, '👍'));
whatsapp()->to($mobile)->send(LocationMessage::make(24.71, 46.67, 'Head office'));

whatsapp()->markAsRead($incomingId, typing: true);
$media = whatsapp()->downloadMedia($mediaId);    // ->mimeType, ->contents, ->save($path)
```

Every message type accepts `replyTo($messageId)` and `tracking($data)`.

### Responses and errors

One recipient returns a `WhatsAppResponse` with `messageId`, `waId`, `messageStatus`, `account` and `phoneNumberId`. Many recipients return a `WhatsAppBatchResponse`. When any recipient fails, Delivra throws `WhatsAppBatchException`.

Delivra maps Meta error codes to exceptions. All of them extend `ApiErrorException`, which has `errorCode`, `errorSubcode`, `details`, `traceId` and `response`:

| Exception | Meta codes |
|---|---|
| `ReEngagementRequiredException` | 131047, more than 24 hours since the last user message |
| `RecipientUnavailableException` | 131026 |
| `TemplateParameterException` | 132000, 132012 |
| `TemplateNotFoundException` | 132001 |
| `RateLimitException` | 4, 80007, 130429, 131048, 131056 |
| `AuthenticationException` | 190 |

Delivra fires `DelivraMessageSending`, `DelivraMessageSent` and `DelivraMessageFailed` with channel `whatsapp`. The `driver` property holds the account name. When a `DelivraMessageSending` listener returns `false`, Delivra skips the message.

### Opt-out

Delivra does not store opt-outs. Register a callback and Delivra checks it before each send:

```php
// AppServiceProvider::boot()
whatsapp()->canSendUsing(fn (string $recipient, $message, $account) => ! WhatsappNumber::isBlocked($recipient));
```

A direct send to a blocked number throws `RecipientBlockedException`. The notification channel skips the number.

### Webhooks

Enable the route and set the verify token. Every account that receives webhooks needs its `app_secret`:

```dotenv
DELIVRA_WHATSAPP_WEBHOOK=true
DELIVRA_WHATSAPP_WEBHOOK_VERIFY_TOKEN=some-random-string
```

Delivra registers `GET` and `POST /delivra/whatsapp/webhook`, named `delivra.whatsapp.webhook`. In Meta, set the callback URL to that route. Change the path with `webhook.path` and add middleware with `webhook.middleware`. The route has no CSRF middleware by default.

The `POST` route rejects requests without a valid `X-Hub-Signature-256` from one of the configured app secrets. Delivra skips inbound message ids it has already processed for `webhook.deduplicate_for` seconds. Delivra fires these events:

| Event | Payload |
|---|---|
| `WhatsAppMessageReceived` | `$event->message`: `InboundMessage` with `from`, `type`, `contactName`, `account`, `text()`, `buttonId()`, `mediaId()`, `location()` |
| `WhatsAppStatusUpdated` | `$event->status`: `StatusUpdate` with `messageId`, `status` (`MessageStatus` enum), `tracking`, `errorCode()` |
| `WhatsAppTemplateStatusUpdated` | Meta approved, rejected, paused or disabled a template |
| `WhatsAppWebhookReceived` | Every change, raw, for fields Delivra does not map |

```php
Event::listen(function (WhatsAppMessageReceived $event) {
    if ($event->message->buttonId() === 'wa_unsubscribe') {
        WhatsappNumber::block($event->message->from);
    }
});
```

### WhatsApp notifications

```php
class OrderReadyNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['whatsapp'];
    }

    public function toWhatsApp(object $notifiable): OrderReadyForPayment
    {
        return new OrderReadyForPayment($this->order);
    }
}

// on the notifiable model
public function routeNotificationForWhatsApp(): ?string
{
    return $this->mobile;
}
```

`toWhatsApp()` returns a class template or any message. When it returns `null`, Delivra sends nothing.

### Testing

```php
use RSE\Delivra\Facades\WhatsApp;

$fake = WhatsApp::fake();

// ... code that sends

$fake->assertTemplateSent(OrderReadyForPayment::class, fn ($template) => $template->order->is($order));
$fake->assertTemplateSent('order_ready_for_payment');        // Meta name or config alias
$fake->assertSentTo('0501234567', OrderReadyForPayment::class);
$fake->assertSent(fn ($sent) => $sent->account === 'sales');
$fake->assertSentCount(1);
$fake->assertNothingSent();
```

The fake makes no HTTP calls and skips template validation.

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

### Telegram Notification

```php
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use RSE\Delivra\Telegram\Messages\TextMessage;

class NewAlert extends Notification
{
    use Queueable;

    public function via($notifiable): array
    {
        return ['telegram'];
    }

    public function toTelegram($notifiable): TextMessage
    {
        return TextMessage::make()
            ->message('<b>New Alert!</b>')
            ->addUrlButton('View Details', 'https://example.com')
            ->asHtml();
    }
}
```

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
    ApiErrorException
};

try {
    sms()->to('invalid')->body('Test')->send();
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
use RSE\Delivra\Sms\SmsResponse;

class CustomDriver extends SmsDriver
{
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
            'class' => CustomDriver::class,
            'api_key' => 'your-key',
        ],
    ],
],
```

### Response Objects

```php
use RSE\Delivra\Telegram\TelegramResponse;
use RSE\Delivra\Sms\SmsResponse;

// SMS Response
$smsResponse = sms()->to('9665000000')->body('Test')->send();
if ($smsResponse->successful()) {
    echo "Message ID: {$smsResponse->getMessageId()}";
    echo "Credits used: {$smsResponse->getCredits()}";
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
            'key' => env('DELIVRA_SMS_UNIFONIC_KEY'),
            'sender' => env('DELIVRA_SMS_UNIFONIC_SENDER'),
        ],
        // ... other drivers
    ],
],
```

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
