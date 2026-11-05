<?php

namespace RSE\Delivra\WhatsApp\Webhook;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController
{
    /**
     * Meta subscription handshake (GET).
     */
    public function verify(Request $request): Response
    {
        $expected = (string) config('delivra.whatsapp.webhook.verify_token');

        // PHP turns "hub.mode" into "hub_mode"
        $valid = $request->query('hub_mode') === 'subscribe'
            && $expected !== ''
            && hash_equals($expected, (string) $request->query('hub_verify_token'));

        abort_unless($valid, 403);

        return new Response((string) $request->query('hub_challenge'), 200, ['Content-Type' => 'text/plain']);
    }

    public function handle(Request $request, WebhookHandler $handler): Response
    {
        $handler->handle($request->json()->all());

        return new Response('', 200);
    }
}
