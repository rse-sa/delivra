<?php

namespace RSE\Delivra\Tests\Unit\Sms\Drivers;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RSE\Delivra\Exceptions\Sms\ApiErrorException;
use RSE\Delivra\Exceptions\Sms\InvalidPhoneNumberException;
use RSE\Delivra\Sms\Drivers\UnifonicDriver;
use RSE\Delivra\Tests\TestCase;

class UnifonicDriverTest extends TestCase
{
    protected function driver(): UnifonicDriver
    {
        $driver = new UnifonicDriver('unifonic', [
            'app_sid'  => 'sid',
            'username' => 'user',
            'password' => 'secret',
            'sender'   => 'ACME',
        ]);

        $driver->message('Salam');

        return $driver;
    }

    protected function sendResponse(mixed $success = 'true'): array
    {
        return [
            'success'   => $success,
            'message'   => '',
            'errorCode' => 'ER-00',
            'data'      => [
                'MessageID'     => 3200017889310,
                'Status'        => 'Queued',
                'NumberOfUnits' => 2,
                'Cost'          => 0.1,
                'Balance'       => 120.5,
                'Recipient'     => '966500000000',
            ],
        ];
    }

    public function test_sends_with_basic_auth_and_query_parameters(): void
    {
        Http::fake(['el.cloud.unifonic.com/*' => Http::response($this->sendResponse())]);

        $response = $this->driver()->sendSingle('966500000000');

        $this->assertTrue($response->successful());
        $this->assertSame('3200017889310', $response->getMessageId());
        $this->assertSame(2.0, $response->getCredits());
        $this->assertSame(120.5, $response->getBalance());

        Http::assertSent(function (Request $request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);

            return $request->method() === 'POST'
                && str_starts_with($request->url(), 'https://el.cloud.unifonic.com/rest/SMS/messages?')
                && $request->hasHeader('Authorization', 'Basic ' . base64_encode('user:secret'))
                && $query['AppSid'] === 'sid'
                && $query['SenderID'] === 'ACME'
                && $query['Recipient'] === '966500000000'
                && $query['Body'] === 'Salam'
                && ! empty($query['CorrelationID']);
        });
    }

    public function test_accepts_boolean_success(): void
    {
        Http::fake(['el.cloud.unifonic.com/*' => Http::response($this->sendResponse(true))]);

        $this->assertTrue($this->driver()->sendSingle('966500000000')->successful());
    }

    public function test_builder_sender_overrides_configured_sender(): void
    {
        Http::fake(['el.cloud.unifonic.com/*' => Http::response($this->sendResponse())]);

        $driver = $this->driver();
        $driver->getBuilder()->from('OTHER');
        $driver->sendSingle('966500000000');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'SenderID=OTHER'));
    }

    public function test_authentication_failure_is_not_retried(): void
    {
        Http::fake(['el.cloud.unifonic.com/*' => Http::response(['success' => false, 'message' => 'Authentication failed', 'errorCode' => 'ER-01'], 401)]);

        try {
            $this->driver()->sendSingle('966500000000');
            $this->fail('Expected ApiErrorException.');
        } catch (ApiErrorException $e) {
            $this->assertSame('AUTHENTICATION_FAILED', $e->getErrorCode());
        }

        Http::assertSentCount(1);
    }

    public function test_invalid_sender(): void
    {
        Http::fake(['el.cloud.unifonic.com/*' => Http::response(['success' => false, 'message' => 'This user cannot use specified SenderID'], 480)]);

        try {
            $this->driver()->sendSingle('966500000000');
            $this->fail('Expected ApiErrorException.');
        } catch (ApiErrorException $e) {
            $this->assertSame('INVALID_SENDER', $e->getErrorCode());
        }
    }

    public function test_invalid_number(): void
    {
        Http::fake(['el.cloud.unifonic.com/*' => Http::response(['success' => false, 'message' => 'Invalid dest num'], 482)]);

        $this->expectException(InvalidPhoneNumberException::class);

        $this->driver()->sendSingle('123');
    }

    public function test_ok_status_with_false_success_throws(): void
    {
        Http::fake(['el.cloud.unifonic.com/*' => Http::response(['success' => 'false', 'message' => 'Bad', 'errorCode' => 'ER-09'])]);

        try {
            $this->driver()->sendSingle('966500000000');
            $this->fail('Expected ApiErrorException.');
        } catch (ApiErrorException $e) {
            $this->assertSame('ER-09', $e->getErrorCode());
        }
    }

    public function test_balance_is_unsupported(): void
    {
        $this->assertNull($this->driver()->getBalance());
    }

    public function test_bulk_sends_one_request_and_maps_each_recipient(): void
    {
        Http::fake(['api.unifonic.com/*' => Http::response([
            'success' => 'true',
            'data'    => ['Messages' => [
                ['MessageID' => 1, 'Recipient' => '966500000001', 'Status' => 'Queued'],
                ['MessageID' => 2, 'Recipient' => '966500000002', 'Status' => 'Rejected'],
            ]],
        ])]);

        $batch = $this->driver()->sendMultiple(['0500000001', '0500000002']);

        $this->assertSame(1, $batch->successfulCount());
        $this->assertSame('1', $batch->successful()[0]['response']->getMessageId());
        $this->assertSame('0500000002', $batch->failed()[0]['number']);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.unifonic.com/rest/Messages/SendBulk'
            && $request['AppSid'] === 'sid'
            && $request['Recipient'] === '966500000001,966500000002');
    }

    public function test_bulk_failure_marks_every_recipient_and_does_not_resend(): void
    {
        Http::fake(['api.unifonic.com/*' => Http::response(['success' => 'false', 'message' => 'Invalid AppSid', 'errorCode' => 'ER-01'])]);

        $batch = $this->driver()->sendMultiple(['0500000001', '0500000002']);

        $this->assertSame(2, $batch->failedCount());
        Http::assertSentCount(1);
    }
}
