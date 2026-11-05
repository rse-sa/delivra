<?php

namespace RSE\Delivra\WhatsApp\Enums;

enum MessageStatus: string
{
    case Sent      = 'sent';
    case Delivered = 'delivered';
    case Read      = 'read';
    case Failed    = 'failed';

    public function isFinal(): bool
    {
        return $this === self::Read || $this === self::Failed;
    }
}
