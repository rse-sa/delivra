<?php

namespace RSE\Delivra\Tests\Feature\Fcm;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RSE\Delivra\Events\DelivraMessageFailed;
use RSE\Delivra\Events\DelivraMessageSending;
use RSE\Delivra\Events\DelivraMessageSent;
use RSE\Delivra\Exceptions\Fcm\FcmConfigurationException;
use RSE\Delivra\Fcm\Events\FcmTokenInvalid;
use RSE\Delivra\Fcm\FcmResult;
use RSE\Delivra\Fcm\Messages\FcmMessage;

class SendingTest extends FcmTestCase
{
    public function test_sends_to_tokens_with_oauth_token(): void
    {
        $this->fakeGoogle();

        $report = fcm()->to(['token-a', 'token-b'])->send(FcmMessage::make('Hi', 'There')->data(['id' => 5]));

        $this->assertSame(2, $report->successfulCount());
        $this->assertSame('projects/demo-app/messages/token-a', $report->firstMessageId());

        Http::assertSent(fn (Request $request) => $request->url() === 'https://oauth2.googleapis.com/token'
            && $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
            && substr_count($request['assertion'], '.') === 2);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://fcm.googleapis.com/v1/projects/demo-app/messages:send'
            && $request->hasHeader('Authorization', 'Bearer oauth-token')
            && $request['message']['token'] === 'token-a'
            && $request['message']['notification'] === ['title' => 'Hi', 'body' => 'There']
            && $request['message']['data'] === ['id' => '5']);
    }

    public function test_access_token_is_cached(): void
    {
        $this->fakeGoogle();

        fcm()->to('token-a')->send(FcmMessage::make('One'));
        fcm()->to('token-b')->send(FcmMessage::make('Two'));

        $this->assertCount(1, Http::recorded(fn (Request $request) => str_contains($request->url(), 'oauth2')));
    }

    public function test_jwt_assertion_is_signed_with_the_service_account_key(): void
    {
        $this->fakeGoogle();

        fcm()->to('token-a')->send(FcmMessage::make('Hi'));

        $request = Http::recorded(fn (Request $request) => str_contains($request->url(), 'oauth2'))->first()[0];

        [$header, $claims, $signature] = explode('.', $request['assertion']);
        $decode = fn (string $value) => base64_decode(strtr($value, '-_', '+/'));
        $public = openssl_pkey_get_details(openssl_pkey_get_private(static::$privateKey))['key'];

        $this->assertSame(1, openssl_verify("{$header}.{$claims}", $decode($signature), $public, OPENSSL_ALGO_SHA256));
        $this->assertSame('push@demo-app.iam.gserviceaccount.com', json_decode($decode($claims), true)['iss']);
        $this->assertSame('https://www.googleapis.com/auth/firebase.messaging', json_decode($decode($claims), true)['scope']);
    }

    public function test_unregistered_token_is_reported_invalid_and_others_still_send(): void
    {
        Event::fake([FcmTokenInvalid::class, DelivraMessageSent::class, DelivraMessageFailed::class]);
        $this->fakeGoogle(['dead-token' => $this->fcmError(404, 'NOT_FOUND', 'UNREGISTERED')]);

        $callback = [];
        fcm()->onInvalidToken(function (string $token, string $reason) use (&$callback) {
            $callback[] = [$token, $reason];
        });

        $report = fcm()->to(['dead-token', 'live-token'])->send(FcmMessage::make('Hi'));

        $this->assertSame(['dead-token'], $report->invalidTokens());
        $this->assertSame(1, $report->successfulCount());
        $this->assertSame([['dead-token', 'UNREGISTERED']], $callback);

        Event::assertDispatched(FcmTokenInvalid::class, fn (FcmTokenInvalid $event) => $event->token === 'dead-token' && $event->project === 'default');
        Event::assertDispatched(DelivraMessageSent::class, 1);
        Event::assertDispatched(DelivraMessageFailed::class, fn (DelivraMessageFailed $event) => ! str_contains($event->recipient, 'dead-token'));
    }

    public function test_sender_id_mismatch_is_wrong_project_and_not_invalid(): void
    {
        Event::fake([FcmTokenInvalid::class]);
        $this->fakeGoogle(['other-app-token' => $this->fcmError(403, 'PERMISSION_DENIED', 'SENDER_ID_MISMATCH')]);

        $report = fcm()->to('other-app-token')->send(FcmMessage::make('Hi'));

        $this->assertSame(FcmResult::WRONG_PROJECT, $report->all()[0]->status);
        $this->assertSame([], $report->invalidTokens());
        Event::assertNotDispatched(FcmTokenInvalid::class);
    }

    public function test_invalid_argument_and_third_party_auth_fail_without_throwing(): void
    {
        Event::fake([FcmTokenInvalid::class]);
        $this->fakeGoogle([
            'ios-token' => $this->fcmError(401, 'UNAUTHENTICATED', 'THIRD_PARTY_AUTH_ERROR'),
            'bad-token' => $this->fcmError(400, 'INVALID_ARGUMENT', 'INVALID_ARGUMENT', 'The registration token is not valid'),
        ]);

        $report = fcm()->to(['ios-token', 'bad-token', 'android-token'])->send(FcmMessage::make('Hi'));

        $this->assertSame(['THIRD_PARTY_AUTH_ERROR', 'INVALID_ARGUMENT', null], $report->results()->pluck('error')->all());
        $this->assertSame(1, $report->successfulCount());
        Event::assertNotDispatched(FcmTokenInvalid::class);

        // no OAuth refresh for a third party auth error
        $this->assertCount(1, Http::recorded(fn (Request $request) => str_contains($request->url(), 'oauth2')));
    }

    public function test_bare_404_is_a_configuration_error(): void
    {
        $this->fakeGoogle(['token-a' => $this->fcmError(404, 'NOT_FOUND', null, 'Requested entity was not found.')]);

        $this->expectException(FcmConfigurationException::class);
        $this->expectExceptionMessage('Requested entity was not found.');

        fcm()->to('token-a')->send(FcmMessage::make('Hi'));
    }

    public function test_expired_oauth_token_is_refreshed_once(): void
    {
        $calls = 0;
        $this->fakeGoogle(['token-a' => function () use (&$calls) {
            return $calls++ === 0 ? $this->fcmError(401, 'UNAUTHENTICATED') : Http::response(['name' => 'projects/demo-app/messages/1']);
        }]);

        $report = fcm()->to('token-a')->send(FcmMessage::make('Hi'));

        $this->assertSame(1, $report->successfulCount());
        $this->assertCount(2, Http::recorded(fn (Request $request) => str_contains($request->url(), 'oauth2')));
    }

    public function test_rejected_credentials_throw_after_refresh(): void
    {
        $this->fakeGoogle(['token-a' => $this->fcmError(401, 'UNAUTHENTICATED')]);

        $this->expectException(FcmConfigurationException::class);

        fcm()->to('token-a')->send(FcmMessage::make('Hi'));
    }

    public function test_failed_token_exchange_throws_without_secrets(): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_grant', 'error_description' => 'Invalid JWT Signature.'], 400)]);

        try {
            fcm()->to('token-a')->send(FcmMessage::make('Hi'));
            $this->fail('Expected a configuration exception.');
        } catch (FcmConfigurationException $e) {
            $this->assertStringContainsString('Invalid JWT Signature.', $e->getMessage());
            $this->assertStringNotContainsString('PRIVATE KEY', $e->getMessage());
        }
    }

    public function test_transient_failures_are_retried_once(): void
    {
        Sleep::fake();

        $calls = 0;
        $this->fakeGoogle(['token-a' => function () use (&$calls) {
            return $calls++ === 0 ? $this->fcmError(503, 'UNAVAILABLE', 'UNAVAILABLE') : Http::response(['name' => 'projects/demo-app/messages/1']);
        }, 'token-b' => $this->fcmError(429, 'RESOURCE_EXHAUSTED', 'QUOTA_EXCEEDED')]);

        $report = fcm()->to(['token-a', 'token-b'])->send(FcmMessage::make('Hi'));

        $this->assertTrue($report->all()[0]->successful());
        $this->assertTrue($report->all()[1]->retryable);
        $this->assertCount(4, $this->fcmRequests());
    }

    public function test_topic_condition_and_validate_only(): void
    {
        $this->fakeGoogle();

        fcm()->topic('news')->send(FcmMessage::make('News'));
        fcm()->condition("'news' in topics")->send(FcmMessage::make('News'));
        fcm()->send(FcmMessage::make('Broadcast')->topic('all')->validateOnly());

        [$topic, $condition, $validate] = $this->fcmRequests();

        $this->assertSame('news', $topic['message']['topic']);
        $this->assertSame("'news' in topics", $condition['message']['condition']);
        $this->assertSame('all', $validate['message']['topic']);
        $this->assertTrue($validate['validate_only']);
        $this->assertArrayNotHasKey('validate_only', $topic->data());
    }

    public function test_project_from_json_credentials(): void
    {
        $this->fakeGoogle();

        fcm()->project('customers')->to('token-a')->send(FcmMessage::make('Hi'));
        fcm()->to('token-b')->send(FcmMessage::make('Hi')->project('customers'));

        $this->assertCount(2, Http::recorded(fn (Request $request) => str_contains($request->url(), 'projects/customers-app/messages:send')));
    }

    public function test_credentials_file_path(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents($path, json_encode(['project_id' => 'file-app', 'client_email' => 'a@file-app.test', 'private_key' => static::$privateKey]));

        config()->set('delivra.fcm.projects.file', ['credentials' => $path]);
        $this->app->forgetInstance('delivra-fcm');

        $this->assertSame('file-app', fcm()->project('file')->projectId());

        @unlink($path);
    }

    public function test_missing_credentials_throw(): void
    {
        config()->set('delivra.fcm.projects.empty', ['project_id' => 'x']);
        $this->app->forgetInstance('delivra-fcm');

        $this->expectException(FcmConfigurationException::class);
        $this->expectExceptionMessage('client_email');

        fcm()->project('empty')->to('token')->send(FcmMessage::make('Hi'));
    }

    public function test_sending_listener_can_skip_a_target(): void
    {
        $this->fakeGoogle();

        Event::listen(DelivraMessageSending::class, fn (DelivraMessageSending $event) => $event->recipient === 'topic:muted' ? false : null);

        $report = fcm()->topic('muted')->topic('news')->send(FcmMessage::make('Hi'));

        $this->assertCount(1, $report);
        $this->assertCount(1, $this->fcmRequests());
    }

    public function test_console_command_checks_each_step(): void
    {
        $this->fakeGoogle(['dead-token' => $this->fcmError(404, 'NOT_FOUND', 'UNREGISTERED')]);

        $this->artisan('delivra:fcm-test', ['token' => 'token-a', '--validate' => true])->assertSuccessful();
        $this->artisan('delivra:fcm-test', ['token' => 'dead-token'])->assertFailed();

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'messages:send') && ($request->data()['validate_only'] ?? null) === true);
    }

    public function test_topic_subscribe_and_unsubscribe(): void
    {
        Event::fake([FcmTokenInvalid::class]);
        $this->fakeGoogle(extra: [
            'iid.googleapis.com/*' => Http::response(['results' => [[], ['error' => 'NOT_FOUND'], ['error' => 'INTERNAL']]]),
        ]);

        $report = fcm()->subscribe('news', ['token-a', 'dead-token', 'token-c']);
        fcm()->unsubscribe('/topics/news', 'token-a');

        $this->assertSame(1, $report->successfulCount());
        $this->assertSame(['dead-token'], $report->invalidTokens());
        Event::assertDispatched(FcmTokenInvalid::class, fn (FcmTokenInvalid $event) => $event->token === 'dead-token' && $event->reason === 'NOT_FOUND');

        Http::assertSent(fn (Request $request) => $request->url() === 'https://iid.googleapis.com/iid/v1:batchAdd'
            && $request->hasHeader('access_token_auth', 'true')
            && $request->hasHeader('Authorization', 'Bearer oauth-token')
            && $request['to'] === '/topics/news'
            && $request['registration_tokens'] === ['token-a', 'dead-token', 'token-c']);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://iid.googleapis.com/iid/v1:batchRemove');
    }
}
