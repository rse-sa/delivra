<?php

namespace RSE\Delivra\InApp;

use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;
use RSE\Delivra\Events\DelivraMessageSending;
use RSE\Delivra\Events\DelivraMessageSent;
use RSE\Delivra\InApp\Events\BroadcastInAppNotificationCreated;
use RSE\Delivra\InApp\Events\InAppNotificationCreated;
use RSE\Delivra\InApp\Http\Controllers\InAppNotificationController;
use RSE\Delivra\InApp\Messages\InAppMessage;
use RSE\Delivra\InApp\Models\InAppNotification;
use RSE\Delivra\InApp\Values\Choice;
use RSE\Delivra\InApp\Values\Date;
use RSE\Delivra\InApp\Values\Text;
use RSE\Delivra\InApp\Values\Trans;
use RSE\Delivra\InApp\Values\Translations;

/**
 * Stores bell notifications and holds the app's hooks.
 * Hooks are static so InApp::fake() keeps the ones a service provider set.
 */
class InAppManager
{
    /** @var array<string, callable> type or category => hook */
    protected static array $renderHooks = [];

    /** @var (callable(object, ?Notification): ?Model)|null */
    protected static $scopeResolver = null;

    /** @var (callable(Model): ?string)|null */
    protected static $timezoneResolver = null;

    public function __construct(protected array $config = []) {}

    public function message(string|Values\Value|null $title = null, array $params = []): InAppMessage
    {
        return InAppMessage::make($title, $params);
    }

    public function trans(string $key, array $params = []): Trans
    {
        return new Trans($key, $params);
    }

    public function choice(string $key, int|float $count, array $params = []): Choice
    {
        return new Choice($key, $count, $params);
    }

    public function date(DateTimeInterface|string|null $value, ?string $format = null): Date
    {
        return Date::date($value, $format);
    }

    public function datetime(DateTimeInterface|string|null $value, ?string $format = null): Date
    {
        return Date::datetime($value, $format);
    }

    /**
     * Literal text, shown the same in every locale.
     */
    public function text(string $text): Text
    {
        return new Text($text);
    }

    /**
     * Text per locale, the reader's one is shown.
     *
     * @param  array<string, string>  $translations  locale => text
     */
    public function translations(array $translations): Translations
    {
        return new Translations($translations);
    }

    /**
     * Every translation of a translatable attribute (spatie/laravel-translatable).
     * A plain attribute is the same text in every locale.
     */
    public function translated(Model $model, string $attribute): Translations|Text
    {
        $translatable = method_exists($model, 'getTranslations')
            && (! method_exists($model, 'isTranslatableAttribute') || $model->isTranslatableAttribute($attribute));

        return $translatable
            ? new Translations($model->getTranslations($attribute))
            : new Text((string) $model->getAttribute($attribute));
    }

    /**
     * Store a message without a notification class. Null when nothing was stored.
     */
    public function send(object $notifiable, InAppMessage $message, ?Notification $notification = null): ?InAppNotification
    {
        // on-demand notifiables have no row to own a notification
        if (! $notifiable instanceof Model) {
            return null;
        }

        $payload   = $message->toArray();
        $body      = $message->description();
        $recipient = $notifiable->getMorphClass() . ':' . $notifiable->getKey();

        // halt mode: a listener returning false cancels, as on every delivra channel
        if (event(new DelivraMessageSending('in_app', $recipient, $body, $payload, null, null, $notifiable, $notification, $message), [], true) === false) {
            return null;
        }

        $scope = $message->hasChosenScope() ? $message->getScope() : static::resolveScope($notifiable, $notification);

        $model = $this->store($this->newNotification($notifiable, $message, $payload, $scope, $notification), $message);

        event(new InAppNotificationCreated($model, $notifiable, $notification, $message));
        event(new DelivraMessageSent('in_app', $recipient, $body, $model->getKey(), $model, $notifiable, $notification, $message));

        if ($model->exists && ($this->config['broadcast'] ?? false)) {
            $model->getConnection()->afterCommit(fn () => event(new BroadcastInAppNotificationCreated($model, $notifiable, $notification)));
        }

        return $model;
    }

    /**
     * The only place that writes. The fake overrides it.
     */
    public function store(InAppNotification $notification, InAppMessage $message): InAppNotification
    {
        $notification->save();

        return $notification;
    }

