<?php

namespace RSE\Delivra\Fcm\Events;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

/**
 * FCM says the token is dead (app uninstalled, token expired). Delete or disable it.
 */
class FcmTokenInvalid
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  string  $reason  FCM error code, e.g. UNREGISTERED or NOT_FOUND
     */
    public function __construct(
        public readonly string $token,
        public readonly string $reason,
        public readonly string $project,
        public readonly mixed $notifiable = null,
        public readonly mixed $notification = null,
    ) {}
}
