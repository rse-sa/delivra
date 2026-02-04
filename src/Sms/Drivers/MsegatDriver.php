<?php

namespace RSE\Delivra\Sms\Drivers;

use Illuminate\Support\Str;
use RSE\Delivra\Contracts\SmsDriver;
use RSE\Delivra\Exceptions\Sms\ApiErrorException;
use RSE\Delivra\Exceptions\Sms\InvalidPhoneNumberException;
use RSE\Delivra\Exceptions\Sms\OutOfBalanceException;
use RSE\Delivra\Sms\SmsResponse;

class MsegatDriver extends SmsDriver
{
    public function getBalance(): ?int
    {
        try {
            $response = $this->http()->asForm()->post('https://www.msegat.com/gw/Credits.php', [
                'userName'    => $this->settings['username'],
                'apiKey'      => $this->settings['key'],
                'msgEncoding' => 'UTF8',
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
        // Msegat expects numbers with country code, no + or spaces
        // Example: 9665000000000
        return '966' . ltrim(ltrim($number, '+'), '0');
    }

    public function credits($numbers, string $message): ?float
    {
        $numbers = is_string($numbers) ? [$numbers] : $numbers;

        try {
            $response = $this->http()->asForm()->post('https://www.msegat.com/gw/calculateCost.php', [
                'userName'    => $this->settings['username'],
                'apiKey'      => $this->settings['key'],
                'contactType' => 'numbers',
                'contacts'    => implode(',', $numbers),
                'msg'         => $message,
                'msgEncoding' => 'UTF8',
            ])->body();

            if (str_contains($response, ',')) {
                return Str::of($response)->after(',')->toFloat();
            }

            return null;
        } catch (\Exception $e) {
            report($e);

            return null;
        }
    }

    public function sendSingle(string $recipient): SmsResponse
    {
        $response = new SmsResponse($this->driver, $recipient, $this->builder->getBody());

        try {
            $apiResponse = $this->http()->asJson()->post('https://www.msegat.com/gw/sendsms.php', [
                'userName'    => $this->settings['username'],
                'apiKey'      => $this->settings['key'],
                'numbers'     => $recipient,
                'userSender'  => $this->settings['sender'],
                'msg'         => $this->builder->getBody(),
                'msgEncoding' => 'UTF8',
            ]);

            $response->setResponse($apiResponse->body());

            $rArray = $response->getResponseArray();

            $response->failedIf(
                $rArray === false
                || ! isset($rArray['code'])
                || ($rArray['code'] != '1' && $rArray['code'] != 'M0000')
            );

            if (isset($rArray['code'])) {
                if ($rArray['code'] == '1060') {
                    throw new OutOfBalanceException($this->driver, null, null);
                } elseif ($rArray['code'] == '1120') {
                    throw new InvalidPhoneNumberException($recipient, $this->driver);
                } elseif ($rArray['code'] == '1110') {
                    throw new ApiErrorException($this->driver, 'INVALID_SENDER', $rArray['message'] ?? 'Invalid sender name', $rArray);
                }
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
