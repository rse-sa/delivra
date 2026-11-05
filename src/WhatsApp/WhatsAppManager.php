<?php

namespace RSE\Delivra\WhatsApp;

use Closure;
use RSE\Delivra\Events\DelivraMessageFailed;
use RSE\Delivra\Events\DelivraMessageSending;
use RSE\Delivra\Events\DelivraMessageSent;
use RSE\Delivra\Exceptions\WhatsApp\ApiErrorException;
use RSE\Delivra\Exceptions\WhatsApp\RecipientBlockedException;
use RSE\Delivra\Exceptions\WhatsApp\WhatsAppException;
use RSE\Delivra\WhatsApp\Messages\Message;
use RSE\Delivra\WhatsApp\Messages\TemplateMessage;
use RSE\Delivra\WhatsApp\Messages\TextMessage;
use RSE\Delivra\WhatsApp\Templates\Template;
use Throwable;

/**
 * @mixin Account
 */
class WhatsAppManager
{
    /** @var array<string, Account> */
    protected array $accounts = [];

    protected ?Closure $canSendCallback = null;

    public function __construct(protected array $config) {}

    public function account(?string $name = null): Account
    {
        $name ??= $this->getDefaultAccount();

        return $this->accounts[$name] ??= $this->resolve($name);
    }

    protected function resolve(string $name): Account
    {
        $config = $this->config['accounts'][$name] ?? null;

        if ($config === null) {
            throw new WhatsAppException("WhatsApp account [{$name}] is not configured.");
        }

        return new Account($this, $name, $config);
    }

    public function getDefaultAccount(): string
    {
        return $this->config['default'] ?? 'default';
    }

    /** @return list<string> */
    public function accountNames(): array
    {
        return array_keys($this->config['accounts'] ?? []);
    }

    public function findAccountByPhoneNumberId(?string $phoneNumberId): ?Account
    {
        foreach ($this->accountNames() as $name) {
            if ($phoneNumberId && $this->account($name)->ownsPhoneNumberId($phoneNumberId)) {
                return $this->account($name);
            }
        }

        return null;
    }

    public function findAccountByBusinessId(?string $businessAccountId): ?Account
    {
        foreach ($this->accountNames() as $name) {
            if ($businessAccountId && $this->account($name)->businessAccountId() === $businessAccountId) {
                return $this->account($name);
            }
        }

        return null;
    }

    /**
     * All distinct app secrets, used to verify webhook signatures.
     *
     * @return list<string>
     */
    public function appSecrets(): array
    {
        return collect($this->config['accounts'] ?? [])->pluck('app_secret')->filter()->unique()->values()->all();
    }

    public function to(string|array $recipients): PendingWhatsApp
    {
        return new PendingWhatsApp($this, null, (array) $recipients);
    }

    public function send(Message|Template $message): WhatsAppResponse|WhatsAppBatchResponse|null
    {
        return (new PendingWhatsApp($this))->send($message);
    }

    /**
     * Opt-out hook. Return false from the callback to block a recipient.
     *
     * @param  (Closure(string $recipient, Message $message, Account $account): bool)|null  $callback
     */
    public function canSendUsing(?Closure $callback): static
    {
        $this->canSendCallback = $callback;

        return $this;
    }

    public function canSendTo(string $recipient, Message $message, Account $account): bool
    {
        return $this->canSendCallback === null || (bool) call_user_func($this->canSendCallback, $recipient, $message, $account);
    }

    /**
     * Turn a class template into a message and apply the config alias.
     */
    public function prepare(Message|Template $message): Message
    {
        if ($message instanceof Template) {
            $message = $message->toMessage();
        }

        if (! $message instanceof TemplateMessage) {
            return $message;
        }

        $alias = $this->config['templates'][$message->getName()] ?? null;

        if ($alias !== null && $message->getAlias() === null) {
            $message->alias($message->getName())->name($alias['name'] ?? $message->getName());

            $message->language($message->getLanguage() ?? ($alias['language'] ?? null));

            if (! $message->getAccount() && isset($alias['account'])) {
                $message->account($alias['account']);
            }

            if (! $message->getFrom() && isset($alias['from'])) {
                $message->from($alias['from']);
            }
        }

        return $message->language($message->getLanguage() ?? $this->config['default_language'] ?? 'en_US');
    }

    /**
     * Pick the account and number for a message, unless one was forced.
     */
    public function accountFor(Message $message, ?Account $account = null): Account
    {
        $account ??= $this->account($message->getAccount());

        if ($message->getFrom() && ! $account->hasExplicitNumber()) {
            $account = $account->from($message->getFrom());
        }

        return $account;
    }

    /**
     * Send one prepared message to one recipient with events, opt-out and validation.
     *
     * Returns null when a sending listener returned false.
     */
    public function sendTo(Account $account, string $recipient, Message $message, mixed $notifiable = null, mixed $notification = null): ?WhatsAppResponse
    {
        $recipient = $this->formatNumber($recipient);

        if (! $this->canSendTo($recipient, $message, $account)) {
            throw new RecipientBlockedException("WhatsApp recipient [{$recipient}] is blocked from receiving messages.");
        }

        if ($message instanceof TemplateMessage && ($this->config['validate_templates'] ?? false)) {
            $account->templates()->validate($message);
        }

        $payload = $this->payloadFor($recipient, $message);
        $body    = $message->description();

        $results = event(new DelivraMessageSending('whatsapp', $recipient, $body, $payload, $account->name, null, $notifiable, $notification, $message));

        if (in_array(false, (array) $results, true)) {
            return null;
        }

        try {
            $response = $this->deliver($account, $payload, $message);
        } catch (Throwable $e) {
            event(new DelivraMessageFailed('whatsapp', $recipient, $body, $e->getMessage(), $e, $notifiable, $notification, $message));

            throw $e;
        }

        event(new DelivraMessageSent('whatsapp', $recipient, $body, $response->messageId, $response, $notifiable, $notification, $message));

        return $response;
    }

    public function payloadFor(string $recipient, Message $message): array
    {
        return [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $recipient,
        ] + $message->toPayload();
    }

    /**
     * The only place that calls the Cloud API to send. The fake overrides it.
     */
    public function deliver(Account $account, array $payload, Message $message): WhatsAppResponse
    {
        $response = $account->http()->post($account->phoneNumberId() . '/messages', $payload);

        if ($response->failed()) {
            throw ApiErrorException::fromResponse($response);
        }

        return WhatsAppResponse::fromApi($account->name, $account->phoneNumberId(), $payload['to'], $response->json() ?? []);
    }

    /**
     * Digits only, with the default country code for local numbers.
     */
    public function formatNumber(string $number): string
    {
        $number = preg_replace('/\D+/', '', $number);

        if (str_starts_with($number, '00')) {
            return substr($number, 2);
        }

        $countryCode = $this->config['default_country_code'] ?? null;

        if ($countryCode && str_starts_with($number, '0')) {
            return $countryCode . ltrim($number, '0');
        }

        return $number;
    }

    public function text(string $body): TextMessage
    {
        return TextMessage::make($body);
    }

    public function template(string $name, ?string $language = null): TemplateMessage
    {
        return TemplateMessage::make($name, $language);
    }

    public function getConfig(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->config : data_get($this->config, $key, $default);
    }

    public function __call(string $method, array $parameters): mixed
    {
        return $this->account()->{$method}(...$parameters);
    }
}
