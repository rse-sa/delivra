<?php

namespace RSE\Delivra\WebPush\Messages;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use RSE\Delivra\Exceptions\WebPush\InvalidMessageException;

/**
 * The payload is what the service worker gets: the title plus the options of showNotification().
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/ServiceWorkerRegistration/showNotification
 *
 * @implements Arrayable<string, mixed>
 */
class WebPushMessage implements Arrayable, JsonSerializable
{
    public const URGENCIES = ['very-low', 'low', 'normal', 'high'];

    protected ?string $icon = null;

    protected ?string $badge = null;

    protected ?string $image = null;

    protected ?string $lang = null;

    protected ?string $dir = null;

    protected ?string $tag = null;

    protected ?bool $renotify = null;

    protected ?bool $requireInteraction = null;

    protected ?bool $silent = null;

    protected ?int $timestamp = null;

    /** @var list<int> */
    protected array $vibrate = [];

    /** @var list<array{action: string, title: string, icon?: string}> */
    protected array $actions = [];

    // protected on purpose: loggers read a public $data as the whole message
    protected array $data = [];

    protected ?int $ttl = null;

    protected ?string $urgency = null;

    protected ?string $topic = null;

    public function __construct(protected ?string $title = null, protected ?string $body = null) {}

    public static function make(?string $title = null, ?string $body = null): static
    {
        return new static($title, $body);
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

    public function icon(?string $url): static
    {
        $this->icon = $url;

        return $this;
    }

    public function badge(?string $url): static
    {
        $this->badge = $url;

        return $this;
    }

    public function image(?string $url): static
    {
        $this->image = $url;

        return $this;
    }

    public function lang(?string $lang): static
    {
        $this->lang = $lang;

        return $this;
    }

    /**
     * @param  'auto'|'ltr'|'rtl'|null  $dir
     */
    public function dir(?string $dir): static
    {
        $this->dir = $dir;

        return $this;
    }

    /**
     * A new push with the same tag replaces the old one on the device.
     */
    public function tag(?string $tag): static
    {
        $this->tag = $tag;

        return $this;
    }

    public function renotify(bool $renotify = true): static
    {
        $this->renotify = $renotify;

        return $this;
    }

    public function requireInteraction(bool $requireInteraction = true): static
    {
        $this->requireInteraction = $requireInteraction;

        return $this;
    }

    public function silent(bool $silent = true): static
    {
        $this->silent = $silent;

        return $this;
    }

    /**
     * Milliseconds since the epoch, as the Notification API expects.
     */
    public function timestamp(?int $milliseconds): static
    {
        $this->timestamp = $milliseconds;

        return $this;
    }

    /**
     * @param  list<int>  $pattern
     */
    public function vibrate(array $pattern): static
    {
        $this->vibrate = $pattern;

        return $this;
    }

    public function action(string $title, string $action, ?string $icon = null): static
    {
        $this->actions[] = array_filter(['action' => $action, 'title' => $title, 'icon' => $icon], fn ($value) => $value !== null);

        return $this;
    }

    public function data(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * The page to open on click. The delivra service worker opens same-origin urls only.
     */
    public function url(string $url): static
    {
        $this->data['url'] = $url;

        return $this;
    }

    /**
     * Seconds the push service keeps the message while the browser is offline.
     */
    public function ttl(int $seconds): static
    {
        $this->ttl = max(0, $seconds);

        return $this;
    }

    /**
     * @param  'very-low'|'low'|'normal'|'high'  $urgency
     */
    public function urgency(string $urgency): static
    {
        if (! in_array($urgency, self::URGENCIES, true)) {
            throw new InvalidMessageException("Web push urgency [{$urgency}] must be one of: " . implode(', ', self::URGENCIES) . '.');
        }

        $this->urgency = $urgency;

        return $this;
    }

    /**
     * A waiting message with the same topic replaces the older one at the push service.
     */
    public function topic(?string $topic): static
    {
        if ($topic !== null && ! preg_match('/^[A-Za-z0-9_-]{1,32}$/', $topic)) {
            throw new InvalidMessageException("Web push topic [{$topic}] must be 1 to 32 letters, digits, - or _.");
        }

        $this->topic = $topic;

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

    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Options for the push service: TTL, urgency and topic. Unset ones use the config defaults.
     *
     * @return array<string, int|string>
     */
    public function getOptions(): array
    {
        return array_filter(['TTL' => $this->ttl, 'urgency' => $this->urgency, 'topic' => $this->topic], fn ($value) => $value !== null);
    }

    public function description(): string
    {
        return $this->title ?? $this->body ?? '[push]';
    }

    public function toArray(): array
    {
        return array_filter([
            'title'              => $this->title,
            'body'               => $this->body,
            'icon'               => $this->icon,
            'badge'              => $this->badge,
            'image'              => $this->image,
            'lang'               => $this->lang,
            'dir'                => $this->dir,
            'tag'                => $this->tag,
            'renotify'           => $this->renotify,
            'requireInteraction' => $this->requireInteraction,
            'silent'             => $this->silent,
            'timestamp'          => $this->timestamp,
            'vibrate'            => $this->vibrate,
            'actions'            => $this->actions,
            'data'               => $this->data,
        ], fn ($value) => $value !== null && $value !== []);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * The JSON the browser decrypts and hands to the service worker.
     */
    public function toPayload(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
