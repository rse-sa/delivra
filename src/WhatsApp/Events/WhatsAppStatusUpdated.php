<?php

namespace RSE\Delivra\WhatsApp\Events;

use RSE\Delivra\WhatsApp\Webhook\StatusUpdate;

class WhatsAppStatusUpdated
{
    public function __construct(public readonly StatusUpdate $status) {}
}
