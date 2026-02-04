<?php

namespace RSE\Delivra\Telegram\Messages;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RSE\Delivra\Exceptions\Telegram\ApiErrorException;

class VideoMessage extends TelegramMessageAbstract
{
    protected ?string $video = null;

    protected ?string $thumbnail = null;

    protected ?string $caption = null;

    protected ?int $width = null;

    protected ?int $height = null;

    protected ?int $duration = null;

    public function video(string $path): self
    {
        $this->video = $path;

        return $this;
    }

    public function setVideo(string $path): self
    {
        return $this->video($path);
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

    public function width(int $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function setWidth(int $width): self
    {
        return $this->width($width);
    }

    public function height(int $height): self
    {
        $this->height = $height;

        return $this;
    }

    public function setHeight(int $height): self
    {
        return $this->height($height);
    }

    public function duration(int $seconds): self
    {
        $this->duration = $seconds;

        return $this;
    }

    public function setDuration(int $seconds): self
    {
        return $this->duration($seconds);
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
            'caption'   => $this->caption,
            'width'     => $this->width,
            'height'    => $this->height,
            'duration'  => $this->duration,
            'thumbnail' => $this->thumbnail,
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
            ->attach('video', File::get($this->video), basename($this->video));

        if ($this->thumbnail) {
            $request->attach('thumbnail', File::get($this->thumbnail), basename($this->thumbnail));
        }

        $response = $request->post("https://api.telegram.org/bot{$this->token}/sendVideo", $this->prepareBasicParameters($this->receivers[0], [
            'caption'  => $this->caption,
            'width'    => $this->width,
            'height'   => $this->height,
            'duration' => $this->duration,
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
