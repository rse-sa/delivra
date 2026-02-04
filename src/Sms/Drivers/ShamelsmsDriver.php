<?php

namespace RSE\Delivra\Sms\Drivers;

use Illuminate\Support\Str;
use RSE\Delivra\Contracts\SmsDriver;
use RSE\Delivra\Exceptions\Sms\ApiErrorException;
use RSE\Delivra\Exceptions\Sms\InvalidPhoneNumberException;
use RSE\Delivra\Exceptions\Sms\OutOfBalanceException;
use RSE\Delivra\Sms\SmsResponse;

class ShamelsmsDriver extends SmsDriver
{
    public function getBalance(): ?int
    {
        try {
            $response = $this->http()->get('https://www.shamelsms.net/api/users.aspx', [
                'query' => [
                    'code'     => 7,
                    'username' => $this->settings['username'],
                    'password' => $this->settings['password'],
                ],
            ])->body();

            if (is_numeric($response)) {
                return (int) $response;
            }

            throw new ApiErrorException($this->driver, 'BALANCE_ERROR', 'Invalid balance response', $response);
        } catch (\Exception $e) {
            report($e);

            if ($e instanceof ApiErrorException) {
                throw $e;
            }

            throw new ApiErrorException($this->driver, 'HTTP_ERROR', $e->getMessage());
        }
    }

    public function formatNumber(string $number): string
    {
        // Shamelsms expects international format: 966XXXXXXXXX
        return '966' . ltrim(ltrim($number, '+'), '0');
    }

    public function sendSingle(string $recipient): SmsResponse
    {
        $response = new SmsResponse($this->driver, $recipient, $this->builder->getBody());

        try {
            $apiResponse = $this->http()->get('https://www.shamelsms.net/api/httpSms.aspx', [
                'query' => [
                    'username'    => $this->settings['username'],
                    'password'    => $this->settings['password'],
                    'mobile'      => $recipient,
                    'message'     => $this->builder->getBody(),
                    'sender'      => $this->settings['sender'],
                    'unicodetype' => 'U',
                ],
            ]);

            $response->setResponse($apiResponse->body());

            $code = (int) Str::of($apiResponse->body())->before(' ')->toInteger();

            $response->failedIf($code != 4);

            if (in_array($code, [3101, 3102, 3103, 3104, 3105])) {
                throw new ApiErrorException($this->driver, (string) $code, 'Invalid parameters', ['code' => $code]);
            } elseif ($code == 105) {
                throw new OutOfBalanceException($this->driver, null, null);
            } elseif ($code == 1010) {
                throw new InvalidPhoneNumberException($recipient, $this->driver);
            } elseif ($code == 107) {
                throw new ApiErrorException($this->driver, (string) $code, 'Invalid sender name', ['code' => $code]);
            }

            return $response;
        } catch (\Exception $e) {
            $response->setFailed()->setResponse($e->getMessage());

            if (! $e instanceof ApiErrorException
                && ! $e instanceof OutOfBalanceException
                && ! $e instanceof InvalidPhoneNumberException
            ) {
                report($e);
            }

            throw $e;
        }
    }
}
