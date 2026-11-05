<?php

namespace RSE\Delivra\WhatsApp\Events;

use RSE\Delivra\WhatsApp\Webhook\InboundMessage;

class WhatsAppMessageReceived
{
    public function __construct(public readonly InboundMessage $message) {}
}
