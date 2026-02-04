<?php

namespace RSE\Delivra\Sms;

use Psr\Http\Message\ResponseInterface;

class SmsResponse
{
    protected string $driver;

    protected string $recipient;

    protected string $body;

    protected bool $successful = true;

    protected ?string $response = null;

    protected ?float $credits = null;

    protected ?string $messageId = null;

    public function __construct($driver, $recipient, $body)
    {
        $this->driver = $driver;
        $this->recipient = $recipient;
        $this->body = $body;
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    public function getRecipient(): string
    {
        return $this->recipient;
    }

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getCredits(): ?float
    {
        return $this->credits;
    }

    public function setCredits(?float $credit): self
    {
        $this->credits = $credit;

        return $this;
    }

    public function setSuccessful(): self
    {
        $this->successful = true;

        return $this;
    }

    public function setFailed(): self
    {
        $this->successful = false;

        return $this;
    }

    public function setMessageId(?string $messageId): self
    {
        $this->messageId = $messageId;

        return $this;
    }

    public function failedIf(bool $condition): self
    {
        $this->successful = !$condition;

        return $this;
    }

    public function successful(): bool
    {
        return $this->successful;
    }

    public function failed(): bool
    {
        return !$this->successful;
    }

    public function setResponseAsError(): self
    {
        return $this->setFailed();
    }

    public function setResponse($response): self
    {
        if ($response instanceof ResponseInterface) {
            $response = $response->getBody()->getContents();
        }

        $this->response = $response;

        return $this;
    }

    public function getResponse(): string
    {
        return $this->response;
    }

    public function getResponseArray(): ?array
    {
        return json_decode($this->response, true);
    }

    public function toArray(): array
    {
        return [
            'driver' => $this->driver,
            'recipient' => $this->recipient ?? '',
            'body' => $this->body ?? '',
            'status' => $this->successful() ? 'success' : 'failure',
            'response' => $this->response,
        ];
    }
}
