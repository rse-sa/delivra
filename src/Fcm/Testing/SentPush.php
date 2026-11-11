<?php

namespace RSE\Delivra\Fcm\Testing;

use RSE\Delivra\Fcm\Messages\FcmMessage;
use RSE\Delivra\Fcm\Target;

class SentPush
{
    public function __construct(
        public readonly string $project,
        public readonly Target $target,
        public readonly FcmMessage $message,
        public readonly array $payload,
    ) {}

    public function token(): ?string
    {
        return $this->target->isToken() ? $this->target->value : null;
    }

    public function topic(): ?string
    {
        return $this->target->type === Target::TOPIC ? $this->target->value : null;
    }

    public function condition(): ?string
    {
        return $this->target->type === Target::CONDITION ? $this->target->value : null;
    }
}
