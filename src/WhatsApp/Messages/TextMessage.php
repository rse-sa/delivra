<?php

namespace RSE\Delivra\WhatsApp\Messages;

class TextMessage extends Message
{
    public function __construct(protected string $body = '', protected bool $previewUrl = false) {}

    public static function make(string $body = ''): static
    {
        return new static($body);
    }

    public function body(string $body): static
    {
        $this->body = $body;

        return $this;
    }

    public function previewUrl(bool $preview = true): static
    {
        $this->previewUrl = $preview;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function type(): string
    {
        return 'text';
    }

    protected function content(): array
    {
        return [
            'body'        => $this->body,
            'preview_url' => $this->previewUrl,
        ];
    }

    public function description(): string
    {
        return $this->body;
    }
}
