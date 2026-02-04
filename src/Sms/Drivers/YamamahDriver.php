<?php

namespace RSE\Delivra\Sms\Drivers;

use RSE\Delivra\Contracts\SmsDriver;
use RSE\Delivra\Exceptions\Sms\ApiErrorException;
use RSE\Delivra\Exceptions\Sms\InvalidPhoneNumberException;
use RSE\Delivra\Exceptions\Sms\OutOfBalanceException;
use RSE\Delivra\Sms\SmsResponse;

class YamamahDriver extends SmsDriver
{
    public function getBalance(): ?int
    {
        try {
            $response = $this->http()->get('https://api.yamamah.com/GetCredit/' . urlencode($this->settings['username']) . '/' . urlencode($this->settings['password']))->body();

            $array = json_decode($response, true);

            if ($array === false || ! isset($array['GetCreditResult']['Status']) || $array['GetCreditResult']['Status'] != '1') {
                throw new ApiErrorException($this->driver, 'BALANCE_ERROR', 'Failed to get balance', $array);
            }

            return $array['GetCreditResult']['Credit'];
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
        // Yamamah expects international format: 966XXXXXXXXX
        // Remove +, spaces, leading 0
        return '966' . ltrim(ltrim($number, '+'), '0');
    }

    public function sendSingle(string $recipient): SmsResponse
    {
        $response = new SmsResponse($this->driver, $recipient, $this->builder->getBody());

        $requestPayload = $this->payload($recipient);

        try {
            $apiResponse = $this->http()->asJson()->post('https://api.yamamah.com/SendSMS', $requestPayload);

            $response->setResponse($apiResponse->body());

            $rArray = $response->getResponseArray();

            $response->failedIf(
                $rArray === false
                || ! isset($rArray['Status'])
                || $rArray['Status'] != '1'
            );

            if (isset($rArray['Status'])) {
                if ($rArray['Status'] == 40) {
                    throw new OutOfBalanceException($this->driver, null, null);
                } elseif ($rArray['Status'] == 60) {
                    throw new InvalidPhoneNumberException($recipient, $this->driver);
                } elseif ($rArray['Status'] == 20 || $rArray['Status'] == 30) {
                    throw new ApiErrorException($this->driver, 'INVALID_SENDER', 'Invalid sender name', $rArray);
                }
            }

            $response->setMessageId($rArray['MessageID'] ?? null);

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

    protected function payload($recipient): array
    {
        return [
            'Username'        => $this->settings['username'],
            'Password'        => $this->settings['password'],
            'Tagname'         => $this->settings['sender'] ?? '',
            'RecepientNumber' => $recipient,
            'VariableList'    => '',
            'ReplacementList' => '',
            'Message'         => $this->builder->getBody(),
            'SendDateTime'    => 0,
            'EnableDR'        => false,
        ];
    }
}
