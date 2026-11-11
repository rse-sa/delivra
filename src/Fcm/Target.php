<?php

namespace RSE\Delivra\Fcm;

use Illuminate\Support\Str;
use RSE\Delivra\Exceptions\Fcm\InvalidMessageException;

/**
 * Where a message goes: one device token, a topic, or a condition.
 */
class Target
{
    public const TOKEN = 'token';

    public const TOPIC = 'topic';

    public const CONDITION = 'condition';

    public function __construct(public readonly string $type, public readonly string $value) {}

    public static function token(string $token): static
    {
        return new static(self::TOKEN, $token);
    }

    public static function topic(string $topic): static
    {
        return new static(self::TOPIC, static::normalizeTopic($topic));
    }

    public static function condition(string $condition): static
    {
        return new static(self::CONDITION, $condition);
    }

    /**
     * @param  string|iterable<string>  $tokens
     * @return list<string>
     */
    public static function tokens(string|iterable $tokens): array
    {
        $tokens = is_string($tokens) ? [$tokens] : collect($tokens)->all();

        return array_values(array_unique(array_filter(array_map('strval', $tokens), fn (string $token) => trim($token) !== '')));
    }

    public static function normalizeTopic(string $topic): string
    {
        $topic = Str::after($topic, '/topics/');

        if (! preg_match('/^[a-zA-Z0-9\-_.~%]+$/', $topic)) {
            throw new InvalidMessageException("FCM topic [{$topic}] has invalid characters.");
        }

        return $topic;
    }

    public function isToken(): bool
    {
        return $this->type === self::TOKEN;
    }

    /**
     * Safe for logs and events: tokens show only their ends.
     */
    public function masked(): string
    {
        if (! $this->isToken()) {
            return "{$this->type}:{$this->value}";
        }

        return strlen($this->value) > 12
            ? substr($this->value, 0, 6) . '...' . substr($this->value, -4)
            : str_repeat('*', strlen($this->value));
    }

    /**
     * @return array<string, string>
     */
    public function toPayload(): array
    {
        return [$this->type => $this->value];
    }
}
