<?php

namespace RSE\Delivra\InApp\Messages;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Traits\Conditionable;
use JsonSerializable;
use RSE\Delivra\Exceptions\InApp\InvalidMessageException;
use RSE\Delivra\InApp\Messages\Concerns\HasLink;
use RSE\Delivra\InApp\Values\Value;

/**
 * A bell notification. Text is stored as translation keys and rendered when read,
 * so every reader sees it in their own locale.
 *
 * @implements Arrayable<string, mixed>
 */
class InAppMessage implements Arrayable, JsonSerializable
{
    use Conditionable;
    use HasLink;

    public const VERSION = 1;

    protected ?Value $title = null;

    protected ?Value $body = null;

    protected ?string $icon = null;

    protected ?string $color = null;

    protected ?string $category = null;

    protected ?Model $subject = null;

    protected ?Model $causer = null;

    protected ?Model $scope = null;

    // true after scope() or withoutScope(): the scope resolver is skipped
    protected bool $scopeChosen = false;

    /** @var list<InAppButton> */
    protected array $buttons = [];

    protected array $data = [];

    public function __construct(string|Value|null $title = null, array $params = [])
    {
        if ($title !== null) {
            $this->title($title, $params);
        }
    }

    public static function make(string|Value|null $title = null, array $params = []): static
    {
        return new static($title, $params);
    }

    /**
     * A translation key with its params, or a value such as InApp::text().
     */
    public function title(string|Value $title, array $params = []): static
    {
        $this->title = Value::from($title, $params);

        return $this;
    }

    public function body(string|Value|null $body, array $params = []): static
    {
        $this->body = $body === null ? null : Value::from($body, $params);

        return $this;
    }

    public function icon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function color(string|BackedEnum|null $color): static
    {
        $this->color = $color instanceof BackedEnum ? (string) $color->value : $color;

        return $this;
    }

    public function category(string|BackedEnum|null $category): static
    {
        $this->category = $category instanceof BackedEnum ? (string) $category->value : $category;

        return $this;
    }

    /**
     * The model the notification is about.
     */
    public function subject(?Model $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Who caused it.
     */
    public function causer(?Model $causer): static
    {
        $this->causer = $causer;

        return $this;
    }

    /**
     * The tenant, team or company it belongs to. Skips the InApp::scopeUsing() resolver.
     */
    public function scope(?Model $scope): static
    {
        $this->scope       = $scope;
        $this->scopeChosen = true;

        return $this;
    }

    /**
     * A system-wide notification: no scope, and the resolver is skipped.
     */
    public function withoutScope(): static
    {
        return $this->scope(null);
    }

    /**
     * Add a button. The closure gets the InAppButton to set its link, color and the rest.
     *
     * @param  (Closure(InAppButton): mixed)|null  $configure
     */
    public function button(string|Value|InAppButton $label, ?Closure $configure = null, array $labelParams = []): static
    {
        $button = $label instanceof InAppButton ? $label : InAppButton::make($label, $labelParams);

        if ($configure) {
            $configure($button);
        }

        $this->buttons[] = $button;

        return $this;
    }

    public function buttons(InAppButton ...$buttons): static
    {
        foreach ($buttons as $button) {
            $this->button($button);
        }

        return $this;
    }

    /**
     * Free data for the UI. Repeated calls merge. JSON-safe values only.
     */
    public function data(array $data): static
    {
        $this->data = array_merge($this->data, $data);

        return $this;
    }

    public function getTitle(): ?Value
    {
        return $this->title;
    }

    public function getBody(): ?Value
    {
        return $this->body;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function getSubject(): ?Model
    {
        return $this->subject;
    }

    public function getCauser(): ?Model
    {
        return $this->causer;
    }

    public function getScope(): ?Model
    {
        return $this->scope;
    }

    public function hasChosenScope(): bool
    {
        return $this->scopeChosen;
    }

    /**
     * @return list<InAppButton>
     */
    public function getButtons(): array
    {
        return $this->buttons;
    }

    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Short text for logs and Delivra events: the title key, or the literal title.
     */
    public function description(): string
    {
        $title = $this->title?->toArray() ?? [];

        return (string) ($title['k'] ?? $title['v'] ?? '');
    }

    /**
     * The JSON stored in the payload column.
     */
    public function toArray(): array
    {
        if ($this->title === null) {
            throw new InvalidMessageException('An in-app notification needs a title.');
        }

        $keys = [];

        $buttons = array_map(function (InAppButton $button, int $position) use (&$keys) {
            $key = $button->getKey() ?? (string) $position;

            if (isset($keys[$key])) {
                throw new InvalidMessageException("Two in-app buttons have the key [{$key}].");
            }

            $keys[$key] = true;

            return ['key' => $key] + $button->toArray();
        }, $this->buttons, array_keys($this->buttons));

        return [
            'v'       => static::VERSION,
            'title'   => $this->title->toArray(),
            'body'    => $this->body?->toArray(),
            'icon'    => $this->icon,
            'color'   => $this->color,
            'link'    => $this->linkToArray(),
            'buttons' => $buttons,
            'data'    => (object) $this->data,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
