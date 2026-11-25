<?php

namespace RSE\Delivra\Facades;

use Illuminate\Support\Facades\Facade;
use RSE\Delivra\InApp\InAppManager;
use RSE\Delivra\InApp\Testing\InAppFake;

/**
 * @method static \RSE\Delivra\InApp\Messages\InAppMessage message(string|\RSE\Delivra\InApp\Values\Value|null $title = null, array $params = [])
 * @method static \RSE\Delivra\InApp\Values\Trans trans(string $key, array $params = [])
 * @method static \RSE\Delivra\InApp\Values\Choice choice(string $key, int|float $count, array $params = [])
 * @method static \RSE\Delivra\InApp\Values\Date date(\DateTimeInterface|string|null $value, ?string $format = null)
 * @method static \RSE\Delivra\InApp\Values\Date datetime(\DateTimeInterface|string|null $value, ?string $format = null)
 * @method static \RSE\Delivra\InApp\Values\Text text(string $text)
 * @method static \RSE\Delivra\InApp\Models\InAppNotification|null send(object $notifiable, \RSE\Delivra\InApp\Messages\InAppMessage $message, ?\Illuminate\Notifications\Notification $notification = null)
 * @method static void routes(array $options = [])
 *
 * @see InAppManager
 */
class InApp extends Facade
{
    public static function fake(): InAppFake
    {
        $fake = new InAppFake(static::getFacadeApplication()['config']->get('delivra.in_app', []));

        static::swap($fake);

        return $fake;
    }

    /**
     * Static on the manager, so the facade passes them through without resolving one.
     */
    public static function renderUsing(string $typeOrCategory, ?callable $hook): void
    {
        InAppManager::renderUsing($typeOrCategory, $hook);
    }

    public static function scopeUsing(?callable $resolver): void
    {
        InAppManager::scopeUsing($resolver);
    }

    public static function timezoneUsing(?callable $resolver): void
    {
        InAppManager::timezoneUsing($resolver);
    }

    protected static function getFacadeAccessor(): string
    {
        return 'delivra-in-app';
    }
}
