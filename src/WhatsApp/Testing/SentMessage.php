<?php

namespace RSE\Delivra\WhatsApp\Testing;

use RSE\Delivra\WhatsApp\Messages\Message;
use RSE\Delivra\WhatsApp\Messages\TemplateMessage;
use RSE\Delivra\WhatsApp\Templates\Template;

class SentMessage
{
    public function __construct(
        public readonly string $account,
        public readonly string $phoneNumberId,
        public readonly string $to,
        public readonly Message $message,
        public readonly array $payload,
    ) {}

    /**
     * The class template when one was sent, otherwise the message.
     */
    public function source(): Message|Template
    {
        return $this->message instanceof TemplateMessage ? ($this->message->getSource() ?? $this->message) : $this->message;
    }

    public function isTemplate(?string $template = null): bool
    {
        return $this->message instanceof TemplateMessage && ($template === null || $this->message->is($template));
    }
}
