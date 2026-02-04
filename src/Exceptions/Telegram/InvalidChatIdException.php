<?php

namespace RSE\Delivra\Exceptions\Telegram;

use RSE\Delivra\Exceptions\DelivraException;

class InvalidChatIdException extends DelivraException
{
    protected string $chatId;

    public function __construct(string $chatId)
    {
        $this->chatId = $chatId;

        parent::__construct("Invalid Telegram chat ID [{$chatId}]");
    }

    public function getChatId(): string
    {
        return $this->chatId;
    }
}
