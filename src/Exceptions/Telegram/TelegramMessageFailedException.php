<?php

namespace RSE\Delivra\Exceptions\Telegram;

use RSE\Delivra\Exceptions\DelivraException;

class TelegramMessageFailedException extends DelivraException
{
    protected ?string $chatId;

    protected ?string $messageType;

    public function __construct(?string $chatId = null, ?string $messageType = null, ?string $message = null)
    {
        $this->chatId      = $chatId;
        $this->messageType = $messageType;

        parent::__construct('Telegram message failed' .
            ($messageType ? " (type: {$messageType})" : '') .
            ($chatId ? " for chat [{$chatId}]" : '') .
            ($message ? ": {$message}" : '')
        );
    }

    public function getChatId(): ?string
    {
        return $this->chatId;
    }

    public function getMessageType(): ?string
    {
        return $this->messageType;
    }
}
