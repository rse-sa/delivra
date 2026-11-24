<?php

use Illuminate\Support\Facades\Route;
use RSE\Delivra\Telegram\Webhook\WebhookController;

Route::middleware(config('delivra.telegram.webhook.middleware', []))
    ->post(config('delivra.telegram.webhook.path', 'delivra/telegram/webhook'), WebhookController::class)
    ->name('delivra.telegram.webhook');
