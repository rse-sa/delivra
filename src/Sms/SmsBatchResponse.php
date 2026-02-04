<?php

namespace RSE\Delivra\Sms;

use Countable;

class SmsBatchResponse implements Countable
{
    protected array $successful = [];
    protected array $failed = [];

    public function addSuccess(string $recipient, SmsResponse $response): void
    {
        $this->successful[] = [
            'number' => $recipient,
            'response' => $response,
        ];
    }

    public function addFailure(string $recipient, string $error): void
    {
        $this->failed[] = [
            'number' => $recipient,
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
