<?php

namespace RSE\Delivra\InApp\Values;

/**
 * Literal text, never translated. For imported rows or text the app has in one language only.
 */
class Text extends Value
{
    public function __construct(public readonly string $text) {}

    public function render(RenderContext $context): string
    {
        return $this->text;
    }

    public function toArray(): array
    {
        return ['t' => 'text', 'v' => $this->text];
    }

    public static function fromArray(array $payload): static
    {
        return new static((string) ($payload['v'] ?? ''));
    }
}
