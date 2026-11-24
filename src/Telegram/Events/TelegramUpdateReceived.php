<?php

namespace RSE\Delivra\Telegram\Events;

use RSE\Delivra\Telegram\Updates\Update;

class TelegramUpdateReceived
{
    public function __construct(
        public readonly Update $update,
        public readonly ?string $token = null,
    ) {}
}
