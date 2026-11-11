<?php

namespace RSE\Delivra\Tests\Feature\Fcm;

use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RSE\Delivra\Tests\TestCase;

abstract class FcmTestCase extends TestCase
{
    protected static ?string $privateKey = null;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        static::$privateKey ??= $this->generateKey();

        $app['config']->set('cache.default', 'array');
        $app['config']->set('delivra.fcm.retry_max_delay', 0);
        $app['config']->set('delivra.fcm.projects', [
            'default' => [
                'project_id'   => 'demo-app',
                'client_email' => 'push@demo-app.iam.gserviceaccount.com',
                // like a key pasted into .env
                'private_key' => str_replace("\n", '\n', static::$privateKey),
            ],
            'customers' => [
                'credentials' => json_encode([
                    'project_id'   => 'customers-app',
                    'client_email' => 'push@customers-app.iam.gserviceaccount.com',
                    'private_key'  => static::$privateKey,
                ]),
            ],
        ]);
    }

    protected function generateKey(): string
    {
        openssl_pkey_export(openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]), $key);

        return $key;
    }

    /**
     * Fake Google: OAuth answers with a token, FCM answers per device token.
     *
     * @param  array<string, PromiseInterface|Closure>  $answers  token => response
     */
    protected function fakeGoogle(array $answers = [], array $extra = []): void
    {
        Http::fake($extra + [
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'oauth-token', 'expires_in' => 3600]),
            'fcm.googleapis.com/*'    => function (Request $request) use ($answers) {
                $key    = $request['message']['token'] ?? $request['message']['topic'] ?? $request['message']['condition'] ?? null;
                $answer = $answers[$key] ?? null;

                if ($answer instanceof Closure) {
                    return $answer($request);
                }

                return $answer ?? Http::response(['name' => "projects/demo-app/messages/{$key}"]);
            },
        ]);
    }

    protected function fcmError(int $status, string $errorStatus, ?string $errorCode = null, string $message = 'error'): PromiseInterface
    {
        return Http::response(['error' => [
            'code'    => $status,
            'message' => $message,
            'status'  => $errorStatus,
            'details' => $errorCode ? [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => $errorCode]] : [],
        ]], $status);
    }

    /** @return list<Request> */
    protected function fcmRequests(): array
    {
        return Http::recorded(fn (Request $request) => str_contains($request->url(), 'messages:send'))->map(fn ($pair) => $pair[0])->values()->all();
    }
}
