<?php

namespace RSE\Delivra\Exceptions\Telegram;

/**
 * Telegram answered 429. Wait retryAfter seconds before the next send to this bot.
 */
class RateLimitedException extends TelegramMessageFailedException
{
    public function __construct(?string $chatId = null, ?string $messageType = null, ?string $message = null, public readonly int $retryAfter = 1)
    {
        parent::__construct($chatId, $messageType, $message);
    }
}
