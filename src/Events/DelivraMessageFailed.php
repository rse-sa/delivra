<?php

namespace RSE\Delivra\Events;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DelivraMessageFailed
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $channel,
        public readonly string $recipient,
        public readonly string $body,
        public readonly string $error,
        public readonly ?Throwable $exception = null,
        public readonly mixed $notifiable = null,
        public readonly mixed $notification = null,
        public readonly ?object $message = null,
    ) {}
}
