<?php

namespace RSE\Delivra\Exceptions\Sms;

use RSE\Delivra\Exceptions\DelivraException;

class ApiErrorException extends DelivraException
{
    protected string $driver;
    protected string $errorCode;
    protected mixed $apiResponse;

    public function __construct(string $driver, string $errorCode, string $message, mixed $apiResponse = null)
    {
        $this->driver = $driver;
        $this->errorCode = $errorCode;
        $this->apiResponse = $apiResponse;

        parent::__construct("API error for driver [{$driver}]: [{$errorCode}] {$message}");
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getApiResponse(): mixed
    {
        return $this->apiResponse;
    }
}
