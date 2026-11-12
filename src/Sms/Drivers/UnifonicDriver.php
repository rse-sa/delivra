<?php

namespace RSE\Delivra\Sms\Drivers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RSE\Delivra\Contracts\SmsDriver;
use RSE\Delivra\Exceptions\DelivraException;
use RSE\Delivra\Exceptions\Sms\ApiErrorException;
use RSE\Delivra\Exceptions\Sms\InvalidPhoneNumberException;
use RSE\Delivra\Sms\Schema\AuthMode;
use RSE\Delivra\Sms\Schema\DriverDefinition;
use RSE\Delivra\Sms\Schema\Field;
use RSE\Delivra\Sms\SmsBatchResponse;
use RSE\Delivra\Sms\SmsFeature;
use RSE\Delivra\Sms\SmsResponse;

class UnifonicDriver extends SmsDriver
{
    public const BASE_URL = 'https://el.cloud.unifonic.com';

    // old v1 API, only used for bulk sending which the current API lacks
    public const LEGACY_URL = 'https://api.unifonic.com';

    public static function definition(): DriverDefinition
    {
        return DriverDefinition::make()
            ->features([SmsFeature::Bulk])
            ->authModes([
                AuthMode::make('app_sid')->fields([
                    Field::make('app_sid')->secret()->rules('required|string'),
                    Field::make('username')->rules('required|string'),
                    Field::make('password')->secret()->rules('required|string'),
                ]),
            ])
            ->fields([
                Field::make('sender')->rules('required|string|max:11'),
            ]);
    }

    /**
     * Unifonic has no balance endpoint. Each send response carries the balance instead,
     * see SmsResponse::getBalance().
     */
    public function getBalance(): ?int
    {
        return null;
    }

    public function formatNumber(string $number): string
    {
        // Remove '+' and leading '0', prepend '966'
        return '966' . ltrim(ltrim($number, '+'), '0');
    }

    public function sendSingle(string $recipient): SmsResponse
    {
        $response = new SmsResponse($this->driver, $recipient, $this->builder->getBody());

        // the docs pass every parameter in the query string
        $apiResponse = $this->request()
            ->withBasicAuth($this->settings['username'], $this->settings['password'])
            ->send('POST', self::BASE_URL . '/rest/SMS/messages', [
                'query' => [
                    'AppSid'        => $this->settings['app_sid'],
                    'SenderID'      => $this->sender(),
                    'Body'          => $this->builder->getBody(),
                    'Recipient'     => $recipient,
                    'CorrelationID' => (string) Str::uuid(),
                    'responseType'  => 'JSON',
                ],
            ]);

        $response->setResponse($apiResponse->body());

        if (! $this->succeeded($apiResponse)) {
            $response->setFailed();

            throw $this->exceptionFor($apiResponse, $recipient);
        }

        $data = $apiResponse->json('data') ?? [];

        return $response
            ->setSuccessful()
            ->setMessageId(isset($data['MessageID']) ? (string) $data['MessageID'] : null)
            ->setCredits(isset($data['NumberOfUnits']) ? (float) $data['NumberOfUnits'] : null)
            ->setBalance(isset($data['Balance']) ? (float) $data['Balance'] : null);
    }

    /**
     * One request through the old SendBulk endpoint. Never falls back to single sends,
     * since the bulk request may already have delivered.
     */
    public function sendMultiple(array $recipients): SmsBatchResponse
    {
        $batch   = new SmsBatchResponse;
        $numbers = array_map(fn ($recipient) => $this->formatNumber($this->sanitizeNumber($recipient)), $recipients);

        $apiResponse = $this->request()->asForm()->post(self::LEGACY_URL . '/rest/Messages/SendBulk', [
            'AppSid'    => $this->settings['app_sid'],
            'SenderID'  => $this->sender(),
            'Recipient' => implode(',', $numbers),
            'Body'      => $this->builder->getBody(),
        ]);

        if (! $this->succeeded($apiResponse)) {
            $error = $this->exceptionFor($apiResponse)->getMessage();

            foreach ($recipients as $recipient) {
                $batch->addFailure($recipient, $error);
            }

            return $batch;
        }

        $results = collect($apiResponse->json('data.Messages') ?? [])->keyBy('Recipient');

        foreach ($recipients as $index => $recipient) {
            $result   = $results->get($numbers[$index], []);
            $response = (new SmsResponse($this->driver, $recipient, $this->builder->getBody()))
                ->setResponse($apiResponse->body())
                ->setMessageId(isset($result['MessageID']) ? (string) $result['MessageID'] : null);

            if (in_array($result['Status'] ?? null, ['Failed', 'Rejected'], true)) {
                $batch->addFailure($recipient, "Message {$result['Status']}");

                continue;
            }

            $batch->addSuccess($recipient, $response->setSuccessful());
        }

        return $batch;
    }

    /**
     * Retry connection errors only. 4xx responses carry the error body we need,
     * and resending a rejected request just repeats the rejection.
     */
    protected function request(): PendingRequest
    {
        return Http::timeout(config('delivra.http.timeout', 10))
            ->connectTimeout(config('delivra.http.connect_timeout', 5))
            ->acceptJson()
            ->retry(
                config('delivra.http.retries', 3),
                config('delivra.http.retry_delay', 100),
                fn ($exception) => $exception instanceof ConnectionException,
                throw: false,
            );
    }

    protected function succeeded(Response $response): bool
    {
        // success comes back as true or "true"
        return $response->successful()
            && filter_var($response->json('success'), FILTER_VALIDATE_BOOLEAN);
    }

    protected function exceptionFor(Response $response, ?string $recipient = null): DelivraException
    {
        $message = $response->json('message') ?: 'Message send failed';

        return match ($response->status()) {
            482     => new InvalidPhoneNumberException($recipient ?? '', $this->driver),
            480     => new ApiErrorException($this->driver, 'INVALID_SENDER', $message, $response->json()),
            401     => new ApiErrorException($this->driver, 'AUTHENTICATION_FAILED', $message, $response->json()),
            default => new ApiErrorException(
                $this->driver,
                (string) ($response->json('errorCode') ?: 'HTTP_' . $response->status()),
                $message,
                $response->json() ?? $response->body(),
            ),
        };
    }
}
