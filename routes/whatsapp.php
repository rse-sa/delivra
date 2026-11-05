<?php

use Illuminate\Support\Facades\Route;
use RSE\Delivra\WhatsApp\Webhook\VerifyWebhookSignature;
use RSE\Delivra\WhatsApp\Webhook\WebhookController;

Route::middleware(config('delivra.whatsapp.webhook.middleware', []))->group(function () {
    $path = config('delivra.whatsapp.webhook.path', 'delivra/whatsapp/webhook');

    Route::get($path, [WebhookController::class, 'verify'])->name('delivra.whatsapp.webhook.verify');

    Route::post($path, [WebhookController::class, 'handle'])
        ->middleware(VerifyWebhookSignature::class)
        ->name('delivra.whatsapp.webhook');
});
