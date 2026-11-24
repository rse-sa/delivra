<?php

namespace RSE\Delivra\WebPush\Console;

use Illuminate\Console\Command;

class VapidCommand extends Command
{
    protected $signature = 'delivra:webpush-vapid';

    protected $description = 'Generate a VAPID key pair for web push';

    public function handle(): int
    {
        $manager = webpush();

        if ($manager->publicKey()) {
            $this->components->warn('VAPID keys are already set. New keys stop every saved subscription, so browsers must subscribe again.');
        }

        $keys = $manager->createVapidKeys();

        $this->line('Add these lines to your .env file:');
        $this->newLine();
        $this->line("DELIVRA_WEBPUSH_PUBLIC_KEY={$keys['publicKey']}");
        $this->line("DELIVRA_WEBPUSH_PRIVATE_KEY={$keys['privateKey']}");

        return self::SUCCESS;
    }
}
