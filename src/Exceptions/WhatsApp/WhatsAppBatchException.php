<?php

namespace RSE\Delivra\Exceptions\WhatsApp;

use RSE\Delivra\WhatsApp\WhatsAppBatchResponse;

class WhatsAppBatchException extends WhatsAppException
{
    public function __construct(public readonly WhatsAppBatchResponse $batch)
    {
        parent::__construct("WhatsApp batch partially failed ({$batch->failedCount()} of {$batch->totalCount()})");
    }

    public function getBatchResponse(): WhatsAppBatchResponse
    {
        return $this->batch;
    }
}
