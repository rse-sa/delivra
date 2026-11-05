<?php

namespace RSE\Delivra\WhatsApp\Messages;

use RSE\Delivra\Exceptions\WhatsApp\InvalidMessageException;

/**
 * Interactive list message, allowed inside the 24 hour window.
 */
class ListMessage extends Message
{
    /** @var array<string, array<string, string|array{title: string, description?: string}>> */
    protected array $sections = [];

    protected ?string $header = null;

    protected ?string $footer = null;

    public function __construct(protected string $body = '', protected string $buttonText = 'Options') {}

    public static function make(string $body = '', string $buttonText = 'Options'): static
    {
        return new static($body, $buttonText);
    }

    /**
     * @param  array<string, string|array{title: string, description?: string}>  $rows  id => title or id => [title, description]
     */
    public function section(string $title, array $rows): static
    {
        $this->sections[$title] = $rows;

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
        if ($this->sections === []) {
            throw new InvalidMessageException('WhatsApp list message needs at least one section.');
        }

        $sections = collect($this->sections)->map(fn (array $rows, string $title) => [
            'title' => $title,
            'rows'  => collect($rows)->map(fn (string|array $row, string|int $id) => array_filter([
                'id'          => (string) $id,
                'title'       => is_array($row) ? $row['title'] : $row,
                'description' => is_array($row) ? ($row['description'] ?? null) : null,
            ]))->values()->all(),
        ])->values()->all();

        return array_filter([
            'type'   => 'list',
            'header' => $this->header ? ['type' => 'text', 'text' => $this->header] : null,
            'body'   => ['text' => $this->body],
            'footer' => $this->footer ? ['text' => $this->footer] : null,
            'action' => ['button' => $this->buttonText, 'sections' => $sections],
        ]);
    }

    public function description(): string
    {
        return $this->body;
    }
}
