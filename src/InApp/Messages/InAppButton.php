<?php

namespace RSE\Delivra\InApp\Messages;

use BackedEnum;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use RSE\Delivra\Exceptions\InApp\InvalidMessageException;
use RSE\Delivra\InApp\Messages\Concerns\HasLink;
use RSE\Delivra\InApp\Values\Value;

/**
 * @implements Arrayable<string, mixed>
 */
class InAppButton implements Arrayable, JsonSerializable
{
    use HasLink;

    public const METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    protected Value $label;

    protected string $method = 'GET';

    protected ?string $color = null;

    protected ?string $icon = null;

    protected array $params = [];

    protected bool $markAsRead = false;

    protected ?string $key = null;

    public function __construct(string|Value $label, array $labelParams = [])
    {
        $this->label($label, $labelParams);
    }

    public static function make(string|Value $label, array $labelParams = []): static
    {
        return new static($label, $labelParams);
    }

    public function label(string|Value $label, array $params = []): static
    {
        $this->label = Value::from($label, $params);

        return $this;
    }

    public function method(string $method): static
    {
        $method = strtoupper($method);

        if (! in_array($method, static::METHODS, true)) {
            throw new InvalidMessageException("In-app button method [{$method}] is not one of " . implode(', ', static::METHODS) . '.');
        }

        $this->method = $method;

        return $this;
    }

    public function get(): static
    {
        return $this->method('GET');
    }

    public function post(): static
    {
        return $this->method('POST');
    }

    public function put(): static
    {
        return $this->method('PUT');
    }

    public function patch(): static
    {
        return $this->method('PATCH');
    }

    public function delete(): static
    {
        return $this->method('DELETE');
    }

    public function color(string|BackedEnum|null $color): static
    {
        $this->color = $color instanceof BackedEnum ? (string) $color->value : $color;

        return $this;
    }

    public function icon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Extra data the UI sends with the request, a form body for POST buttons. JSON-safe values only.
     */
    public function params(array $params): static
    {
        $this->params = array_merge($this->params, $params);

        return $this;
    }

    public function markAsRead(bool $markAsRead = true): static
    {
        $this->markAsRead = $markAsRead;

        return $this;
    }

    /**
     * A stable id for the UI. Defaults to the button's position.
     */
    public function key(?string $key): static
    {
        $this->key = $key;

        return $this;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function toArray(): array
    {
        return [
            'key'       => $this->key,
            'label'     => $this->label->toArray(),
            'link'      => $this->linkToArray(),
            'method'    => $this->method,
            'color'     => $this->color,
            'icon'      => $this->icon,
            'params'    => (object) $this->params,
            'mark_read' => $this->markAsRead,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