    public function newNotification(Model $notifiable, InAppMessage $message, array $payload, ?Model $scope, ?Notification $notification = null): InAppNotification
    {
        $class = $this->config['model'] ?? InAppNotification::class;

        /** @var InAppNotification $model */
        $model = new $class;

        $model->forceFill([
            'type'            => $notification ? $notification::class : null,
            'notification_id' => $notification?->id,
            'category'        => $message->getCategory(),
            'payload'         => $payload,
        ]);

        $model->notifiable()->associate($notifiable);

        foreach (['scope' => $scope, 'subject' => $message->getSubject(), 'causer' => $message->getCauser()] as $name => $related) {
            $model->forceFill([
                "{$name}_type" => $related?->getMorphClass(),
                "{$name}_id"   => $related === null ? null : (string) $related->getKey(),
            ]);
        }

        return $model;
    }

    /**
     * Register the JSON routes inside the app's own route group.
     */
    public function routes(array $options = []): void
    {
        $config = $this->config['routes'] ?? [];

        Route::group([
            'prefix'     => $options['prefix'] ?? $config['prefix'] ?? 'in-app-notifications',
            'middleware' => $options['middleware'] ?? $config['middleware'] ?? ['api', 'auth:sanctum'],
            'as'         => $options['name'] ?? $config['name'] ?? 'delivra.in-app.',
        ], function () {
            Route::get('/', [InAppNotificationController::class, 'index'])->name('index');
            Route::get('unread-count', [InAppNotificationController::class, 'unreadCount'])->name('unread-count');
            Route::post('read-all', [InAppNotificationController::class, 'readAll'])->name('read-all');
            Route::post('{notification}/read', [InAppNotificationController::class, 'read'])->name('read');
            Route::post('{notification}/unread', [InAppNotificationController::class, 'unread'])->name('unread');
            Route::post('{notification}/archive', [InAppNotificationController::class, 'archive'])->name('archive');
            Route::post('{notification}/unarchive', [InAppNotificationController::class, 'unarchive'])->name('unarchive');
            Route::delete('{notification}', [InAppNotificationController::class, 'destroy'])->name('destroy');
        });
    }

    /**
     * Change the rendered array of one notification type (class name) or one category.
     * The hook gets ($notification, $rendered, $locale) and returns the array.
     * One hook per key: registering again replaces it, so a provider booted twice does not stack hooks.
     */
    public static function renderUsing(string $typeOrCategory, ?callable $hook): void
    {
        if ($hook === null) {
            unset(static::$renderHooks[$typeOrCategory]);

            return;
        }

        static::$renderHooks[$typeOrCategory] = $hook;
    }

    /**
     * Type hooks run before category hooks.
     */
    public static function applyRenderHooks(InAppNotification $notification, array $rendered, string $locale): array
    {
        foreach (array_unique(array_filter([$notification->type, $notification->category])) as $key) {
            if (isset(static::$renderHooks[$key])) {
                $rendered = (static::$renderHooks[$key])($notification, $rendered, $locale);
            }
        }

        return $rendered;
    }

    /**
     * The tenant of a receiver. Gets ($notifiable, $notification) when sending, ($notifiable, null) when reading.
     */
    public static function scopeUsing(?callable $resolver): void
    {
        static::$scopeResolver = $resolver;
    }

    public static function resolveScope(object $notifiable, ?Notification $notification = null): ?Model
    {
        return static::$scopeResolver ? (static::$scopeResolver)($notifiable, $notification) : null;
    }

    /**
     * The reader's timezone for datetimes. Null from the resolver falls back to the app timezone.
     */
    public static function timezoneUsing(?callable $resolver): void
    {
        static::$timezoneResolver = $resolver;
    }

    public static function hasTimezoneResolver(): bool
    {
        return static::$timezoneResolver !== null;
    }

    public static function resolveTimezone(?Model $notifiable): string
    {
        $timezone = $notifiable && static::$timezoneResolver ? (static::$timezoneResolver)($notifiable) : null;

        return $timezone ?: (string) config('app.timezone', 'UTC');
    }

    public static function flushHooks(): void
    {
        static::$renderHooks      = [];
        static::$scopeResolver    = null;
        static::$timezoneResolver = null;
    }

    public function getConfig(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->config : data_get($this->config, $key, $default);
    }
}
