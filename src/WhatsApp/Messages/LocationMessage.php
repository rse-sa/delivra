<?php

namespace RSE\Delivra\WhatsApp\Messages;

class LocationMessage extends Message
{
    public function __construct(
        protected float $latitude,
        protected float $longitude,
        protected ?string $name = null,
        protected ?string $address = null,
    ) {}

    public static function make(float $latitude, float $longitude, ?string $name = null, ?string $address = null): static
    {
        return new static($latitude, $longitude, $name, $address);
    }

    public function type(): string
    {
        return 'location';
    }

    protected function content(): array
    {
        return array_filter([
            'latitude'  => $this->latitude,
            'longitude' => $this->longitude,
            'name'      => $this->name,
            'address'   => $this->address,
        ], fn ($value) => $value !== null);
    }

    public function description(): string
    {
        return $this->name ?? "[location] {$this->latitude},{$this->longitude}";
    }
}
