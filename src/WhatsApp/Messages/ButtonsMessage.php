<?php

namespace RSE\Delivra\WhatsApp\Messages;

use RSE\Delivra\Exceptions\WhatsApp\InvalidMessageException;

/**
 * Interactive reply buttons (max 3), allowed inside the 24 hour window.
 */
class ButtonsMessage extends Message
{
    /** @var array<string, string> */
    protected array $buttons = [];

    protected ?string $header = null;

    protected ?string $footer = null;

    public function __construct(protected string $body = '') {}

    public static function make(string $body = ''): static
    {
        return new static($body);
    }

    public function body(string $body): static
    {
        $this->body = $body;

        return $this;
    }

    public function button(string $id, string $title): static
    {
        $this->buttons[$id] = $title;

        return $this;
    }

    /**
     * @param  array<string, string>  $buttons  id => title
     */
    public function buttons(array $buttons): static
    {
        $this->buttons = $buttons;

        return $this;
    }

    public function header(?string $text): static
    {
        $this->header = $text;

        return $this;
    }

    public function footer(?string $text): static
    {
        $this->footer = $text;

        return $this;
    }

    public function type(): string
    {
        return 'interactive';
    }

    protected function content(): array
    {
        if ($this->buttons === [] || count($this->buttons) > 3) {
            throw new InvalidMessageException('WhatsApp reply buttons message needs 1 to 3 buttons.');
        }

        return array_filter([
            'type'   => 'button',
            'header' => $this->header ? ['type' => 'text', 'text' => $this->header] : null,
            'body'   => ['text' => $this->body],
            'footer' => $this->footer ? ['text' => $this->footer] : null,
            'action' => [
                'buttons' => collect($this->buttons)->map(fn (string $title, string|int $id) => [
                    'type'  => 'reply',
                    'reply' => ['id' => (string) $id, 'title' => $title],
                ])->values()->all(),
            ],
        ]);
    }

    public function description(): string
    {
        return $this->body;
    }
}
