<?php

namespace RSE\Delivra\Exceptions\Sms;

use RSE\Delivra\Exceptions\DelivraException;

class InvalidDriverSettingsException extends DelivraException
{
    public function __construct(protected string $driver, protected array $errors)
    {
        parent::__construct("Invalid settings for SMS driver [{$driver}]: " . implode(' ', array_merge(...array_values($errors))));
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    /**
     * Validation messages keyed by setting name.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
