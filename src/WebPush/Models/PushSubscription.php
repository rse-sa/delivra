<?php

namespace RSE\Delivra\WebPush\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int|string $subscribable_id
 * @property string $subscribable_type
 * @property string $endpoint
 * @property string|null $public_key
 * @property string|null $auth_token
 * @property string|null $content_encoding
 */
class PushSubscription extends Model
{
    /**
     * Some push services (Microsoft WNS) send endpoints over 500 characters.
     * The ascii column keeps the unique index inside MySQL limits.
     */
    public const ENDPOINT_MAX_LENGTH = 1024;

    protected $fillable = [
        'endpoint',
        'public_key',
        'auth_token',
        'content_encoding',
    ];

    public function getTable(): string
    {
        return $this->table ?? config('delivra.webpush.subscriptions.table', 'push_subscriptions');
    }

    public function getConnectionName(): ?string
    {
        return $this->connection ?? config('delivra.webpush.subscriptions.connection');
    }

    public function subscribable(): MorphTo
    {
        return $this->morphTo();
    }

    public static function findByEndpoint(string $endpoint): ?static
    {
        return static::query()->firstWhere('endpoint', $endpoint);
    }
}
