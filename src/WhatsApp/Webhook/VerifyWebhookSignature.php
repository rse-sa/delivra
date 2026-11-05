<?php

namespace RSE\Delivra\WhatsApp\Webhook;

use Closure;
use Illuminate\Http\Request;
use RSE\Delivra\Exceptions\WhatsApp\WhatsAppException;
use RSE\Delivra\WhatsApp\WhatsAppManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checks X-Hub-Signature-256 against every configured app secret
 * (accounts may belong to different Meta apps).
 */
class VerifyWebhookSignature
{
    public function __construct(protected WhatsAppManager $manager) {}

    public function handle(Request $request, Closure $next): Response
    {
        $secrets = $this->manager->appSecrets();

        if ($secrets === []) {
            throw new WhatsAppException('No WhatsApp app_secret is configured, webhook signatures can not be verified.');
        }

        $signature = (string) $request->header('X-Hub-Signature-256');

        foreach ($secrets as $secret) {
            if (hash_equals('sha256=' . hash_hmac('sha256', $request->getContent(), $secret), $signature)) {
                return $next($request);
            }
        }

        abort(403, 'Invalid WhatsApp webhook signature.');
    }
}
