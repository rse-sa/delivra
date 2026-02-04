<?php

namespace RSE\Delivra\Exceptions\Sms;

use RSE\Delivra\Exceptions\DelivraException;

class DriverNotFoundException extends DelivraException
{
    protected string $driver;

    public function __construct(string $driver)
    {
        $this->driver = $driver;
        parent::__construct("SMS driver [{$driver}] not found or not configured");
    }

    public function getDriver(): string
    {
        return $this->driver;
    }
}
