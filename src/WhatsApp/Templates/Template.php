<?php

namespace RSE\Delivra\WhatsApp\Templates;

use Illuminate\Support\Str;
use RSE\Delivra\WhatsApp\Messages\TemplateMessage;

/**
 * Class based template, one class per approved Meta template.
 */
abstract class Template
{
    /**
     * Meta template name or config alias. Defaults to the snake cased class name.
     */
    protected ?string $name = null;

    protected ?string $language = null;

    protected ?string $account = null;

    protected ?string $from = null;

    protected ?string $tracking = null;

    /**
     * Header variable, if the template header has one.
     */
    public function header(): Parameter|string|null
    {
        return null;
    }

    /**
     * Body variables. String keys for named params, a list for positional.
     *
     * @return array<int|string, mixed>
     */
    public function body(): array
    {
        return [];
    }

    /**
     * Button variables keyed by button index.
     *
     * @return array<int, Button>
     */
    public function buttons(): array
    {
        return [];
    }

    public function name(): string
    {
        return $this->name ?? Str::snake(class_basename($this));
    }

    public function toMessage(): TemplateMessage
    {
        $message = TemplateMessage::make($this->name(), $this->language)
            ->header($this->header())
            ->body($this->body())
            ->buttons($this->buttons())
            ->source($this);

        foreach (['account', 'from', 'tracking'] as $option) {
            if ($this->{$option} !== null) {
                $message->{$option}($this->{$option});
            }
        }

        return $message;
    }
}
