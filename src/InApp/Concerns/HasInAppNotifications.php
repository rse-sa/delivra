<?php

namespace RSE\Delivra\InApp\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RSE\Delivra\InApp\InAppManager;
use RSE\Delivra\InApp\Models\InAppNotification;

/**
 * Bell notifications of a notifiable model. Newest first.
 *
 * inAppNotifications() is every row of the receiver, in every tenant scope.
 * Bells in multi-tenant apps use visibleInAppNotifications(), which follows InApp::scopeUsing().
 */
trait HasInAppNotifications
{
    public function inAppNotifications(): MorphMany
    {
        return $this->morphMany(static::inAppNotificationModel(), 'notifiable')->latest()->orderByDesc('id');
    }

    public function unreadInAppNotifications(): MorphMany
    {
        return $this->inAppNotifications()->unread();
    }

    /**
     * Rows of the current tenant scope plus system-wide rows.
     * Without an argument the scope comes from InApp::scopeUsing(). Pass null to skip the filter.
     */
    public function visibleInAppNotifications(?Model $scope = null): MorphMany
    {
        $scope = func_num_args() > 0 ? $scope : InAppManager::resolveScope($this);

        return $this->inAppNotifications()->visibleInScope($scope);
    }

    /**
     * Unread and not archived, in the current tenant scope.
     */
    public function unreadInAppNotificationsCount(?string $category = null, ?Model $scope = null): int
    {
        return $this->visibleInAppNotifications(...(func_num_args() > 1 ? [$scope] : []))
            ->reorder()
            ->unread()
            ->withoutArchived()
            ->when($category, fn ($query) => $query->category($category))
            ->count();
    }

    /**
     * @return int how many rows changed
     */
    public function markInAppNotificationsAsRead(?string $category = null, ?Model $scope = null): int
    {
        // no ORDER BY in an UPDATE: not every database takes it
        return $this->visibleInAppNotifications(...(func_num_args() > 1 ? [$scope] : []))
            ->reorder()
            ->unread()
            ->when($category, fn ($query) => $query->category($category))
            ->update(['read_at' => now()]);
    }

    /**
     * @return class-string<InAppNotification>
     */
    public static function inAppNotificationModel(): string
    {
        return config('delivra.in_app.model', InAppNotification::class);
    }
}
