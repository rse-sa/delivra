<?php

namespace RSE\Delivra\Sms;

use Illuminate\Support\Arr;

class SmsBuilder
{
    protected array $recipients = [];

    public static function make(): self
    {
        return new static;
    }

    protected string $body = '';

    protected ?string $driver = null;

    protected ?string $sender = null;

    protected bool $withCredits = false;

    public function to($recipients): self
    {
        $this->recipients = Arr::wrap($recipients);

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function body($body): self
    {
        $this->body = $body;

        return $this;
    }

    public function withoutCredits(): self
    {
        $this->withCredits = false;

        return $this;
    }

    public function withCredits(bool $enabled = true): self
    {
        $this->withCredits = $enabled;

        return $this;
    }

    public function shouldIncludeCredits(): bool
    {
        return $this->withCredits;
    }

    public function via($driver): self
    {
        $this->driver = $driver;

        return $this;
    }

    /**
     * Send from this sender name instead of the driver's configured one.
     */
    public function from(?string $sender): self
    {
        $this->sender = $sender;

        return $this;
    }

    public function getSender(): ?string
    {
        return $this->sender;
    }

    public function getRecipients(): array
    {
        return $this->recipients;
    }

    public function getDriver(): ?string
    {
        return $this->driver;
    }
}
