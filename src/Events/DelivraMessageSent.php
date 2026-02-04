<?php

namespace RSE\Delivra\Events;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

class DelivraMessageSent
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $channel,
        public readonly string $recipient,
        public readonly string $body,
        public readonly ?string $messageId,
        public readonly mixed $response,
        public readonly mixed $notifiable = null,
        public readonly mixed $notification = null,
        public readonly ?object $message = null,
    ) {}
}
