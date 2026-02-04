<?php

namespace RSE\Delivra\Exceptions\Sms;

use RSE\Delivra\Exceptions\DelivraException;

class SmsMessageFailedException extends DelivraException
{
    protected string $driver;

    protected ?string $recipient;

    protected $message;

    public function __construct(string $driver, ?string $recipient = null, ?string $message = null)
    {
        $this->driver    = $driver;
        $this->recipient = $recipient;
        $this->message   = $message;

        parent::__construct("SMS message failed using driver [{$driver}]" .
            ($recipient ? " for recipient [{$recipient}]" : '') .
            ($message ? ": {$message}" : '')
        );
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    public function getRecipient(): ?string
    {
        return $this->recipient;
    }
}
