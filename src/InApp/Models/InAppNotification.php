<?php

namespace RSE\Delivra\InApp\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RSE\Delivra\InApp\InAppRenderer;

/**
 * @property string $id
 * @property string|null $type
 * @property string|null $category
 * @property array $payload
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property \Illuminate\Support\Carbon|null $archived_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class InAppNotification extends Model
{
    use HasUlids;
    use MassPrunable;

    protected $guarded = [];

    protected $casts = [
        'payload'     => 'array',
        'read_at'     => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('delivra.in_app.table') ?: parent::getTable();
    }

    public function getConnectionName(): ?string
    {
        return config('delivra.in_app.connection') ?: parent::getConnectionName();
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The tenant. Named scopeable() because scope() reads like an Eloquent query scope.
     */
    public function scopeable(): MorphTo
    {
        return $this->morphTo('scopeable', 'scope_type', 'scope_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    public function scopeRead(Builder $query): void
    {
        $query->whereNotNull('read_at');
    }

    public function scopeArchived(Builder $query): void
    {
        $query->whereNotNull('archived_at');
    }

    public function scopeWithoutArchived(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * @param  string|list<string>  $categories
     */
    public function scopeCategory(Builder $query, string|array $categories): void
    {
        $query->whereIn('category', (array) $categories);
    }

    public function scopeForScope(Builder $query, Model $scope): void
    {
        $query->where('scope_type', $scope->getMorphClass())->where('scope_id', (string) $scope->getKey());
    }

    public function scopeForSubject(Builder $query, Model $subject): void
    {
        $query->where('subject_type', $subject->getMorphClass())->where('subject_id', (string) $subject->getKey());
    }

    /**
     * Rows of this scope plus system-wide rows. A null scope filters nothing.
     */
    public function scopeVisibleInScope(Builder $query, ?Model $scope): void
    {
        if ($scope === null) {
            return;
        }

        $query->where(fn (Builder $query) => $query
            ->where(fn (Builder $query) => $query->forScope($scope))
            ->orWhereNull('scope_type'));
    }

    public function markAsRead(): static
    {
        return $this->stamp('read_at', now());
    }

    public function markAsUnread(): static
    {
        return $this->stamp('read_at', null);
    }

    public function archive(): static
    {
        return $this->stamp('archived_at', now());
    }

    public function unarchive(): static
    {
        return $this->stamp('archived_at', null);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Title, body, buttons and links in this locale. Defaults to the app locale.
     */
    public function render(?string $locale = null, ?string $timezone = null): array
    {
        return app(InAppRenderer::class)->render($this, $locale, $timezone);
    }

    /**
     * Rows older than delivra.in_app.prune_after_days. Null keeps everything.
     * Schedule: php artisan model:prune --model="RSE\Delivra\InApp\Models\InAppNotification"
     */
    public function prunable(): Builder
    {
        $days = config('delivra.in_app.prune_after_days');

        return $days === null
            ? static::query()->whereRaw('1 = 0')
            : static::query()->where('created_at', '<', now()->subDays((int) $days));
    }

    protected function stamp(string $column, mixed $value): static
    {
        // no write when it is in that state already
        if (($this->{$column} === null) !== ($value === null)) {
            $this->forceFill([$column => $value])->save();
        }

        return $this;
    }
}
