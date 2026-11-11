<?php

namespace RSE\Delivra\Exceptions\Fcm;

/**
 * Broken setup: missing credentials, wrong project id, missing permission.
 * The only FCM failure that throws during a send.
 */
class FcmConfigurationException extends FcmException
{
    public function __construct(string $message, public readonly ?int $status = null, public readonly ?array $response = null)
    {
        parent::__construct($message);
    }
}
