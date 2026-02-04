<?php

namespace RSE\Delivra\Exceptions\Telegram;

use RSE\Delivra\Exceptions\DelivraException;

class FileUploadException extends DelivraException
{
    protected string $filePath;

    public function __construct(string $filePath, ?string $message = null)
    {
        $this->filePath = $filePath;

        parent::__construct("Failed to upload file [{$filePath}]" . ($message ? ": {$message}" : ''));
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }
}
