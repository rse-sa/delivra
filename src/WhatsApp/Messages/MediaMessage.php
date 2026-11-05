<?php

namespace RSE\Delivra\WhatsApp\Messages;

use Illuminate\Support\Str;

class MediaMessage extends Message
{
    protected ?string $caption = null;

    protected ?string $filename = null;

    /**
     * @param  string  $media  public link or uploaded media id
     */
    public function __construct(protected string $mediaType, protected string $media) {}

    public static function image(string $media, ?string $caption = null): static
    {
        return (new static('image', $media))->caption($caption);
    }

    public static function video(string $media, ?string $caption = null): static
    {
        return (new static('video', $media))->caption($caption);
    }

    public static function audio(string $media): static
    {
        return new static('audio', $media);
    }

    public static function document(string $media, ?string $filename = null, ?string $caption = null): static
    {
        return (new static('document', $media))->filename($filename)->caption($caption);
    }

    public static function sticker(string $media): static
    {
        return new static('sticker', $media);
    }

    public function caption(?string $caption): static
    {
        $this->caption = $caption;

        return $this;
    }

    public function filename(?string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    public function type(): string
    {
        return $this->mediaType;
    }

    protected function content(): array
    {
        return array_filter([
            Str::startsWith($this->media, ['http://', 'https://']) ? 'link' : 'id' => $this->media,
            'caption'                                                              => $this->caption,
            'filename'                                                             => $this->filename,
        ], fn ($value) => $value !== null);
    }

    public function description(): string
    {
        return $this->caption ?? $this->filename ?? "[{$this->mediaType}]";
    }
}
