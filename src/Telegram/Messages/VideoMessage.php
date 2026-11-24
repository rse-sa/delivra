<?php

namespace RSE\Delivra\Telegram\Messages;

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

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    /**
     * Local paths are uploaded; URLs or file_ids go in the video and thumbnail fields.
     */
    public function toArray(): array
    {
        return $this->prepareBasicParameters('', [
            'video'     => $this->fileField($this->video),
            'thumbnail' => $this->fileField($this->thumbnail),
            'caption'   => $this->caption,
            'width'     => $this->width,
            'height'    => $this->height,
            'duration'  => $this->duration,
        ]);
    }

    public function attachments(): array
    {
        return $this->localFiles(['video' => $this->video, 'thumbnail' => $this->thumbnail]);
    }
}
