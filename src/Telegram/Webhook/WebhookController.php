<?php

namespace RSE\Delivra\Telegram\Webhook;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use RSE\Delivra\Telegram\Events\TelegramUpdateReceived;
use RSE\Delivra\Telegram\Updates\Update;

/**
 * Receives updates for the default bot (delivra.telegram.default_token).
 */
class WebhookController
{
    public function __invoke(Request $request): Response
    {
        $secret = (string) config('delivra.telegram.webhook.secret_token');

        abort_unless($secret !== '' && hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token')), 403);

        $update = Update::fromArray($request->json()->all());

        if (! static::alreadyProcessed($update)) {
            event(new TelegramUpdateReceived($update, config('delivra.telegram.default_token')));
        }

        return new Response('', 200);
    }

    /**
     * Telegram resends an update until it gets a 200, so skip update ids we have seen.
     */
    public static function alreadyProcessed(Update $update, string $scope = 'default'): bool
    {
        $seconds = (int) config('delivra.telegram.webhook.deduplicate_for', 86400);

        if ($update->id === 0 || $seconds <= 0) {
            return false;
        }

        return ! Cache::add("delivra:telegram:update:{$scope}:{$update->id}", true, $seconds);
    }
}
