<?php

namespace RSE\Delivra\Exceptions\Telegram;

use RSE\Delivra\Exceptions\DelivraException;
use RSE\Delivra\Telegram\TelegramBatchResponse;

class TelegramBatchException extends DelivraException
{
    protected TelegramBatchResponse $batchResponse;

    public function __construct(TelegramBatchResponse $batchResponse, string $message = 'Telegram batch partially failed')
    {
        parent::__construct($message);
        $this->batchResponse = $batchResponse;
    }

    public function getBatchResponse(): TelegramBatchResponse
    {
        return $this->batchResponse;
    }
}
