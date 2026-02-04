<?php

namespace RSE\Delivra\Exceptions\Telegram;

use RSE\Delivra\Exceptions\DelivraException;

class ApiErrorException extends DelivraException
{
    protected string $errorCode;

    protected mixed $apiResponse;

    public function __construct(string $errorCode, string $message, mixed $apiResponse = null)
    {
        $this->errorCode   = $errorCode;
        $this->apiResponse = $apiResponse;

        parent::__construct("Telegram API error [{$errorCode}]: {$message}");
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
