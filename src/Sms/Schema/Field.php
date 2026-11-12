<?php

namespace RSE\Delivra\Sms\Schema;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use RSE\Delivra\Sms\Schema\Concerns\ResolvesLabels;

class Field implements Arrayable, JsonSerializable
{
    use ResolvesLabels;

    protected ?string $label = null;

    protected string $type = 'text';

    protected bool $secret = false;

    protected array $rules = [];

    protected ?string $help = null;

    protected ?string $placeholder = null;

    protected mixed $default = null;

    protected ?array $options = null;

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
     * One of: text, password, url, number, boolean, select.
     */
    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Mark the value as a credential the app should encrypt and never echo back.
     */
    public function secret(bool $secret = true): static
    {
        $this->secret = $secret;

        if ($secret) {
            $this->type = 'password';
        }

        return $this;
    }

    public function rules(string|array $rules): static
    {
        $this->rules = is_string($rules) ? explode('|', $rules) : $rules;

        return $this;
    }

    public function help(string $help): static
    {
        $this->help = $help;

        return $this;
    }

    public function placeholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function default(mixed $default): static
    {
        $this->default = $default;

        return $this;
    }

    /**
     * Options for a select field, as value => label.
     */
    public function options(array $options): static
    {
        $this->options = $options;

        return $this->type('select');
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->resolveLabel($this->label, "delivra::sms.fields.{$this->name}", $this->name);
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getRules(): array
    {
        return $this->rules;
    }

    public function isSecret(): bool
    {
        return $this->secret;
    }

    public function isRequired(): bool
    {
        return in_array('required', $this->rules, true);
    }

    public function toArray(): array
    {
        return [
            'name'        => $this->name,
            'label'       => $this->getLabel(),
            'type'        => $this->type,
            'secret'      => $this->secret,
            'required'    => $this->isRequired(),
            'rules'       => array_map(fn ($rule) => match (true) {
                is_string($rule)          => $rule,
                $rule instanceof Closure  => 'closure',
                is_object($rule)          => $rule::class,
                default                   => (string) $rule,
            }, $this->rules),
            'help'        => $this->help,
            'placeholder' => $this->placeholder,
            'default'     => $this->default,
            'options'     => $this->options,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
