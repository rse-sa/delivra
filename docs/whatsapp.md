# WhatsApp

Delivra sends WhatsApp messages through the Meta WhatsApp Cloud API (WhatsApp Business Platform). It supports many business accounts, template messages, session messages, webhooks and a test fake.

- [Requirements](#requirements)
- [Concepts](#concepts)
- [Configuration](#configuration)
- [Sending messages](#sending-messages)
- [Template messages](#template-messages)
- [Class based templates](#class-based-templates)
- [Template aliases](#template-aliases)
- [Template validation and the registry](#template-validation-and-the-registry)
- [Artisan commands](#artisan-commands)
- [Session messages](#session-messages)
- [Read receipts and media](#read-receipts-and-media)
- [Responses](#responses)
- [Errors](#errors)
- [Send events](#send-events)
- [Opt-out](#opt-out)
- [Webhooks](#webhooks)
- [Laravel notifications](#laravel-notifications)
- [Testing](#testing)
- [Not supported yet](#not-supported-yet)

## Requirements

- PHP 8.2 or newer, Laravel 10 or newer.
- A Meta app with the WhatsApp product, a WhatsApp Business Account (WABA) and at least one registered phone number.
- A system user access token with the `whatsapp_business_messaging` and `whatsapp_business_management` permissions.

## Concepts

| Term | Meaning |
|---|---|
| Account | One WhatsApp Business Account. It has one token and one or more phone numbers. In Delivra, each account has a name, for example `default` or `sales`. |
| Number | A phone number registered in the account. Meta identifies it by a `phone_number_id`. Delivra gives each number a name, for example `main`. |
| Template | A message that Meta approved in advance. Only templates can start a conversation. |
| Session message | Text, media, buttons, list, reaction or location. Meta accepts these only within 24 hours of the user's last message. |
| Webhook | Meta calls your app when a user sends a message, when a message status changes, or when a template status changes. |

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=delivra-config
```

### One account

For a single account, the `.env` file is enough:

```dotenv
DELIVRA_WHATSAPP_TOKEN=EAAG...
DELIVRA_WHATSAPP_BUSINESS_ACCOUNT_ID=102030405060708
DELIVRA_WHATSAPP_APP_SECRET=abc123...
DELIVRA_WHATSAPP_PHONE_NUMBER_ID=109876543210987
DELIVRA_WHATSAPP_COUNTRY_CODE=966
DELIVRA_WHATSAPP_LANGUAGE=ar
```

### Many accounts and numbers

Add accounts in `config/delivra.php`:

```php
'whatsapp' => [
    'default'              => env('DELIVRA_WHATSAPP_ACCOUNT', 'default'),
    'graph_url'            => 'https://graph.facebook.com',
    'graph_version'        => env('DELIVRA_WHATSAPP_GRAPH_VERSION', 'v24.0'),
    'default_country_code' => env('DELIVRA_WHATSAPP_COUNTRY_CODE'),
    'default_language'     => env('DELIVRA_WHATSAPP_LANGUAGE', 'en_US'),

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
            'numbers'             => [
                'main' => env('SALES_WHATSAPP_PHONE_NUMBER_ID'),
            ],
        ],
    ],
],
```

### Options

| Key | Default | Purpose |
|---|---|---|
| `default` | `default` | Account used when you do not name one. |
| `graph_url` | `https://graph.facebook.com` | Meta Graph API host. |
| `graph_version` | `v24.0` | Graph API version. Meta retires each version about two years after release. |
| `default_country_code` | `null` | Delivra adds this code to numbers that start with `0`. With `966`, `0501234567` becomes `966501234567`. |
| `default_language` | `en_US` | Template language when neither the message nor the alias sets one. |
| `accounts.*.token` | | Access token. |
| `accounts.*.business_account_id` | | WABA id. The template registry and webhook matching need it. |
| `accounts.*.app_secret` | | Meta app secret. The webhook signature check needs it. |
| `accounts.*.default_number` | first number | Number name used when you do not pick one. |
| `accounts.*.numbers` | | Number name mapped to `phone_number_id`. |
| `templates` | `[]` | [Template aliases](#template-aliases). |
| `validate_templates` | `false` | Check templates against Meta before sending. |
| `templates_cache_ttl` | `3600` | Seconds to cache template definitions. |
| `webhook.*` | | [Webhooks](#webhooks). |

The shared `delivra.http` options set the timeout and retries. Delivra retries only on network errors. A retry after an API error could send the same message twice.

### Phone number format

Delivra removes everything except digits. It also removes a leading `00`. When `default_country_code` is set, Delivra replaces a leading `0` with that code. These inputs all become `966501234567`:

```
+966 50 123 4567
00966501234567
0501234567        (with default_country_code = 966)
```

## Sending messages

Use the `whatsapp()` helper or the `RSE\Delivra\Facades\WhatsApp` facade. Both return the same `WhatsAppManager`.

```php
whatsapp()->to('0501234567')->text('Your order is ready.');

WhatsApp::to('0501234567')->text('Your order is ready.');
```

### Choose the account and number

```php
whatsapp()->to($mobile)->text('Hi');                                   // default account, default number
whatsapp()->from('support')->to($mobile)->text('Hi');                  // default account, number "support"
whatsapp()->account('sales')->to($mobile)->text('Hi');                 // account "sales", its default number
whatsapp()->account('sales')->from('main')->to($mobile)->text('Hi');   // account and number
```

`from()` accepts a number name from the config or a raw `phone_number_id`.

A message can carry its own account and number:

```php
whatsapp()->to($mobile)->send(
    TemplateMessage::make('order_ready', 'ar')->account('sales')->from('main')
);
```

Delivra picks the account and number in this order:

1. The account you pass to `whatsapp()->account()`. It wins over the message.
2. The account on the message, or on its [alias](#template-aliases).
3. The `default` account from the config.

The number follows the same order.

### Recipients

`to()` takes one number or an array:

```php
whatsapp()->to('0501234567')->text('Hi');                    // returns WhatsAppResponse
whatsapp()->to(['0501234567', '0559876543'])->text('Hi');    // returns WhatsAppBatchResponse
```

A message can also carry its recipients with `->to()`. Then call `send()` without `to()`:

```php
whatsapp()->send(TextMessage::make('Hi')->to('0501234567'));
```

### Send methods

After `to()`, these methods send right away:

| Method | Sends |
|---|---|
| `text(string $body, bool $previewUrl = false)` | A text message. |
| `template(string $name, array $body = [], ?string $language = null)` | A template with body variables only. |
| `send(Message\|Template $message)` | Any message object or template class. |

`whatsapp()->text()` and `whatsapp()->template()` are different. Called directly on `whatsapp()`, without `to()`, they only return a message builder and send nothing:

```php
$message = whatsapp()->template('order_ready', 'ar');   // TemplateMessage, not sent
whatsapp()->to($mobile)->send($message->body([...]));   // sent here
```

## Template messages

Meta must approve a template before you can send it. You create templates in WhatsApp Manager or through the API. Delivra sends the template name, the language and the values for its variables.

### Quick send, body only

```php
whatsapp()->to($mobile)->template('order_ready', [
    'name'   => 'Acme',
    'amount' => '1,250.00',
], 'ar');
```

### Builder

Use `TemplateMessage` when the template also has a header, buttons, a reply context or tracking data:

```php
use RSE\Delivra\WhatsApp\Messages\TemplateMessage;
use RSE\Delivra\WhatsApp\Templates\Button;
use RSE\Delivra\WhatsApp\Templates\Parameter;

whatsapp()->to($mobile)->send(
    TemplateMessage::make('order_ready_for_payment', 'ar')
        ->header(Parameter::document($invoiceUrl, 'invoice.pdf'))
        ->body([
            'name'   => $order->company,
            'id'     => $order->code,
            'amount' => Parameter::currency($order->total, 'SAR'),
            'due'    => $order->due_at,
        ])
        ->urlButton($order->uuid)
        ->button(1, Button::quickReply('wa_unsubscribe'))
        ->tracking("order:{$order->id}")
);
```

### Named and positional variables

Meta templates use one of two variable styles. Delivra picks the style from the array keys.

| Template body | Delivra body array |
|---|---|
| `Hello {{name}}, order {{id}} is ready.` (named) | `['name' => 'Ali', 'id' => 'A-15']` |
| `Hello {{1}}, order {{2}} is ready.` (positional) | `['Ali', 'A-15']` |

String keys become named parameters. A plain list becomes positional parameters, in order.

### Builder methods

| Method | Purpose |
|---|---|
| `TemplateMessage::make(string $name, ?string $language = null)` | Start a template message. |
| `language(?string $language)` | Set the language code, for example `ar` or `en_US`. |
| `header(Parameter\|string\|null $value)` | Header variable. A string becomes a text parameter. |
| `body(array $parameters)` | All body variables. This replaces earlier values. |
| `with(string\|int $key, mixed $value)` | Add or replace one body variable. |
| `buttons(array $buttons)` | All button values, keyed by button index. |
| `button(int $index, Button $button)` | One button value. |
| `urlButton(string $suffix, int $index = 0)` | Value for the `{{1}}` in a dynamic URL button. |
| `quickReplyButton(string $payload, int $index = 0)` | Payload of a quick reply button. |
| `copyCodeButton(string $code, int $index = 0)` | Coupon code of a copy code button. |

Every message type also has `to()`, `account()`, `from()`, `replyTo()` and `tracking()`. See [Common message options](#common-message-options).

### Parameter types

Delivra wraps plain values for you:

| Value | Becomes |
|---|---|
| string, int, float, `Stringable` | text |
| `DateTimeInterface` (Carbon) | date_time, formatted `Y-m-d H:i` |
| backed enum | text of the enum value |
| `Parameter` | used as is |

Create other types with `RSE\Delivra\WhatsApp\Templates\Parameter`:

| Method | Use |
|---|---|
| `Parameter::text($value)` | Text. |
| `Parameter::currency(float $amount, string $code, ?string $fallback = null)` | Money. The default fallback text is `1,250.00 SAR`. |
| `Parameter::dateTime($value, string $format = 'Y-m-d H:i')` | Date shown as text. |
| `Parameter::image(string $media)` | Image header. |
| `Parameter::video(string $media)` | Video header. |
| `Parameter::document(string $media, ?string $filename = null)` | Document header. |
| `Parameter::location($latitude, $longitude, $name = null, $address = null)` | Location header. |

For media, pass a public `https://` link or the id of media you uploaded to Meta with [`upload()`](#upload-media). Delivra sends a value that starts with `http` as a link and any other value as a media id.

### Buttons

Only buttons that need a value at send time go in the message. Static buttons, for example a URL without `{{1}}` or a quick reply without a payload, need nothing.

The array key is the button position in the template, starting at `0`:

```php
use RSE\Delivra\WhatsApp\Templates\Button;

->buttons([
    0 => Button::url($order->uuid),           // https://example.com/pay/{{1}}
    2 => Button::copyCode('SAVE10'),
])
```

| Method | Template button type |
|---|---|
| `Button::url(string $suffix)` | URL with a variable. |
| `Button::quickReply(string $payload)` | Quick reply. The webhook returns the payload when the user taps it. |
| `Button::copyCode(string $code)` | Copy code (coupon). |
| `Button::flow(?string $token = null, array $data = [])` | WhatsApp Flow. |

## Class based templates

Give each approved template its own class. The class keeps the variable mapping in one place and makes tests clear.

```php
namespace App\WhatsApp\Templates;

use App\Models\DistOrder;
use RSE\Delivra\WhatsApp\Templates\Button;
use RSE\Delivra\WhatsApp\Templates\Parameter;
use RSE\Delivra\WhatsApp\Templates\Template;

class OrderReadyForPayment extends Template
{
    protected ?string $name = 'order_ready_for_payment';

    protected ?string $language = 'ar';

    public function __construct(public DistOrder $order) {}

    public function header(): Parameter|string|null
    {
        return null;
    }

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
```

```php
whatsapp()->to($order->user->mobile)->send(new OrderReadyForPayment($order));
```

| Member | Default | Purpose |
|---|---|---|
| `$name` | snake case class name (`order_ready_for_payment`) | Meta template name or [alias](#template-aliases). |
| `$language` | alias language, then `default_language` | Language code. |
| `$account` | `null` | Account name. |
| `$from` | `null` | Number name. |
| `$tracking` | `null` | Tracking data for status webhooks. |
| `header()` | `null` | Header variable. |
| `body()` | `[]` | Body variables. |
| `buttons()` | `[]` | Button values keyed by index. |
| `toMessage()` | | Builds the `TemplateMessage`. Delivra calls it for you. |

Create the class with [`make:whatsapp-template`](#artisan-commands).

## Template aliases

An alias is a fixed name that your code uses. The config maps it to the real Meta template. Each environment can then use a different template, language or account without code changes.

```php
// config/delivra.php
'templates' => [
    'order_ready_for_payment' => [
        'name'     => env('WHATSAPP_ORDER_READY_TEMPLATE', 'order_ready_for_payment_v2'),
        'language' => 'ar',
        'account'  => 'sales',
        'from'     => 'main',
    ],
],
```

```php
// code keeps the alias
whatsapp()->to($mobile)->template('order_ready_for_payment', [...]);
whatsapp()->to($mobile)->send(new OrderReadyForPayment($order));   // $name is the alias
```

Rules:

- `name` replaces the template name. All other keys are optional.
- A language, account or number set on the message wins over the alias.
- The test fake matches both the alias and the real name.

## Template validation and the registry

### Validation before send

Meta rejects a template send with the wrong variables. The error comes back as code `132000` and the user gets nothing. With validation on, Delivra checks the message against the approved definition first:

```dotenv
DELIVRA_WHATSAPP_VALIDATE_TEMPLATES=true
DELIVRA_WHATSAPP_TEMPLATES_CACHE_TTL=3600
```

Delivra reads the definitions once per account and caches them. The check finds these problems:

- The template does not exist in that language.
- The template status is not `APPROVED`.
- The header needs a value and the message has none.
- Named variables are missing or unknown, or the count of positional variables is wrong.
- A URL or copy code button needs a value and the message has none.

Delivra throws `TemplateValidationException` with every problem:

```php
use RSE\Delivra\Exceptions\WhatsApp\TemplateValidationException;

try {
    whatsapp()->to($mobile)->send(new OrderReadyForPayment($order));
} catch (TemplateValidationException $e) {
    $e->template;   // 'order_ready_for_payment_v2'
    $e->errors;     // ['Body is missing parameters: hours.', 'Button 0 [URL] needs a parameter.']
}
```

Validation costs one API call per account per cache period. It is a good default in staging. In production, turn it on when templates change often.

### Template registry

The registry reads an account's templates from Meta:

```php
$templates = whatsapp()->templates()->all();                 // Collection of TemplateDefinition
$templates = whatsapp()->account('sales')->templates()->refresh();
$template  = whatsapp()->templates()->find('order_ready', 'ar');
```

`TemplateDefinition` has these members:

| Member | Returns |
|---|---|
| `name`, `language`, `status`, `category`, `id`, `components` | Raw values from Meta. |
| `isApproved()` | `true` when the status is `APPROVED`. |
| `bodyText()` | Body text with placeholders. |
| `bodyVariables()` | Variable names in order, for example `['name', 'id']` or `['1', '2']`. |
| `usesNamedParameters()` | `true` for `{{name}}` style. |
| `headerFormat()` | `TEXT`, `IMAGE`, `VIDEO`, `DOCUMENT`, `LOCATION` or `null`. |
| `headerNeedsParameter()` | `true` when the header needs a value. |
| `buttons()` | List of `index`, `type`, `text`, `url`, `needs_parameter`. |

## Artisan commands

### List templates

```bash
php artisan delivra:whatsapp-templates
php artisan delivra:whatsapp-templates --account=sales --fresh
```

The command prints each template with its language, status, category, header format, body variables and buttons. A `*` marks a button that needs a value. `--fresh` skips the cache.

### Create a template class

```bash
php artisan make:whatsapp-template OrderReadyForPayment
php artisan make:whatsapp-template OrderReadyForPayment --template=order_ready_for_payment --language=ar --account=sales
```

The command writes `app/WhatsApp/Templates/OrderReadyForPayment.php`. When Delivra finds the template in Meta, it fills in the header type, the body variable keys and the buttons that need values. You then add the values. Without access to Meta, the command writes an empty class and shows a warning.

| Option | Default |
|---|---|
| `--template`, `-t` | Snake case class name. |
| `--language`, `-l` | Language from Meta, then `default_language`. |
| `--account`, `-a` | Default account. |
| `--force`, `-f` | Overwrite an existing class. |

To change the generated code, publish the stub:

```bash
php artisan vendor:publish --tag=delivra-stubs
```

The command then uses `stubs/whatsapp-template.stub` from your app.

## Session messages

Meta accepts these types only within 24 hours of the user's last message. Outside that window, Meta returns error `131047` and Delivra throws `ReEngagementRequiredException`. Send a template instead.

```php
use RSE\Delivra\WhatsApp\Messages\ButtonsMessage;
use RSE\Delivra\WhatsApp\Messages\ListMessage;
use RSE\Delivra\WhatsApp\Messages\LocationMessage;
use RSE\Delivra\WhatsApp\Messages\MediaMessage;
use RSE\Delivra\WhatsApp\Messages\ReactionMessage;
use RSE\Delivra\WhatsApp\Messages\TextMessage;

// text
whatsapp()->to($mobile)->send(TextMessage::make('Visit https://example.com')->previewUrl());

// media: link or media id from upload()
whatsapp()->to($mobile)->send(MediaMessage::image($url, 'Caption'));
whatsapp()->to($mobile)->send(MediaMessage::video($url, 'Caption'));
whatsapp()->to($mobile)->send(MediaMessage::audio($url));
whatsapp()->to($mobile)->send(MediaMessage::document($url, 'invoice.pdf', 'Your invoice'));
whatsapp()->to($mobile)->send(MediaMessage::sticker($mediaId));

// reply buttons, 1 to 3
whatsapp()->to($mobile)->send(
    ButtonsMessage::make('Do you want to stop these messages?')
        ->header('Notifications')
        ->button('wa_unsubscribe', 'Unsubscribe')
        ->button('wa_keep', 'Keep')
        ->footer('Reply any time')
);

// list
whatsapp()->to($mobile)->send(
    ListMessage::make('Pick a branch', 'Branches')
        ->section('Riyadh', ['r1' => 'Olaya', 'r2' => ['title' => 'Malqa', 'description' => 'North Riyadh']])
        ->section('Jeddah', ['j1' => 'Tahlia'])
);

// reaction, an empty emoji removes it
whatsapp()->to($mobile)->send(ReactionMessage::make($incomingMessageId, '👍'));

// location
whatsapp()->to($mobile)->send(LocationMessage::make(24.7136, 46.6753, 'Head office', 'Olaya St, Riyadh'));
```

`ButtonsMessage` throws `InvalidMessageException` when it has no buttons or more than three. `ListMessage` throws when it has no section.

### Common message options

All message types and `TemplateMessage` have these methods:

| Method | Purpose |
|---|---|
| `to(string\|array $recipients)` | Recipients, when you do not call `whatsapp()->to()`. |
| `account(string $account)` | Account name. |
| `from(string $number)` | Number name or `phone_number_id`. |
| `replyTo(string $messageId)` | Show the message as a reply to an earlier message. |
| `tracking(string $data)` | Data that Meta returns in status webhooks (`biz_opaque_callback_data`). Use it to link a status to your record, for example `order:15`. |

## Read receipts and media

```php
// blue ticks for an inbound message, optionally with the typing indicator
whatsapp()->markAsRead($inboundMessageId);
whatsapp()->account('sales')->markAsRead($inboundMessageId, typing: true);

// download inbound media by its id
$media = whatsapp()->downloadMedia($mediaId);

$media->mimeType;      // 'image/jpeg'
$media->contents;      // raw bytes
$media->size();        // bytes
$media->extension();   // 'jpg'
$path = $media->save();                       // temp file, returns the path
$path = $media->save(storage_path('a.jpg'));  // given path

Storage::put("whatsapp/{$media->id}.{$media->extension()}", $media->contents);

// URL only, valid for about 5 minutes and needs the token
$url = whatsapp()->mediaUrl($mediaId);
```

Use the account that received the message. The `account` property of an inbound message names it.

### Upload media

Upload a private file, for example an invoice in `storage/`, to get a media id. Send the id in place of a link. Meta keeps uploaded media for 30 days, so you can send one id to many recipients.

```php
// local path or UploadedFile; the file name defaults to the base name
$mediaId = whatsapp()->upload(storage_path("app/invoices/{$invoice->id}.pdf"));
$mediaId = whatsapp()->upload($request->file('attachment'));   // uses the client file name

// raw bytes, for example from an S3 disk; the MIME type is guessed when null
$mediaId = whatsapp()->uploadContents(Storage::disk('s3')->get($path), 'invoice.pdf', 'application/pdf');

whatsapp()->to($mobile)->send(MediaMessage::document($mediaId, 'invoice.pdf', 'Your invoice'));
```

Upload from the account that sends the message: `whatsapp()->account('sales')->from('support')->upload($path)`. A missing file throws `WhatsAppException`. Meta errors throw `ApiErrorException`.

Template with a document header and body variables:

```php
$mediaId = whatsapp()->upload(storage_path("app/invoices/{$invoice->id}.pdf"));

whatsapp()->to($customer->mobile)->send(
    TemplateMessage::make('invoice_ready', 'ar')
        ->header(Parameter::document($mediaId, "invoice-{$invoice->number}.pdf"))
        ->body([
            'name'   => $customer->name,
            'number' => $invoice->number,
            'amount' => Parameter::currency($invoice->total, 'SAR'),
        ])
);
```

The same in a [class based template](#class-based-templates):

```php
public function header(): Parameter
{
    return Parameter::document(
        whatsapp()->upload(storage_path("app/invoices/{$this->invoice->id}.pdf")),
        "invoice-{$this->invoice->number}.pdf",
    );
}
```

Delivra calls `header()` once per `send()` call, so each call uploads the file again. Notifications send once per notifiable. To send one file to many people in a loop, upload once and pass the id to the template constructor.

## Responses

One recipient returns `WhatsAppResponse`:

| Property | Value |
|---|---|
| `messageId` | Meta message id (`wamid...`). Store it to match status webhooks. |
| `waId` | WhatsApp id of the recipient. |
| `messageStatus` | `accepted`, or `held_for_quality_assessment` for some marketing templates. |
| `account` | Account name. |
| `phoneNumberId` | Sender number id. |
| `recipient` | Formatted number. |
| `timestamp` | Time of the send. |
| `raw` | Full API response. |

Many recipients return `WhatsAppBatchResponse`. Delivra sends to every recipient first. When any of them failed, it throws `WhatsAppBatchException`:

```php
use RSE\Delivra\Exceptions\WhatsApp\WhatsAppBatchException;

try {
    $batch = whatsapp()->to($numbers)->send(new Campaign);
} catch (WhatsAppBatchException $e) {
    $batch = $e->getBatchResponse();

    foreach ($batch->failed() as $number => $exception) {
        logger()->warning("WhatsApp to {$number} failed", ['error' => $exception->getMessage()]);
    }
}

$batch->successful();       // [number => WhatsAppResponse]
$batch->successfulCount();
$batch->failedCount();
$batch->totalCount();
```

A send returns `null` when a `DelivraMessageSending` listener returned `false`. See [Send events](#send-events).

## Errors

All WhatsApp exceptions extend `RSE\Delivra\Exceptions\WhatsApp\WhatsAppException`.

### API errors

Delivra converts Meta error responses into `ApiErrorException` or one of its subclasses:

| Exception | Meta code | Meaning | What to do |
|---|---|---|---|
| `ReEngagementRequiredException` | 131047 | More than 24 hours since the user's last message. | Send a template. |
| `RecipientUnavailableException` | 131026 | The number has no WhatsApp, or has an old app version. | Use another channel. |
| `TemplateParameterException` | 132000, 132012 | Wrong variable count or format. | Check the template. Turn on validation. |
| `TemplateNotFoundException` | 132001 | No template with that name and language. | Check the name, language and alias. |
| `RateLimitException` | 4, 80007, 130429, 131048, 131056 | Too many messages. | Retry later with backoff. |
| `AuthenticationException` | 190 | Token is invalid or expired. | Replace the token. |

Other codes throw `ApiErrorException` itself. Every API exception has these properties:

```php
use RSE\Delivra\Exceptions\WhatsApp\ApiErrorException;

catch (ApiErrorException $e) {
    $e->errorCode;     // 131047
    $e->errorSubcode;
    $e->errorType;     // 'OAuthException'
    $e->details;       // Meta's explanation
    $e->traceId;       // fbtrace_id for Meta support
    $e->status;        // HTTP status
    $e->response;      // full error body
}
```

Add codes to the map in a service provider:

```php
ApiErrorException::$map[131049] = App\Exceptions\WhatsAppEcosystemLimit::class;   // must extend ApiErrorException
```

### Other exceptions

| Exception | When |
|---|---|
| `WhatsAppException` | Unknown account or number, missing token, missing `business_account_id`, missing app secret for webhooks. |
| `InvalidMessageException` | No recipients, or an invalid buttons or list message. |
| `RecipientBlockedException` | The [opt-out](#opt-out) callback blocked the number. |
| `TemplateValidationException` | [Validation](#validation-before-send) found problems. |
| `WhatsAppBatchException` | One or more recipients of a batch failed. |

## Send events

Delivra fires the shared Delivra events with channel `whatsapp`:

| Event | When | Useful properties |
|---|---|---|
| `DelivraMessageSending` | Before each API call | `recipient`, `body`, `payload`, `driver` (account name), `message` |
| `DelivraMessageSent` | After success | `messageId`, `response` (`WhatsAppResponse`), `message` |
| `DelivraMessageFailed` | After an exception | `error`, `exception`, `message` |

When a `DelivraMessageSending` listener returns `false`, Delivra skips that recipient. `body` holds the text, the caption or `[template] name`.

Delivra fires `DelivraMessageSending` in halt mode, like Laravel's `NotificationSending`. The first listener that returns a value other than `null` stops the listeners after it. Return nothing from listeners that only log.

Log every outbound message in your own table:

```php
Event::listen(function (DelivraMessageSent $event) {
    if ($event->channel !== 'whatsapp') {
        return;
    }

    WhatsappHistory::create([
        'to_number'           => $event->recipient,
        'whatsapp_message_id' => $event->messageId,
        'template_name'       => $event->message instanceof TemplateMessage ? $event->message->getName() : null,
        'body'                => $event->body,
        'payload'             => $event->response->raw,
    ]);
});
```

## Opt-out

Delivra does not store opt-outs. Your app owns the list. Register a callback, and Delivra calls it before each send:

```php
// AppServiceProvider::boot()
use RSE\Delivra\WhatsApp\Account;
use RSE\Delivra\WhatsApp\Messages\Message;

whatsapp()->canSendUsing(function (string $recipient, Message $message, Account $account): bool {
    return ! WhatsappNumber::where('number', $recipient)->whereNotNull('unsubscribed_at')->exists();
});
```

- `$recipient` is already formatted, for example `966501234567`.
- A direct send to a blocked number throws `RecipientBlockedException`.
- In a batch, the blocked number goes to the failures.
- The notification channel skips the number and sends nothing.

Pass `null` to remove the callback.

## Webhooks

### Setup

1. Turn on the route and set a verify token:

    ```dotenv
    DELIVRA_WHATSAPP_WEBHOOK=true
    DELIVRA_WHATSAPP_WEBHOOK_VERIFY_TOKEN=a-long-random-string
    ```

2. Set `app_secret` for every account that receives webhooks.
3. In the Meta app, open **WhatsApp > Configuration**. Set the callback URL to `https://your-app.test/delivra/whatsapp/webhook` and the verify token to the same value.
4. Subscribe to the `messages` field. Subscribe to `message_template_status_update` if you want template status events.

Delivra registers two routes:

| Method | Route name | Purpose |
|---|---|---|
| `GET` | `delivra.whatsapp.webhook.verify` | Meta handshake. Returns the challenge when the verify token matches. |
| `POST` | `delivra.whatsapp.webhook` | Webhook payloads. |

Options under `webhook`:

| Key | Default | Purpose |
|---|---|---|
| `enabled` | `false` | Register the routes. |
| `path` | `delivra/whatsapp/webhook` | URL path. |
| `verify_token` | | Token for the handshake. |
| `middleware` | `[]` | Extra middleware. Do not add `web`, because CSRF would block Meta. |
| `deduplicate_for` | `86400` | Seconds to remember inbound message ids. `0` turns it off. |

### Security

The `POST` route checks the `X-Hub-Signature-256` header. Delivra accepts the request when the signature matches any configured `app_secret`. Your accounts can belong to different Meta apps. Without a valid signature, Delivra returns `403`. When no account has an `app_secret`, Delivra throws `WhatsAppException`, so a missing secret shows up as an error.

Meta resends a webhook when your app does not answer fast. Delivra stores each inbound message id in the cache and skips repeats. Delivra does not deduplicate status updates, because the same message has several statuses.

Delivra answers `200` after it fires the events. Slow work in listeners delays the answer. Queue heavy work with `ShouldQueue` listeners.

### Events

Delivra finds the account from `metadata.phone_number_id`, then from the WABA id. The `account` property is `null` when no configured account matches.

| Event | Property | Fired for |
|---|---|---|
| `WhatsAppWebhookReceived` | `field`, `value`, `account`, `businessAccountId` | Every change, before the typed events. Use it for fields Delivra does not map. |
| `WhatsAppMessageReceived` | `message` (`InboundMessage`) | Each new inbound message. |
| `WhatsAppStatusUpdated` | `status` (`StatusUpdate`) | Each status change of a message you sent. |
| `WhatsAppTemplateStatusUpdated` | `account`, `templateId`, `templateName`, `language`, `status`, `reason` | Meta approved, rejected, paused or disabled a template. |

All events are in `RSE\Delivra\WhatsApp\Events`.

### InboundMessage

| Member | Returns |
|---|---|
| `id` | Message id. Use it for `markAsRead()`, `replyTo()` and reactions. |
| `from` | Sender number, for example `966501234567`. |
| `type` | `text`, `image`, `video`, `audio`, `document`, `sticker`, `location`, `contacts`, `reaction`, `button`, `interactive` and others. |
| `timestamp` | `CarbonImmutable`. |
| `account`, `phoneNumberId`, `displayPhoneNumber` | Receiving account and number. |
| `contactName` | WhatsApp profile name. |
| `replyToId` | Id of the message the user replied to. |
| `raw` | Original message array. |
| `text()` | Text body, media caption, tapped button title or reaction emoji. |
| `buttonId()` | Id of a tapped reply button or list row, or the payload of a template quick reply. |
| `isButtonReply()` | `true` when `buttonId()` has a value. |
| `mediaId()`, `hasMedia()`, `mimeType()` | Media details for image, video, audio, document and sticker. |
| `location()` | `latitude`, `longitude`, `name`, `address`. |

### StatusUpdate

| Member | Returns |
|---|---|
| `messageId` | Id from `WhatsAppResponse::$messageId`. |
| `status` | `MessageStatus::Sent`, `Delivered`, `Read`, `Failed`, or `null` for other values. |
| `recipient` | Recipient number. |
| `timestamp` | `CarbonImmutable`. |
| `tracking` | The value you set with `tracking()`. |
| `failed()` | `true` for failed messages. |
| `errorCode()`, `errorMessage()` | First error from Meta. |
| `errors`, `raw` | Original data. |

### Examples

Auto reply and unsubscribe:

```php
use RSE\Delivra\WhatsApp\Events\WhatsAppMessageReceived;
use RSE\Delivra\WhatsApp\Messages\ButtonsMessage;

Event::listen(function (WhatsAppMessageReceived $event) {
    $message = $event->message;
    $account = whatsapp()->account($message->account);

    $account->markAsRead($message->id);

    if ($message->buttonId() === 'wa_unsubscribe') {
        WhatsappNumber::updateOrCreate(['number' => $message->from], ['unsubscribed_at' => now()]);

        return;
    }

    $account->from($message->phoneNumberId)->to($message->from)->send(
        ButtonsMessage::make('Thanks, our team will reply soon.')->button('wa_unsubscribe', 'Unsubscribe')
    );
});
```

Track delivery with the tracking value:

```php
// send
whatsapp()->to($mobile)->send((new OrderReadyForPayment($order))->toMessage()->tracking("order:{$order->id}"));

// webhook
Event::listen(function (WhatsAppStatusUpdated $event) {
    if (! str_starts_with($event->status->tracking ?? '', 'order:')) {
        return;
    }

    $order = DistOrder::find(Str::after($event->status->tracking, 'order:'));

    match ($event->status->status) {
        MessageStatus::Delivered => $order->update(['whatsapp_delivered_at' => $event->status->timestamp]),
        MessageStatus::Read      => $order->update(['whatsapp_read_at' => $event->status->timestamp]),
        MessageStatus::Failed    => logger()->warning('WhatsApp failed', ['code' => $event->status->errorCode()]),
        default                  => null,
    };
});
```

You can also set `protected ?string $tracking` in a template class.

## Laravel notifications

Delivra registers the `whatsapp` notification channel:

```php
use Illuminate\Notifications\Notification;

class OrderReadyNotification extends Notification
{
    public function __construct(public DistOrder $order) {}

    public function via(object $notifiable): array
    {
        return ['whatsapp'];
    }

    public function toWhatsApp(object $notifiable): OrderReadyForPayment
    {
        return new OrderReadyForPayment($this->order);
    }
}
```

On the notifiable model, return one number or an array:

```php
public function routeNotificationForWhatsApp(): string|array|null
{
    return $this->mobile;
}
```

```php
$distributor->notify(new OrderReadyNotification($order));

Notification::route('whatsapp', '0501234567')->notify(new OrderReadyNotification($order));
```

The channel follows these rules:

- `toWhatsApp()` can return a template class or any message. When it returns `null`, Delivra sends nothing.
- Recipients set on the message win over `routeNotificationForWhatsApp()`.
- When the notifiable has no number, the channel sends nothing.
- The channel skips numbers that the [opt-out](#opt-out) callback blocks.
- API errors throw, so a queued notification fails and retries.
- The send events include `notifiable` and `notification`.

## Testing

`WhatsApp::fake()` replaces the manager. The fake makes no HTTP calls and skips template validation. The `whatsapp()` helper and the notification channel use the fake too.

```php
use RSE\Delivra\Facades\WhatsApp;
use RSE\Delivra\WhatsApp\Testing\SentMessage;

public function test_distributor_gets_payment_message(): void
{
    $fake = WhatsApp::fake();

    // ... code that sends

    $fake->assertTemplateSent(OrderReadyForPayment::class, fn (OrderReadyForPayment $template) => $template->order->is($order));
    $fake->assertTemplateSent('order_ready_for_payment');                    // Meta name or alias
    $fake->assertSentTo('0501234567', OrderReadyForPayment::class);
    $fake->assertSent(fn (SentMessage $sent) => $sent->account === 'sales' && $sent->to === '966501234567');
    $fake->assertSentTimes(OrderReadyForPayment::class, 1);
    $fake->assertSentCount(1);
    $fake->assertNotSent(TextMessage::class);
}

public function test_nothing_is_sent(): void
{
    WhatsApp::fake()->assertNothingSent();
}
```

| Assertion | Passes when |
|---|---|
| `assertSent($classOrNameOrCallback, ?Closure $callback = null)` | A matching message was sent. |
| `assertTemplateSent(string $template, ?Closure $callback = null)` | A template was sent. `$template` is a class, Meta name or alias. |
| `assertSentTo(string $number, string\|Closure\|null $message = null)` | A message went to the number. Delivra formats the number first. |
| `assertSentTimes($classOrNameOrCallback, int $times)` | The exact number of matching sends. |
| `assertNotSent($classOrNameOrCallback, ?Closure $callback = null)` | No matching message was sent. |
| `assertSentCount(int $count)` | Total sends. |
| `assertNothingSent()` | Nothing was sent. |
| `assertUploaded(string\|Closure\|null $filename = null)` | A file was uploaded. A closure receives each upload array. |
| `assertNothingUploaded()` | No file was uploaded. |

The fake never calls Meta for uploads. `upload()` returns `fake-media-1`, `fake-media-2` and so on. `$fake->uploads()` returns each upload as an array with `id`, `filename`, `mimeType`, `contents` and `account`.

A string matches a message class, a template class, a Meta template name or an alias. A closure as the first argument receives each `SentMessage`. The second callback receives the source first, then the `SentMessage`. The source is the template class when you sent one, otherwise the message.

`SentMessage` has `account`, `phoneNumberId`, `to`, `message`, `payload`, `source()` and `isTemplate(?string $template)`.

`$fake->sent(?Closure $filter)` returns all sends as a collection.

To test without the fake, use `Http::fake()`. Delivra calls `https://graph.facebook.com/{version}/{phone_number_id}/messages`.

## Not supported yet

- Carousel and limited time offer templates.
- Template management (create, edit, delete). The registry only reads.
- Contacts messages, call to action URL messages and Flows sent as session messages.

`whatsapp()->http()` returns a Laravel HTTP client with the base URL and token of the account. Use it for any endpoint that Delivra does not cover:

```php
whatsapp()->account('sales')->http()->get(whatsapp()->account('sales')->phoneNumberId(), ['fields' => 'quality_rating']);
```
