<?php

namespace RSE\Delivra\InApp\Values;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use RSE\Delivra\Exceptions\InApp\InvalidPayloadException;
use Stringable;
use UnitEnum;

/**
 * Text that is stored now and rendered later, in the reader's locale and timezone.
 * Stored as a tagged array: ['t' => 'trans', ...].
 *
 * @implements Arrayable<string, mixed>
 */
abstract class Value implements Arrayable, JsonSerializable
{
    /**
     * Tag => class. Add your own value type here and give it a fromArray().
     *
     * @var array<string, class-string<Value>>
     */
    public static array $types = [
        'text'   => Text::class,
        'trans'  => Trans::class,
        'choice' => Choice::class,
        'date'   => Date::class,

        'translations' => Translations::class,
    ];

    abstract public function render(RenderContext $context): string;

    /**
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;

    /**
     * A plain string is a translation key.
     */
    public static function from(string|Value $value, array $params = []): Value
    {
        return $value instanceof Value ? $value : new Trans($value, $params);
    }

    /**
     * Each type builds itself from its stored array.
     */
    abstract public static function fromArray(array $payload): static;

    /**
     * A stored array back to the right value type.
     */
    public static function hydrate(array $payload): Value
    {
        $class = Value::$types[$payload['t'] ?? null] ?? null;

        if ($class === null) {
            throw new InvalidPayloadException('Unknown in-app value type [' . ($payload['t'] ?? 'none') . '].');
        }

        return $class::fromArray($payload);
    }

    public static function isPayload(mixed $value): bool
    {
        return is_array($value) && isset($value['t']) && isset(Value::$types[$value['t']]);
    }

    /**
     * Params as they go into the JSON column. Names and numbers stay as they are.
     */
    public static function serializeParams(array $params): array
    {
        return array_map(fn ($param) => match (true) {
            $param instanceof Value             => $param->toArray(),
            $param instanceof BackedEnum        => $param->value,
            $param instanceof UnitEnum          => $param->name,
            $param instanceof DateTimeInterface => Date::datetime($param)->toArray(),
            $param instanceof Stringable        => (string) $param,
            default                             => $param,
        }, $params);
    }

    /**
     * Stored params back to strings for the translator.
     */
    public static function renderParams(array $params, RenderContext $context): array
    {
        return array_map(fn ($param) => match (true) {
            static::isPayload($param) => Value::hydrate($param)->render($context),
            is_array($param)          => json_encode($param),
            default                   => (string) $param,
        }, $params);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
