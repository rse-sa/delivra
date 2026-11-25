<?php

namespace RSE\Delivra\InApp\Values;

use Illuminate\Support\Arr;

/**
 * Text the app holds in each locale, such as a translatable model name.
 * Renders the reader's locale, then the fallback locale, then the first text stored.
 */
class Translations extends Value
{
    /**
     * @param  array<string, string>  $translations  locale => text
     */
    public function __construct(public readonly array $translations) {}

    public function render(RenderContext $context): string
    {
        return (string) ($this->translations[$context->locale]
            ?? $this->translations[config('app.fallback_locale')]
            ?? Arr::first($this->translations)
            ?? '');
    }

    public function toArray(): array
    {
        // an object, so an empty list stays {} in the JSON column
        return ['t' => 'translations', 'v' => (object) $this->translations];
    }

    public static function fromArray(array $payload): static
    {
        return new static((array) ($payload['v'] ?? []));
    }
}
