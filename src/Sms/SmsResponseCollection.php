<?php

namespace RSE\Delivra\Sms;

use Illuminate\Support\Collection;

class SmsResponseCollection extends Collection
{
    public function append(string $key, SmsResponse $smsResponse): self
    {
        return parent::put($key, $smsResponse);
    }

    public function getErrors(): array
    {
        return $this->map(function (SmsResponse $response) {
            return $response->failed() ? $response->getResponse() : false;
        })->filter()->toArray();
    }

    public function success(): bool
    {
        return $this->successCount() == $this->count();
    }

    public function failed(): bool
    {
        return $this->failureCount() > 0;
    }

    public function successCount(): int
    {
        return $this->map(function (SmsResponse $response) {
            return $response->successful() ? 1 : 0;
        })->sum();
    }

    public function failureCount(): int
    {
        return $this->map(function (SmsResponse $response) {
            return $response->failed() ? 1 : 0;
        })->sum();
    }

    public function credits(): float
    {
        return $this->map(function (SmsResponse $response) {
            return $response->getCredits() ?? 0;
        })->sum();
    }
}
