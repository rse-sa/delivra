<?php

namespace RSE\Delivra\WhatsApp\Messages;

class ReactionMessage extends Message
{
    /**
     * @param  string  $emoji  empty string removes the reaction
     */
    public function __construct(protected string $messageId, protected string $emoji) {}

    public static function make(string $messageId, string $emoji): static
    {
        return new static($messageId, $emoji);
    }

    public function type(): string
    {
        return 'reaction';
    }

    protected function content(): array
    {
        return ['message_id' => $this->messageId, 'emoji' => $this->emoji];
    }

    public function description(): string
    {
        return "[reaction] {$this->emoji}";
    }
}
