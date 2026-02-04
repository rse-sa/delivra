<?php

namespace RSE\Delivra\Telegram\Messages;

class TextMessage extends TelegramMessageAbstract
{
    protected string $message = '';

    protected ?string $title = null;

    public function message(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function setMessage(string $message): self
    {
        return $this->message($message);
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function setTitle(string $title): self
    {
        return $this->title($title);
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
        $params = $this->prepareBasicParameters('', [
            'text' => $this->prepareMessage(),
        ]);

        unset($params['chat_id']);

        return $params;
    }

    public function getMessage(): string
    {
        return $this->prepareMessage();
    }

    protected function prepareMessage(): string
    {
        $message = '';

        if ($this->message) {
            $message = str_replace(
                ['<br>', '<br/>', '<BR>', '<BR/>'],
                "\r\n",
                $this->message
            );
        }

        if ($this->title) {
            $message = '<strong>' . $this->title . "</strong>\r\n" . $message;
        }

        return $message;
    }
}
