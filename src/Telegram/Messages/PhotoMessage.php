<?php

namespace RSE\Delivra\Telegram\Messages;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RSE\Delivra\Exceptions\Telegram\ApiErrorException;

class PhotoMessage extends TelegramMessageAbstract
{
    protected ?string $photo = null;

    protected ?string $caption = null;

    public function photo(string $path): self
    {
        $this->photo = $path;

        return $this;
    }

    public function setPhoto(string $photoPath): self
    {
        return $this->photo($photoPath);
    }

    public function caption(string $caption): self
    {
        $this->caption = $caption;

        return $this;
    }

    public function setCaption(string $caption): self
    {
        return $this->caption($caption);
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
            'photo' => $this->photo,
            'caption' => $this->caption,
        ];
    }

    public function send(): \RSE\Delivra\Telegram\TelegramResponse
    {
        $this->validateTokenAndReceivers();

        $response = Http::timeout(config('delivra.http.timeout', 10))
            ->connectTimeout(config('delivra.http.connect_timeout', 5))
            ->retry(config('delivra.http.retries', 3), config('delivra.http.retry_delay', 100))
            ->acceptJson()
            ->asMultipart()
            ->attach('photo', File::get($this->photo), basename($this->photo))
            ->post("https://api.telegram.org/bot{$this->token}/sendPhoto", $this->prepareBasicParameters($this->receivers[0], [
                'caption' => $this->caption,
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
