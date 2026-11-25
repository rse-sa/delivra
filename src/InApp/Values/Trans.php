<?php

namespace RSE\Delivra\InApp\Values;

/**
 * A translation key. Its params can be values too, so they render in the same locale.
 */
class Trans extends Value
{
    public readonly array $params;

    public function __construct(public readonly string $key, array $params = [])
    {
        $this->params = static::serializeParams($params);
    }

    public function render(RenderContext $context): string
    {
        return (string) app('translator')->get($this->key, static::renderParams($this->params, $context), $context->locale);
    }

    public function toArray(): array
    {
        return ['t' => 'trans', 'k' => $this->key, 'p' => (object) $this->params];
    }

    public static function fromArray(array $payload): static
    {
        return new static((string) ($payload['k'] ?? ''), (array) ($payload['p'] ?? []));
    }
}
