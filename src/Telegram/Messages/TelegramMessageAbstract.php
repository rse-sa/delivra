<?php

namespace RSE\Delivra\Telegram\Messages;

use RSE\Delivra\Exceptions\Telegram\InvalidChatIdException;
use RSE\Delivra\Exceptions\Telegram\InvalidTokenException;
use RSE\Delivra\Telegram\Telegram;
use RSE\Delivra\Telegram\TelegramBatchResponse;
use RSE\Delivra\Telegram\TelegramResponse;

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
        return new static;
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

    public function getReceivers(): array
    {
        return $this->receivers;
    }

    /**
     * Null keeps the message without a token, so Telegram::send() falls back to the default one.
     */
    public function token(?string $token): self
    {
        $this->token = $token;

        return $this;
    }

    public function setToken(?string $token): self
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
        return $this->addRawButton([
            'text' => $title,
            'url'  => $url,
        ]);
    }

    /**
     * Telegram sends the data back in a callback_query update. Max 64 bytes.
     */
    public function addCallbackButton(string $title, string $data): self
    {
        return $this->addRawButton([
            'text'          => $title,
            'callback_data' => $data,
        ]);
    }

    /**
     * Adds the button to the current keyboard row.
     */
    public function addRawButton(array $payload): self
    {
        if ($this->buttons === []) {
            $this->buttons[] = [];
        }

        $this->buttons[array_key_last($this->buttons)][] = $payload;

        return $this;
    }

    /**
     * Buttons added after this go on a new keyboard row.
     */
    public function newRow(): self
    {
        if ($this->buttons !== [] && end($this->buttons) !== []) {
            $this->buttons[] = [];
        }

        return $this;
    }

    public function getButtons(): array
    {
        return array_values(array_filter($this->buttons));
    }

    protected function validateTokenAndReceivers(): void
    {
        if (empty($this->receivers)) {
            throw new InvalidChatIdException('');
        }

        if (empty($this->token)) {
            throw new InvalidTokenException;
        }
    }

    protected function prepareBasicParameters(string $chatId, array $extra = []): array
    {
        $params = array_merge([
            'chat_id'              => $chatId,
            'parse_mode'           => $this->parseMode,
            'protect_content'      => $this->protectContent,
            'disable_notification' => $this->silently,
        ], $extra);

        if ($this->getButtons() !== []) {
            $params['reply_markup'] = json_encode([
                'inline_keyboard' => $this->getButtons(),
            ]);
        }

        // drop only nulls: false and 0 are real values (is_anonymous, correct_option_id)
        return array_filter($params, fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Sends through the Telegram manager: default token and chat, sending events, and a batch for many receivers.
     * Null when a DelivraMessageSending listener cancelled it.
     */
    public function send(): TelegramResponse|TelegramBatchResponse|null
    {
        return app(Telegram::class)->send($this);
    }

    /**
     * Local files to upload, keyed by the Bot API field name.
     *
     * @return array<string, string>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * A path to an existing file is uploaded. Anything else (URL, Telegram file_id) goes as text.
     */
    protected function isLocalFile(?string $value): bool
    {
        return $value !== null && is_file($value);
    }

    /**
     * The value to put in the payload for a file field: null when the file is uploaded instead.
     */
    protected function fileField(?string $value): ?string
    {
        return $this->isLocalFile($value) ? null : $value;
    }

    /**
     * @param  array<string, ?string>  $files
     * @return array<string, string>
     */
    protected function localFiles(array $files): array
    {
        return array_filter($files, fn ($value) => $this->isLocalFile($value));
    }

    /**
     * Bot API parameters without chat_id; the manager adds it per receiver.
     */
    abstract public function toArray(): array;
}
