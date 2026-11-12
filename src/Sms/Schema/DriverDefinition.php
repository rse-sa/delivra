<?php

namespace RSE\Delivra\Sms\Schema;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use LogicException;
use RSE\Delivra\Sms\Schema\Concerns\ResolvesLabels;
use RSE\Delivra\Sms\SmsFeature;

class DriverDefinition implements Arrayable, JsonSerializable
{
    use ResolvesLabels;

    public const AUTH_MODE_KEY = 'auth_mode';

    protected string $name = '';

    protected ?string $label = null;

    /** @var SmsFeature[] */
    protected array $features = [];

    /** @var AuthMode[] */
    protected array $authModes = [];

    /** @var Field[] */
    protected array $fields = [];

    public static function make(): static
    {
        return new static;
    }

    /**
     * The driver's config key. The Sms manager sets it when resolving the definition.
     */
    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @param  SmsFeature[]  $features
     */
    public function features(array $features): static
    {
        $this->features = $features;

        return $this;
    }

    /**
     * @param  AuthMode[]  $authModes
     */
    public function authModes(array $authModes): static
    {
        $this->authModes = $authModes;

        return $this;
    }

    /**
     * Fields shared by every auth mode, like the sender name.
     *
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
        return $this->resolveLabel($this->label, "delivra::sms.drivers.{$this->name}", $this->name);
    }

    /**
     * @return SmsFeature[]
     */
    public function getFeatures(): array
    {
        return $this->features;
    }

    public function supports(SmsFeature|string $feature): bool
    {
        $feature = is_string($feature) ? SmsFeature::from($feature) : $feature;

        return in_array($feature, $this->features, true);
    }

    /**
     * @return AuthMode[]
     */
    public function getAuthModes(): array
    {
        return $this->authModes;
    }

    /**
     * @return Field[]
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function hasAuthModeChoice(): bool
    {
        return count($this->authModes) > 1;
    }

    /**
     * The auth mode the given settings use: settings[auth_mode] when the driver
     * has a choice, otherwise its only mode.
     */
    public function authModeFor(array $settings): ?AuthMode
    {
        if (! $this->hasAuthModeChoice()) {
            return $this->authModes[0] ?? null;
        }

        foreach ($this->authModes as $mode) {
            if ($mode->getName() === ($settings[self::AUTH_MODE_KEY] ?? null)) {
                return $mode;
            }
        }

        return null;
    }

    /**
     * Laravel validation rules for the driver's settings, covering every auth mode.
     */
    public function rules(): array
    {
        $this->ensureUniqueFieldNames();

        $rules = [];

        if ($this->hasAuthModeChoice()) {
            $modes = array_map(fn (AuthMode $mode) => $mode->getName(), $this->authModes);

            $rules[self::AUTH_MODE_KEY] = ['required', 'in:' . implode(',', $modes)];

            foreach ($this->authModes as $mode) {
                foreach ($mode->getFields() as $field) {
                    // drops other modes' fields from the validated data
                    $rules[$field->getName()] = ['exclude_unless:' . self::AUTH_MODE_KEY . ',' . $mode->getName(), ...$field->getRules()];
                }
            }
        } else {
            foreach (($this->authModes[0] ?? null)?->getFields() ?? [] as $field) {
                $rules[$field->getName()] = $field->getRules();
            }
        }

        foreach ($this->fields as $field) {
            $rules[$field->getName()] = $field->getRules();
        }

        return $rules;
    }

    public function toArray(): array
    {
        $this->ensureUniqueFieldNames();

        return [
            'name'       => $this->name,
            'label'      => $this->getLabel(),
            'features'   => array_map(fn (SmsFeature $feature) => $feature->value, $this->features),
            'auth_modes' => array_map(fn (AuthMode $mode) => $mode->toArray(), $this->authModes),
            'fields'     => array_map(fn (Field $field) => $field->toArray(), $this->fields),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    protected function ensureUniqueFieldNames(): void
    {
        $names = [];

        foreach ([...$this->authModes, null] as $mode) {
            foreach ($mode ? $mode->getFields() : $this->fields as $field) {
                if (in_array($field->getName(), $names, true) || $field->getName() === self::AUTH_MODE_KEY) {
                    throw new LogicException("SMS driver [{$this->name}] declares the field [{$field->getName()}] more than once.");
                }

                $names[] = $field->getName();
            }
        }
    }
}
