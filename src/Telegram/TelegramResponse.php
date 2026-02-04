<?php

namespace RSE\Delivra\Telegram;

use Carbon\Carbon;

class TelegramResponse
{
    protected bool $successful = true;

    protected string $chatId;

    protected ?int $messageId = null;

    protected ?Carbon $timestamp = null;

    protected mixed $raw = null;

    protected ?string $error = null;

    public function __construct(string $chatId, mixed $raw = null)
    {
        $this->chatId = $chatId;
        $this->raw = $raw;
        $this->timestamp = Carbon::now();

        if (is_array($raw) && isset($raw['ok']) && $raw['ok']) {
            $this->messageId = $raw['result']['message_id'] ?? null;
        } elseif (is_array($raw) && (!$raw['ok'] ?? false)) {
            $this->successful = false;
            $this->error = $raw['description'] ?? 'Unknown error';
        }
    }

    public function successful(): bool
    {
        return $this->successful;
    }

    public function failed(): bool
    {
        return !$this->successful;
    }

    public function getChatId(): string
    {
        return $this->chatId;
    }

    public function getMessageId(): ?int
    {
        return $this->messageId;
    }

    public function getTimestamp(): Carbon
    {
        return $this->timestamp;
    }

    public function getRaw(): mixed
    {
        return $this->raw;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->successful(),
            'chat_id' => $this->chatId,
            'message_id' => $this->messageId,
            'timestamp' => $this->timestamp->toIso8601String(),
            'error' => $this->error,
            'raw' => $this->raw,
        ];
    }
}
