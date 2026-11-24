<?php

namespace RSE\Delivra\Telegram\Console;

use Illuminate\Console\Command;
use RSE\Delivra\Telegram\Events\TelegramUpdateReceived;
use RSE\Delivra\Telegram\Telegram;

/**
 * Long polling for servers without public HTTPS, and for local work. The bot must have no webhook.
 */
class PollCommand extends Command
{
    protected $signature = 'delivra:telegram-poll
                            {--token= : Bot token, defaults to delivra.telegram.default_token}
                            {--timeout=30 : Seconds Telegram holds each request open}
                            {--once : Stop after the first batch}';

    protected $description = 'Receive Telegram updates by long polling and fire TelegramUpdateReceived';

    public function handle(Telegram $telegram): int
    {
        $token  = $this->option('token') ?: config('delivra.telegram.default_token');
        $offset = null;

        do {
            foreach ($telegram->getUpdates($offset, (int) $this->option('timeout'), $token) as $update) {
                $offset = $update->id + 1;

                event(new TelegramUpdateReceived($update, $token));
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }
}
