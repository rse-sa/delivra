<?php

namespace RSE\Delivra\Telegram\Messages;

use Illuminate\Support\Facades\Http;
use RSE\Delivra\Exceptions\Telegram\ApiErrorException;

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
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }

    public function send(): \RSE\Delivra\Telegram\TelegramResponse
    {
        $this->validateTokenAndReceivers();

        $response = Http::timeout(config('delivra.http.timeout', 10))
            ->connectTimeout(config('delivra.http.connect_timeout', 5))
            ->retry(config('delivra.http.retries', 3), config('delivra.http.retry_delay', 100))
            ->acceptJson()
            ->post("https://api.telegram.org/bot{$this->token}/sendLocation", $this->prepareBasicParameters($this->receivers[0], [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
            ]));

        $result = $response->json();

        if (!($result['ok'] ?? false)) {
            throw new ApiErrorException(
                $result['error_code'] ?? 'UNKNOWN',
                $result['description'] ?? 'Unknown error',
                $result
            );
        }

        return new \RSE\Delivra\Telegram\TelegramResponse($this->receivers[0], $result);
    }
}
