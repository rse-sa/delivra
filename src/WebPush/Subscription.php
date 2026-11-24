<?php

namespace RSE\Delivra\WebPush;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * One browser subscription: where to post and the keys to encrypt the payload with.
 */
class Subscription
{
    public const DEFAULT_ENCODING = 'aes128gcm';

    /**
     * @param  mixed  $source  The model or array it was built from, so expired ones can be deleted.
     */
    public function __construct(
        public readonly string $endpoint,
        public readonly ?string $publicKey = null,
        public readonly ?string $authToken = null,
        public readonly string $contentEncoding = self::DEFAULT_ENCODING,
        public readonly mixed $source = null,
    ) {}

    /**
     * A model with endpoint, public_key, auth_token and content_encoding columns,
     * the browser's PushSubscription JSON, or a Subscription.
     */
    public static function from(mixed $subscription): static
    {
        if ($subscription instanceof static) {
            return $subscription;
        }

        if (! is_array($subscription) && ! is_object($subscription)) {
            throw new InvalidArgumentException('A web push subscription must be a model, an array or a Subscription.');
        }

        $endpoint = data_get($subscription, 'endpoint');

        if (! is_string($endpoint) || trim($endpoint) === '') {
            throw new InvalidArgumentException('A web push subscription needs an endpoint.');
        }

        $encoding = data_get($subscription, 'content_encoding') ?? data_get($subscription, 'contentEncoding');

        return new static(
            $endpoint,
            data_get($subscription, 'public_key') ?? data_get($subscription, 'keys.p256dh') ?? data_get($subscription, 'publicKey'),
            data_get($subscription, 'auth_token') ?? data_get($subscription, 'keys.auth') ?? data_get($subscription, 'authToken'),
            ($encoding instanceof BackedEnum ? $encoding->value : $encoding) ?: self::DEFAULT_ENCODING,
            $subscription,
        );
    }

    /**
     * One subscription or many, without duplicate endpoints.
     *
     * @return list<static>
     */
    public static function list(mixed $subscriptions): array
    {
        if ($subscriptions === null || $subscriptions === []) {
            return [];
        }

        if (static::isSingle($subscriptions)) {
            $subscriptions = [$subscriptions];
        }

        return collect($subscriptions)
            ->map(fn ($subscription) => static::from($subscription))
            ->unique('endpoint')
            ->values()
            ->all();
    }

    protected static function isSingle(mixed $subscription): bool
    {
        return $subscription instanceof static
            || $subscription instanceof Model
            || (is_array($subscription) && array_key_exists('endpoint', $subscription));
    }

    public function model(): ?Model
    {
        return $this->source instanceof Model ? $this->source : null;
    }

    /**
     * Safe for logs and events: the push service host and the end of the endpoint.
     */
    public function masked(): string
    {
        $host = parse_url($this->endpoint, PHP_URL_HOST) ?: 'endpoint';

        return $host . '/...' . substr($this->endpoint, -6);
    }
}
