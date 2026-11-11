<?php

namespace RSE\Delivra\Fcm\Messages;

use BackedEnum;
use Closure;
use DateTimeInterface;
use RSE\Delivra\Exceptions\Fcm\InvalidMessageException;
use Stringable;

/**
 * One FCM v1 message. The sender adds the token, topic or condition.
 */
class FcmMessage
{
    protected ?string $title = null;

    protected ?string $body = null;

    protected ?string $image = null;

    /** @var array<string, string> */
    protected array $data = [];

    protected ?AndroidConfig $android = null;

    protected ?ApnsConfig $apns = null;

    protected ?WebpushConfig $webpush = null;

    protected ?int $ttl = null;

    protected ?string $collapseKey = null;

    protected ?string $analyticsLabel = null;

    protected array $with = [];

    protected ?string $project = null;

    protected ?string $topic = null;

    protected ?string $condition = null;

    protected bool $validateOnly = false;

    public function __construct(?string $title = null, ?string $body = null)
    {
        $this->title = $title;
        $this->body  = $body;
    }

    public static function make(?string $title = null, ?string $body = null): static
    {
        return new static($title, $body);
    }

    /**
     * Data only, delivered in the background: no tray notification.
     */
    public static function silent(array $data = []): static
    {
        return (new static)
            ->data($data)
            ->android(fn (AndroidConfig $android) => $android->priority('normal'))
            ->apns(fn (ApnsConfig $apns) => $apns->pushType('background')->priority(5)->contentAvailable());
    }

    public function title(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function body(?string $body): static
    {
        $this->body = $body;

        return $this;
    }

    public function image(?string $url): static
    {
        $this->image = $url;

        return $this;
    }

    /**
     * Key/value data for the app. Values become strings: null is dropped,
     * bools become "true"/"false", arrays become JSON.
     */
    public function data(array $data): static
    {
        $data = static::stringifyData($data);

        foreach (array_keys($data) as $key) {
            if (in_array($key, ['from', 'notification', 'message_type'], true) || str_starts_with($key, 'google') || str_starts_with($key, 'gcm')) {
                throw new InvalidMessageException("FCM data key [{$key}] is reserved.");
            }
        }

        $this->data = array_merge($this->data, $data);

        return $this;
    }

    /**
     * @param  (Closure(AndroidConfig): mixed)|AndroidConfig|array  $config
     */
    public function android(Closure|AndroidConfig|array $config): static
    {
        $this->android = $this->configure($this->android ?? new AndroidConfig, $config);

        return $this;
    }

    /**
     * @param  (Closure(ApnsConfig): mixed)|ApnsConfig|array  $config
     */
    public function apns(Closure|ApnsConfig|array $config): static
    {
        $this->apns = $this->configure($this->apns ?? new ApnsConfig, $config);

        return $this;
    }

    /**
     * @param  (Closure(WebpushConfig): mixed)|WebpushConfig|array  $config
     */
    public function webpush(Closure|WebpushConfig|array $config): static
    {
        $this->webpush = $this->configure($this->webpush ?? new WebpushConfig, $config);

        return $this;
    }

    /**
     * @template T of PlatformConfig
     *
     * @param  T  $current
     * @return T
     */
    protected function configure(PlatformConfig $current, Closure|PlatformConfig|array $config): PlatformConfig
    {
        if ($config instanceof PlatformConfig) {
            return $config;
        }

        if (is_array($config)) {
            return $current->with($config);
        }

        $config($current);

        return $current;
    }

    /**
     * How long FCM, Apple and browsers keep the message for an offline device.
     */
    public function ttl(int $seconds): static
    {
        $this->ttl = $seconds;

        return $this;
    }

    /**
     * Newer messages with the same key replace older pending ones.
     */
    public function collapseKey(string $key): static
    {
        $this->collapseKey = $key;

        return $this;
    }

    public function analyticsLabel(string $label): static
    {
        $this->analyticsLabel = $label;

        return $this;
    }

    /**
     * Raw fields merged into the FCM "message" object.
     */
    public function with(array $fields): static
    {
        $this->with = array_replace_recursive($this->with, $fields);

        return $this;
    }

    /**
     * Firebase project name from config.
     */
    public function project(?string $project): static
    {
        $this->project = $project;

        return $this;
    }

    /**
     * Send to a topic instead of the notifiable's tokens.
     */
    public function topic(?string $topic): static
    {
        $this->topic = $topic;

        return $this;
    }

    /**
     * Send to a topic condition, e.g. "'news' in topics && 'ar' in topics".
     */
    public function condition(?string $condition): static
    {
        $this->condition = $condition;

        return $this;
    }

    /**
     * Ask FCM to check the message without delivering it.
     */
    public function validateOnly(bool $validateOnly = true): static
    {
        $this->validateOnly = $validateOnly;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    /** @return array<string, string> */
    public function getData(): array
    {
        return $this->data;
    }

    public function getProject(): ?string
    {
        return $this->project;
    }

    public function getTopic(): ?string
    {
        return $this->topic;
    }

    public function getCondition(): ?string
    {
        return $this->condition;
    }

    public function isValidateOnly(): bool
    {
        return $this->validateOnly;
    }

    public function description(): string
    {
        return $this->title ?? $this->body ?? '[data]';
    }

    /**
     * The FCM "message" object without a target. Build it at send time,
     * since apns-expiration is an absolute time.
     */
    public function toPayload(): array
    {
        $message = [];

        $notification = array_filter(['title' => $this->title, 'body' => $this->body, 'image' => $this->image], fn ($value) => $value !== null);

        if ($notification !== []) {
            $message['notification'] = $notification;
        }

        if ($this->data !== []) {
            $message['data'] = $this->data;
        }

        foreach (['android' => $this->androidPayload(), 'apns' => $this->apnsPayload(), 'webpush' => $this->webpushPayload()] as $key => $payload) {
            if ($payload !== []) {
                $message[$key] = $payload;
            }
        }

        if ($this->analyticsLabel !== null) {
            $message['fcm_options']['analytics_label'] = $this->analyticsLabel;
        }

        return array_replace_recursive($message, $this->with);
    }

    protected function androidPayload(): array
    {
        $shortcuts = array_filter([
            'ttl'          => $this->ttl !== null ? "{$this->ttl}s" : null,
            'collapse_key' => $this->collapseKey,
        ], fn ($value) => $value !== null);

        return array_replace_recursive($shortcuts, $this->android?->toArray() ?? []);
    }

    protected function apnsPayload(): array
    {
        $headers = array_filter([
            'apns-expiration'  => $this->ttl !== null ? (string) ($this->ttl === 0 ? 0 : time() + $this->ttl) : null,
            'apns-collapse-id' => $this->collapseKey,
        ], fn ($value) => $value !== null);

        return array_replace_recursive($headers ? ['headers' => $headers] : [], $this->apns?->toArray() ?? []);
    }

    protected function webpushPayload(): array
    {
        $headers = $this->ttl !== null ? ['headers' => ['TTL' => (string) $this->ttl]] : [];

        return array_replace_recursive($headers, $this->webpush?->toArray() ?? []);
    }

    /**
     * FCM data values must be strings.
     *
     * @return array<string, string>
     */
    public static function stringifyData(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            $result[(string) $key] = match (true) {
                is_string($value)                   => $value,
                is_bool($value)                     => $value ? 'true' : 'false',
                $value instanceof BackedEnum        => (string) $value->value,
                $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
                $value instanceof Stringable        => (string) $value,
                is_scalar($value)                   => (string) $value,
                default                             => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            };
        }

        return $result;
    }
}
