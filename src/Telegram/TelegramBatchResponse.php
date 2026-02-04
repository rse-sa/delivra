<?php

namespace RSE\Delivra\Telegram;

use Countable;

class TelegramBatchResponse implements Countable
{
    protected array $successful = [];
    protected array $failed = [];

    public function addSuccess(string $chatId, TelegramResponse $response): void
    {
        $this->successful[] = [
            'chatId' => $chatId,
            'response' => $response,
        ];
    }

    public function addFailure(string $chatId, string $error): void
    {
        $this->failed[] = [
            'chatId' => $chatId,
            'error' => $error,
        ];
    }

    public function successful(): array
    {
        return $this->successful;
    }

    public function failed(): array
    {
        return $this->failed;
    }

    public function successfulCount(): int
    {
        return count($this->successful);
    }

    public function failedCount(): int
    {
        return count($this->failed);
    }

    public function totalCount(): int
    {
        return $this->successfulCount() + $this->failedCount();
    }

    public function hasFailures(): bool
    {
        return $this->failedCount() > 0;
    }

    public function count(): int
    {
        return $this->totalCount();
    }
}
