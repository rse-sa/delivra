<?php

namespace RSE\Delivra\WebPush\Concerns;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RSE\Delivra\WebPush\Models\PushSubscription;

/**
 * Stores the browser subscriptions of a notifiable model.
 * Optional: the channel works with any routeNotificationForWebPush().
 */
trait HasPushSubscriptions
{
    public function pushSubscriptions(): MorphMany
    {
        return $this->morphMany(static::pushSubscriptionModel(), 'subscribable');
    }

    /**
     * Save the subscription. A device that belonged to another user moves to this one.
     */
    public function updatePushSubscription(string $endpoint, ?string $key = null, ?string $token = null, ?string $contentEncoding = null): Model
    {
        $subscription = static::pushSubscriptionModel()::query()->firstWhere('endpoint', $endpoint);

        if ($subscription && $this->ownsPushSubscription($subscription)) {
            $subscription->update(['public_key' => $key, 'auth_token' => $token, 'content_encoding' => $contentEncoding]);

            return $subscription;
        }

        $subscription?->delete();

        return $this->pushSubscriptions()->create([
            'endpoint'         => $endpoint,
            'public_key'       => $key,
            'auth_token'       => $token,
            'content_encoding' => $contentEncoding,
        ]);
    }

    public function ownsPushSubscription(Model $subscription): bool
    {
        return (string) $subscription->subscribable_id === (string) $this->getKey()
            && $subscription->subscribable_type === $this->getMorphClass();
    }

    public function deletePushSubscription(string $endpoint): void
    {
        $this->pushSubscriptions()->where('endpoint', $endpoint)->delete();
    }

    /**
     * A query, not the relation property: lazy loading may be off.
     */
    public function routeNotificationForWebPush(): Collection
    {
        return $this->pushSubscriptions()->get();
    }

    /**
     * @return class-string<Model>
     */
    public static function pushSubscriptionModel(): string
    {
        return config('delivra.webpush.subscriptions.model') ?: PushSubscription::class;
    }
}
