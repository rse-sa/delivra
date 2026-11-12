<?php

namespace RSE\Delivra\Facades;

use Illuminate\Support\Facades\Facade;
use RSE\Delivra\Fcm\FcmManager;
use RSE\Delivra\Fcm\Testing\FcmFake;

/**
 * @method static \RSE\Delivra\Fcm\Project project(?string $name = null)
 * @method static \RSE\Delivra\Fcm\PendingFcm to(string|iterable $tokens)
 * @method static \RSE\Delivra\Fcm\PendingFcm topic(string $topic)
 * @method static \RSE\Delivra\Fcm\PendingFcm condition(string $condition)
 * @method static \RSE\Delivra\Fcm\Messages\FcmMessage message(?string $title = null, ?string $body = null)
 * @method static \RSE\Delivra\Fcm\FcmReport send(\RSE\Delivra\Fcm\Messages\FcmMessage $message)
 * @method static \RSE\Delivra\Fcm\FcmReport subscribe(string $topic, string|iterable $tokens)
 * @method static \RSE\Delivra\Fcm\FcmReport unsubscribe(string $topic, string|iterable $tokens)
 * @method static \RSE\Delivra\Fcm\FcmManager onInvalidToken(?\Closure $callback)
 * @method static \Illuminate\Http\Client\PendingRequest http()
 *
 * @see FcmManager
 */
class Fcm extends Facade
{
    public static function fake(): FcmFake
    {
        $fake = new FcmFake(static::getFacadeApplication()['config']->get('delivra.fcm', []));

        static::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return 'delivra-fcm';
    }
}
