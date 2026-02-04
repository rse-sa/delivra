<?php

namespace RSE\Delivra\Exceptions\Sms;

use RSE\Delivra\Exceptions\DelivraException;

class OutOfBalanceException extends DelivraException
{
    protected string $driver;
    protected ?int $currentBalance;
    protected ?int $required;

    public function __construct(string $driver, ?int $currentBalance = null, ?int $required = null)
    {
        $this->driver = $driver;
        $this->currentBalance = $currentBalance;
        $this->required = $required;

        $message = "Insufficient balance for driver [{$driver}]";
        if ($currentBalance !== null) {
            $message .= " (current: {$currentBalance}" .
                ($required !== null ? ", required: {$required}" : '') .
                ')';
        }

        parent::__construct($message);
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    public function getCurrentBalance(): ?int
    {
        return $this->currentBalance;
    }

    public function getRequired(): ?int
    {
        return $this->required;
    }
}
