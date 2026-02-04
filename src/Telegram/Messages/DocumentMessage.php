<?php

namespace RSE\Delivra\Telegram\Messages;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RSE\Delivra\Exceptions\Telegram\ApiErrorException;

class DocumentMessage extends TelegramMessageAbstract
{
    protected ?string $document = null;

    protected ?string $thumbnail = null;

    protected ?string $caption = null;

    public function document(string $path): self
    {
        $this->document = $path;

        return $this;
    }

    public function setDocument(string $path): self
    {
        return $this->document($path);
    }

    public function thumbnail(string $path): self
    {
        $this->thumbnail = $path;

        return $this;
    }

    public function setThumbnail(string $path): self
    {
        return $this->thumbnail($path);
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
            'caption' => $this->caption,
        ];
    }

    public function send(): \RSE\Delivra\Telegram\TelegramResponse
    {
        $this->validateTokenAndReceivers();

        $request = Http::timeout(config('delivra.http.timeout', 10))
            ->connectTimeout(config('delivra.http.connect_timeout', 5))
            ->retry(config('delivra.http.retries', 3), config('delivra.http.retry_delay', 100))
            ->acceptJson()
            ->asMultipart()
            ->attach('document', File::get($this->document), basename($this->document));

        if ($this->thumbnail) {
            $request->attach('thumbnail', File::get($this->thumbnail), basename($this->thumbnail));
        }

        $response = $request->post("https://api.telegram.org/bot{$this->token}/sendDocument", $this->prepareBasicParameters($this->receivers[0], [
            'caption' => $this->caption,
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
