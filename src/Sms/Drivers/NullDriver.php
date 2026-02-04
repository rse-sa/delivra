<?php

namespace RSE\Delivra\Sms\Drivers;

use RSE\Delivra\Contracts\SmsDriver;
use RSE\Delivra\Sms\SmsResponse;

class NullDriver extends SmsDriver
{
    public function getBalance(): ?int
    {
        return 0;
    }

    public function formatNumber(string $number): string
    {
        return $number;
    }

    public function sendSingle(string $recipient): SmsResponse
    {
        $response = new SmsResponse($this->driver, $recipient, $this->builder->getBody());

        return $response->setResponse('')->setSuccessful();
    }
}
