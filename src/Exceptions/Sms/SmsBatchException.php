<?php

namespace RSE\Delivra\Exceptions\Sms;

use RSE\Delivra\Exceptions\DelivraException;
use RSE\Delivra\Sms\SmsBatchResponse;

class SmsBatchException extends DelivraException
{
    protected SmsBatchResponse $batchResponse;

    public function __construct(SmsBatchResponse $batchResponse, string $message = 'SMS batch partially failed')
    {
        parent::__construct($message);
        $this->batchResponse = $batchResponse;
    }

    public function getBatchResponse(): SmsBatchResponse
    {
        return $this->batchResponse;
    }
}
