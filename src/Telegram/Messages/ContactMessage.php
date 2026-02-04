<?php

namespace RSE\Delivra\Telegram\Messages;

use Illuminate\Support\Facades\Http;
use RSE\Delivra\Exceptions\Telegram\ApiErrorException;

class ContactMessage extends TelegramMessageAbstract
{
    protected ?string $phoneNumber = null;

    protected ?string $firstName = null;

    protected ?string $lastName = null;

    protected ?string $vCard = null;

    public function phone(string $number): self
    {
        $this->phoneNumber = $number;

        return $this;
    }

    public function setPhone(string $number): self
    {
        return $this->phone($number);
    }

    public function firstName(string $name): self
    {
        $this->firstName = $name;

        return $this;
    }

    public function setFirstName(string $name): self
    {
        return $this->firstName($name);
    }

    public function lastName(string $name): self
    {
        $this->lastName = $name;

        return $this;
    }

    public function setLastName(string $name): self
    {
        return $this->lastName($name);
    }

    public function vCard(string $vcard): self
    {
        $this->vCard = $vcard;

        return $this;
    }

    public function setVCard(string $vcard): self
    {
        return $this->vCard($vcard);
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
            'phone_number' => $this->phoneNumber,
            'first_name'   => $this->firstName,
            'last_name'    => $this->lastName,
            'vcard'        => $this->vCard,
        ];
    }

    public function send(): \RSE\Delivra\Telegram\TelegramResponse
    {
        $this->validateTokenAndReceivers();

        $response = Http::timeout(config('delivra.http.timeout', 10))
            ->connectTimeout(config('delivra.http.connect_timeout', 5))
            ->retry(config('delivra.http.retries', 3), config('delivra.http.retry_delay', 100))
            ->acceptJson()
            ->post("https://api.telegram.org/bot{$this->token}/sendContact", $this->prepareBasicParameters($this->receivers[0], [
                'phone_number' => $this->phoneNumber,
                'first_name'   => $this->firstName,
                'last_name'    => $this->lastName,
                'vcard'        => $this->vCard,
            ]));

        $result = $response->json();

        if (! ($result['ok'] ?? false)) {
            throw new ApiErrorException(
                $result['error_code'] ?? 'UNKNOWN',
                $result['description'] ?? 'Unknown error',
                $result
            );
        }

        return new \RSE\Delivra\Telegram\TelegramResponse($this->receivers[0], $result);
    }
}
