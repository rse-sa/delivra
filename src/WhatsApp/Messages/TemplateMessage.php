<?php

namespace RSE\Delivra\WhatsApp\Messages;

use RSE\Delivra\WhatsApp\Templates\Button;
use RSE\Delivra\WhatsApp\Templates\Parameter;
use RSE\Delivra\WhatsApp\Templates\Template;

class TemplateMessage extends Message
{
    protected ?Parameter $header = null;

    /** @var array<int|string, Parameter> */
    protected array $body = [];

    /** @var array<int, Button> */
    protected array $buttons = [];

    /**
     * The config alias this message was resolved from, if any.
     */
    protected ?string $alias = null;

    /**
     * The class based template this message was built from, if any.
     */
    protected ?Template $source = null;

    public function __construct(protected string $name, protected ?string $language = null) {}

    public static function make(string $name, ?string $language = null): static
    {
        return new static($name, $language);
    }

    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function language(?string $language): static
    {
        $this->language = $language;

        return $this;
    }

    /**
     * Header variable. Plain strings become text, use Parameter::image() etc. for media.
     */
    public function header(Parameter|string|null $value): static
    {
        $this->header = $value === null ? null : Parameter::from($value);

        return $this;
    }

    /**
     * Body variables. String keys are named params, int keys are positional ({{1}}, {{2}}).
     *
     * @param  array<int|string, mixed>  $parameters
     */
    public function body(array $parameters): static
    {
        $this->body = array_map(fn ($value) => Parameter::from($value), $parameters);

        return $this;
    }

    public function with(string|int $key, mixed $value): static
    {
        $this->body[$key] = Parameter::from($value);

        return $this;
    }

    /**
     * @param  array<int, Button>  $buttons  keyed by the button index in the template
     */
    public function buttons(array $buttons): static
    {
        $this->buttons = $buttons;

        return $this;
    }

    public function button(int $index, Button $button): static
    {
        $this->buttons[$index] = $button;

        return $this;
    }

    public function urlButton(string $suffix, int $index = 0): static
    {
        return $this->button($index, Button::url($suffix));
    }

    public function quickReplyButton(string $payload, int $index = 0): static
    {
        return $this->button($index, Button::quickReply($payload));
    }

    public function copyCodeButton(string $code, int $index = 0): static
    {
        return $this->button($index, Button::copyCode($code));
    }

    public function alias(?string $alias): static
    {
        $this->alias = $alias;

        return $this;
    }

    public function source(?Template $template): static
    {
        $this->source = $template;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function getHeader(): ?Parameter
    {
        return $this->header;
    }

    /** @return array<int|string, Parameter> */
    public function getBody(): array
    {
        return $this->body;
    }

    /** @return array<int, Button> */
    public function getButtons(): array
    {
        return $this->buttons;
    }

    public function getAlias(): ?string
    {
        return $this->alias;
    }

    public function getSource(): ?Template
    {
        return $this->source;
    }

    /**
     * True when the given name matches the Meta name, the alias or the source class.
     */
    public function is(string $template): bool
    {
        return in_array($template, array_filter([$this->name, $this->alias, $this->source ? $this->source::class : null]), true);
    }

    public function type(): string
    {
        return 'template';
    }

    protected function content(): array
    {
        $components = [];

        if ($this->header) {
            $components[] = ['type' => 'header', 'parameters' => [$this->header->toArray()]];
        }

        if ($this->body !== []) {
            $components[] = [
                'type'       => 'body',
                'parameters' => collect($this->body)
                    ->map(fn (Parameter $parameter, int|string $key) => $parameter->toArray(is_string($key) ? $key : null))
                    ->values()
                    ->all(),
            ];
        }

        $buttons = $this->buttons;
        ksort($buttons);

        foreach ($buttons as $index => $button) {
            $components[] = $button->toArray($index);
        }

        return array_filter([
            'name'       => $this->name,
            'language'   => ['code' => $this->language],
            'components' => $components ?: null,
        ]);
    }

    public function description(): string
    {
        return "[template] {$this->name}";
    }
}
