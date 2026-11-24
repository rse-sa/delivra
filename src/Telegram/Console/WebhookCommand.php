<?php

namespace RSE\Delivra\Telegram\Console;

use Illuminate\Console\Command;
use RSE\Delivra\Telegram\Telegram;

class WebhookCommand extends Command
{
    protected $signature = 'delivra:telegram-webhook
                            {action=info : set, delete or info}
                            {--url= : Webhook URL, defaults to the delivra.telegram.webhook route}
                            {--secret= : Secret token, defaults to delivra.telegram.webhook.secret_token}
                            {--token= : Bot token, defaults to delivra.telegram.default_token}';

    protected $description = 'Set, delete or show the Telegram webhook of a bot';

    public function handle(Telegram $telegram): int
    {
        $token = $this->option('token') ?: config('delivra.telegram.default_token');

        match ($this->argument('action')) {
            'set' => $telegram->setWebhook(
                $url = $this->option('url') ?: route('delivra.telegram.webhook'),
                $this->option('secret') ?: config('delivra.telegram.webhook.secret_token'),
                token: $token,
            ),
            'delete' => $telegram->deleteWebhook($token),
            default  => null,
        };

        foreach ($telegram->getWebhookInfo($token) as $key => $value) {
            $this->components->twoColumnDetail($key, is_scalar($value) ? (string) $value : json_encode($value));
        }

        return self::SUCCESS;
    }
}
