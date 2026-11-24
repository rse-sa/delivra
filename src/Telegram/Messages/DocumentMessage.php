<?php

namespace RSE\Delivra\Telegram\Messages;

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

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    /**
     * Local paths are uploaded; URLs or file_ids go in the document and thumbnail fields.
     */
    public function toArray(): array
    {
        return $this->prepareBasicParameters('', [
            'document'  => $this->fileField($this->document),
            'thumbnail' => $this->fileField($this->thumbnail),
            'caption'   => $this->caption,
        ]);
    }

    public function attachments(): array
    {
        return $this->localFiles(['document' => $this->document, 'thumbnail' => $this->thumbnail]);
    }
}
