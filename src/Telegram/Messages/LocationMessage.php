<?php

namespace RSE\Delivra\Telegram\Messages;

class LocationMessage extends TelegramMessageAbstract
{
    protected ?float $latitude = null;

    protected ?float $longitude = null;

    public function latitude(float $lat): self
    {
        $this->latitude = $lat;

        return $this;
    }

    public function setLatitude(float $lat): self
    {
        return $this->latitude($lat);
    }

    public function longitude(float $lng): self
    {
        $this->longitude = $lng;

        return $this;
    }

    public function setLongitude(float $lng): self
    {
        return $this->longitude($lng);
    }

    public function getReceivers(): array
    {
        return $this->receivers;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function toArray(): array
    {
        return $this->prepareBasicParameters('', [
            'latitude'  => $this->latitude,
            'longitude' => $this->longitude,
        ]);
    }
}
