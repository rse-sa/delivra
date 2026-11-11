<?php

namespace RSE\Delivra\Fcm\Messages;

abstract class PlatformConfig
{
    protected array $fields = [];

    public static function make(array $fields = []): static
    {
        return (new static)->with($fields);
    }

    /**
     * Raw FCM fields for options Delivra does not model. Merged deeply.
     */
    public function with(array $fields): static
    {
        $this->fields = array_replace_recursive($this->fields, $fields);

        return $this;
    }

    /**
     * Set one field by dot path, e.g. "notification.sound".
     */
    public function set(string $path, mixed $value): static
    {
        data_set($this->fields, $path, $value);

        return $this;
    }

    public function get(string $path, mixed $default = null): mixed
    {
        return data_get($this->fields, $path, $default);
    }

    public function toArray(): array
    {
        return $this->fields;
    }
}
