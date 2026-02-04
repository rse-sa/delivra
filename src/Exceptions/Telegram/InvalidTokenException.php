<?php

namespace RSE\Delivra\Exceptions\Telegram;

use RSE\Delivra\Exceptions\DelivraException;

class InvalidTokenException extends DelivraException
{
    protected ?string $token;

    public function __construct(?string $token = null)
    {
        $this->token = $token;

        parent::__construct('Invalid Telegram bot token'.($token ? " [{$token}]" : ''));
    }

    public function getToken(): ?string
    {
        return $this->token;
    }
}
