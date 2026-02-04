<?php

namespace RSE\Delivra\Sms\Drivers;

use RSE\Delivra\Contracts\SmsDriver;
use RSE\Delivra\Exceptions\Sms\ApiErrorException;
use RSE\Delivra\Sms\SmsBatchResponse;
use RSE\Delivra\Sms\SmsResponse;

class UnifonicDriver extends SmsDriver
{
    public function getBalance(): ?int
    {
        try {
            $response = $this->http()->get('https://api.unifonic.com/rest/Account/GetBalance', [
                'query' => ['AppSid' => $this->settings['key']],
            ]);

            $array = json_decode($response->body(), true);

            if ($array === false || ! isset($array['Balance'])) {
                throw new ApiErrorException($this->driver, 'BALANCE_ERROR', 'Failed to get balance', $array);
            }

            return $array['Balance'];
        } catch (\Exception $e) {
            report($e);
            throw new ApiErrorException($this->driver, 'HTTP_ERROR', $e->getMessage());
        }
    }

    public function formatNumber(string $number): string
    {
        // Remove '+' and leading '0', prepend '966'
        return '966' . ltrim(ltrim($number, '+'), '0');
    }

    public function sendSingle(string $recipient): SmsResponse
    {
        $response = new SmsResponse($this->driver, $recipient, $this->builder->getBody());

        $params = [
            'AppSid'    => $this->settings['key'],
            'Recipient' => $recipient,
            'Body'      => $this->builder->getBody(),
            'SenderID'  => $this->settings['sender'],
        ];

        try {
            $apiResponse = $this->http()->asForm()->post(
                'https://api.unifonic.com/rest/Messages/Send',
                $params
            );

            $response->setResponse($apiResponse->body());

            $rArray = $response->getResponseArray();

            $response->failedIf(
                $rArray === false
                || $rArray['status'] == 'Failed'
                || $rArray['status'] == 'Rejected'
                || ($rArray['success'] ?? 'false') != 'true'
            );

            if ($response->failed()) {
                throw new ApiErrorException(
                    $this->driver,
                    $rArray['errorCode'] ?? 'SEND_FAILED',
                    $rArray['message'] ?? 'Message send failed',
                    $rArray
                );
            }

            return $response;
        } catch (\Exception $e) {
            $response->setFailed()->setResponse($e->getMessage());

            if (! $e instanceof ApiErrorException) {
                report($e);
            }

            throw $e;
        }
    }

    public function sendMultiple(array $recipients): SmsBatchResponse
    {
        // Unifonic supports bulk sending
        $batch   = new SmsBatchResponse;
        $numbers = array_map([$this, 'formatNumber'], $recipients);

        try {
            $response = $this->http()->asForm()->post(
                'https://api.unifonic.com/rest/Messages/SendBulk',
                [
                    'AppSid'    => $this->settings['key'],
                    'Recipient' => implode(',', $numbers),
                    'Body'      => $this->builder->getBody(),
                    'SenderID'  => $this->settings['sender'],
                ]
            );

            $result = json_decode($response->body(), true);

            // Unifonic bulk API returns individual results per recipient
            if (isset($result['data']) && is_array($result['data'])) {
                foreach ($result['data'] as $index => $item) {
                    $recipient   = $recipients[$index];
                    $smsResponse = new SmsResponse($this->driver, $recipient, $this->builder->getBody());

                    if (($item['status'] ?? 'failed') === 'success') {
                        $smsResponse->setSuccessful();
                        $batch->addSuccess($recipient, $smsResponse);
                    } else {
                        $smsResponse->setFailed();
                        $batch->addFailure($recipient, $item['message'] ?? 'Unknown error');
                    }
                }
            } else {
                // Fallback: send individually
                return parent::sendMultiple($recipients);
            }
        } catch (\Exception $e) {
            report($e);

            // Fallback: send individually
            return parent::sendMultiple($recipients);
        }

        return $batch;
    }
}
