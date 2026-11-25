<?php

namespace RSE\Delivra\InApp\Values;

/**
 * A plural translation, trans_choice() at read time.
 */
class Choice extends Value
{
    public readonly array $params;

    public function __construct(public readonly string $key, public readonly int|float $count, array $params = [])
    {
        $this->params = static::serializeParams($params);
    }

    public function render(RenderContext $context): string
    {
        return (string) app('translator')->choice($this->key, $this->count, static::renderParams($this->params, $context), $context->locale);
    }

    public function toArray(): array
    {
        return ['t' => 'choice', 'k' => $this->key, 'c' => $this->count, 'p' => (object) $this->params];
    }

    public static function fromArray(array $payload): static
    {
        return new static((string) ($payload['k'] ?? ''), $payload['c'] ?? 0, (array) ($payload['p'] ?? []));
    }
}
