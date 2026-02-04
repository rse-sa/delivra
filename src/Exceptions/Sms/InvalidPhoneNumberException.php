<?php

namespace RSE\Delivra\Exceptions\Sms;

use RSE\Delivra\Exceptions\DelivraException;

class InvalidPhoneNumberException extends DelivraException
{
    protected string $number;

    protected ?string $driver;

    public function __construct(string $number, ?string $driver = null)
    {
        $this->number = $number;
        $this->driver = $driver;

        parent::__construct("Invalid phone number format [{$number}]" .
            ($driver ? " for driver [{$driver}]" : '')
        );
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function getDriver(): ?string
    {
        return $this->driver;
    }
}
