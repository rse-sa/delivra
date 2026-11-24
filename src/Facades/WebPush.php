<?php

namespace RSE\Delivra\Facades;

use Illuminate\Support\Facades\Facade;
use RSE\Delivra\WebPush\Testing\WebPushFake;
use RSE\Delivra\WebPush\WebPushManager;

/**
 * @method static \RSE\Delivra\WebPush\PendingWebPush to(mixed $subscriptions)
 * @method static \RSE\Delivra\WebPush\Messages\WebPushMessage message(?string $title = null, ?string $body = null)
 * @method static string|null publicKey()
 * @method static bool configured()
 * @method static array createVapidKeys()
 * @method static \RSE\Delivra\WebPush\WebPushManager usingHttpClient(?\Psr\Http\Client\ClientInterface $client)
 *
 * @see WebPushManager
 */
class WebPush extends Facade
{
    public static function fake(): WebPushFake
    {
        $fake = new WebPushFake(static::getFacadeApplication()['config']->get('delivra.webpush', []));

        static::swap($fake);

        return $fake;
    }

    /**
     * Static on the manager, so the facade passes it through without resolving one.
     */
    public static function sendUsing(string $routeClass, callable|string $sender): void
    {
        WebPushManager::sendUsing($routeClass, $sender);
    }

    protected static function getFacadeAccessor(): string
    {
        return 'delivra-webpush';
    }
}
