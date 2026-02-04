<?php

namespace RSE\Delivra\Telegram\Messages;

use RSE\Delivra\Exceptions\Telegram\InvalidChatIdException;
use RSE\Delivra\Exceptions\Telegram\InvalidTokenException;

abstract class TelegramMessageAbstract
{
    protected array $receivers = [];

    protected ?string $token = null;

    protected string $parseMode = 'html';

    protected array $buttons = [];

    protected bool $protectContent = false;

    protected bool $silently = false;

    public static function make(): self
    {
        return new static();
    }

    public function to(string|array $chatIds): self
    {
        $this->receivers = is_array($chatIds) ? $chatIds : [$chatIds];

        return $this;
    }

    public function addReceiver(string $chatId): self
    {
        $this->receivers[] = $chatId;

        return $this;
    }

    public function setReceivers(array $receivers): self
    {
        $this->receivers = $receivers;

        return $this;
    }

    public function token(string $token): self
    {
        $this->token = $token;

        return $this;
    }

    public function setToken(string $token): self
    {
        return $this->token($token);
    }

    public function setParseMode(string $parseMode): self
    {
        $this->parseMode = $parseMode;

        return $this;
    }

    public function asHtml(): self
    {
        return $this->setParseMode('html');
    }

    public function asMarkdown(): self
    {
        return $this->setParseMode('MarkdownV2');
    }

    public function withProtectedContent(): self
    {
        $this->protectContent = true;

        return $this;
    }

    public function silently(): self
    {
        $this->silently = true;

        return $this;
    }

    public function addUrlButton(string $title, string $url): self
    {
        $this->buttons[] = [
            'text' => $title,
            'url' => $url,
        ];

        return $this;
    }

    public function addRawButton(array $payload): self
    {
        $this->buttons[] = $payload;

        return $this;
    }

    protected function validateTokenAndReceivers(): void
    {
        if (empty($this->receivers)) {
            throw new InvalidChatIdException('');
        }

        if (empty($this->token)) {
            throw new InvalidTokenException();
        }
    }

    protected function prepareBasicParameters(string $chatId, array $extra = []): array
    {
        $params = array_merge([
            'chat_id' => $chatId,
            'parse_mode' => $this->parseMode,
            'protect_content' => $this->protectContent,
            'disable_notification' => $this->silently,
        ], $extra);

        if (count($this->buttons) > 0) {
            $params['reply_markup'] = json_encode([
                'inline_keyboard' => [[$this->buttons]],
            ]);
        }

        return array_filter($params);
    }

    abstract public function toArray(): array;
}
