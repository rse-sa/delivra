<?php

namespace RSE\Delivra\Telegram\Updates;

/**
 * One Telegram update, flattened to what bots usually need. The full payload stays in $raw.
 */
class Update
{
    public const MESSAGE = 'message';

    public const CALLBACK_QUERY = 'callback_query';

    public const MY_CHAT_MEMBER = 'my_chat_member';

    public const OTHER = 'other';

    public function __construct(
        public readonly int $id,
        public readonly string $type,
        public readonly ?string $chatId = null,
        public readonly ?string $chatType = null,
        public readonly ?string $userId = null,
        public readonly ?string $username = null,
        public readonly ?string $firstName = null,
        public readonly ?string $languageCode = null,
        public readonly ?string $text = null,
        public readonly ?int $messageId = null,
        public readonly ?string $callbackId = null,
        public readonly ?string $callbackData = null,
        public readonly ?string $memberStatus = null,
        public readonly array $raw = [],
    ) {}

    public static function fromArray(array $payload): self
    {
        $type = match (true) {
            isset($payload['message'])        => self::MESSAGE,
            isset($payload['callback_query']) => self::CALLBACK_QUERY,
            isset($payload['my_chat_member']) => self::MY_CHAT_MEMBER,
            default                           => self::OTHER,
        };

        $body = match ($type) {
            self::MESSAGE        => $payload['message'],
            self::CALLBACK_QUERY => $payload['callback_query'],
            self::MY_CHAT_MEMBER => $payload['my_chat_member'],
            default              => [],
        };

        $chat    = $type === self::CALLBACK_QUERY ? ($body['message']['chat'] ?? []) : ($body['chat'] ?? []);
        $message = $type === self::CALLBACK_QUERY ? ($body['message'] ?? []) : $body;

        return new self(
            id: (int) ($payload['update_id'] ?? 0),
            type: $type,
            chatId: isset($chat['id']) ? (string) $chat['id'] : null,
            chatType: $chat['type'] ?? null,
            userId: isset($body['from']['id']) ? (string) $body['from']['id'] : null,
            username: $body['from']['username'] ?? null,
            firstName: $body['from']['first_name'] ?? null,
            languageCode: $body['from']['language_code'] ?? null,
            text: $type === self::MESSAGE ? ($body['text'] ?? null) : null,
            messageId: isset($message['message_id']) ? (int) $message['message_id'] : null,
            callbackId: $type === self::CALLBACK_QUERY ? ($body['id'] ?? null) : null,
            callbackData: $type === self::CALLBACK_QUERY ? ($body['data'] ?? null) : null,
            memberStatus: $type === self::MY_CHAT_MEMBER ? ($body['new_chat_member']['status'] ?? null) : null,
            raw: $payload,
        );
    }

    public function isPrivate(): bool
    {
        return $this->chatType === 'private';
    }

    /**
     * "/start abc" gives "start". Null when the text is not a command.
     */
    public function command(): ?string
    {
        if ($this->text === null || ! preg_match('#^/([A-Za-z0-9_]+)(@\w+)?(\s|$)#', $this->text, $matches)) {
            return null;
        }

        return strtolower($matches[1]);
    }

    /**
     * "/start abc" gives "abc". Null when there is no argument.
     */
    public function commandArgument(): ?string
    {
        if ($this->command() === null) {
            return null;
        }

        $argument = trim((string) preg_replace('#^/\S+\s*#', '', $this->text));

        return $argument === '' ? null : $argument;
    }

    /**
     * The user blocked the bot (private chat) or removed it (group).
     */
    public function botWasBlocked(): bool
    {
        return $this->type === self::MY_CHAT_MEMBER && in_array($this->memberStatus, ['kicked', 'left'], true);
    }
}
