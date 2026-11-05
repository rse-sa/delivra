<?php

namespace RSE\Delivra\WhatsApp;

use Countable;
use Throwable;

class WhatsAppBatchResponse implements Countable
{
    /** @var array<string, WhatsAppResponse> */
    protected array $successful = [];

    /** @var array<string, Throwable> */
    protected array $failed = [];

    public function addSuccess(string $recipient, WhatsAppResponse $response): static
    {
        $this->successful[$recipient] = $response;

        return $this;
    }

    public function addFailure(string $recipient, Throwable $exception): static
    {
        $this->failed[$recipient] = $exception;

        return $this;
    }

    /** @return array<string, WhatsAppResponse> */
    public function successful(): array
    {
        return $this->successful;
    }

    /** @return array<string, Throwable> */
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
        return $this->failed !== [];
    }

    public function count(): int
    {
        return $this->totalCount();
    }
}
