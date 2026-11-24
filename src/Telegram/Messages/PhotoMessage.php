<?php

namespace RSE\Delivra\Telegram\Messages;

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

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    /**
     * A local path is uploaded; a URL or file_id goes in the photo field.
     */
    public function toArray(): array
    {
        return $this->prepareBasicParameters('', [
            'photo'   => $this->fileField($this->photo),
            'caption' => $this->caption,
        ]);
    }

    public function attachments(): array
    {
        return $this->localFiles(['photo' => $this->photo]);
    }
}
