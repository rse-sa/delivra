<?php

namespace RSE\Delivra\Sms\Schema;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use RSE\Delivra\Sms\Schema\Concerns\ResolvesLabels;

class AuthMode implements Arrayable, JsonSerializable
{
    use ResolvesLabels;

    protected ?string $label = null;

    /** @var Field[] */
    protected array $fields = [];

    public function __construct(protected string $name) {}

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @param  Field[]  $fields
     */
    public function fields(array $fields): static
    {
        $this->fields = $fields;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->resolveLabel($this->label, "delivra::sms.auth_modes.{$this->name}", $this->name);
    }

    /**
     * @return Field[]
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function toArray(): array
    {
        return [
            'name'   => $this->name,
            'label'  => $this->getLabel(),
            'fields' => array_map(fn (Field $field) => $field->toArray(), $this->fields),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
