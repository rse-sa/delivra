<?php

namespace RSE\Delivra\Events;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

class DelivraMessageSending
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $channel,
        public readonly string $recipient,
        public readonly string $body,
        public readonly array $payload,
        public readonly ?string $driver = null,
        public readonly ?string $token = null,
        public readonly mixed $notifiable = null,
        public readonly mixed $notification = null,
        public readonly ?object $message = null,
    ) {}
}
