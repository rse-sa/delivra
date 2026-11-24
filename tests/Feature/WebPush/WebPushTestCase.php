<?php

namespace RSE\Delivra\Tests\Feature\WebPush;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Minishlink\WebPush\VAPID;
use RSE\Delivra\Tests\TestCase;

abstract class WebPushTestCase extends TestCase
{
    protected static ?array $vapid = null;

    /** @var list<array{request: \Psr\Http\Message\RequestInterface}> */
    protected array $pushRequests = [];

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        static::$vapid ??= VAPID::createVapidKeys();

        $app['config']->set('database.default', 'testing');
        $app['config']->set('delivra.webpush.vapid', [
            'subject'     => 'mailto:push@example.com',
            'public_key'  => static::$vapid['publicKey'],
            'private_key' => static::$vapid['privateKey'],
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        // the published stub, so it is tested too
        $migration = require __DIR__ . '/../../../stubs/create_push_subscriptions_table.php.stub';
        $migration->up();
    }

    /**
     * Push services answer in this order, one response per push.
     */
    protected function fakePushServices(Response ...$responses): void
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->pushRequests));

        webpush()->usingHttpClient(new Client(['handler' => $stack]));
    }

    /**
     * A subscription with real keys, like the browser makes.
     */
    protected function browserSubscription(string $endpoint = 'https://push.example.com/send/abc'): array
    {
        $key     = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $details = openssl_pkey_get_details($key)['ec'];
        $encode  = fn (string $bytes) => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

        return [
            'endpoint' => $endpoint,
            'keys'     => [
                'p256dh' => $encode("\x04" . str_pad($details['x'], 32, "\0", STR_PAD_LEFT) . str_pad($details['y'], 32, "\0", STR_PAD_LEFT)),
                'auth'   => $encode(random_bytes(16)),
            ],
        ];
    }
}
