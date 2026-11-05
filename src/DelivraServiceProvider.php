<?php

namespace RSE\Delivra;

use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\ServiceProvider;
use RSE\Delivra\Sms\Channels\SmsChannel;
use RSE\Delivra\Sms\Sms;
use RSE\Delivra\Telegram\Channels\TelegramChannel;
use RSE\Delivra\Telegram\Telegram;
use RSE\Delivra\WhatsApp\Channels\WhatsAppChannel;
use RSE\Delivra\WhatsApp\Console\ListTemplatesCommand;
use RSE\Delivra\WhatsApp\Console\MakeTemplateCommand;
use RSE\Delivra\WhatsApp\Webhook\WebhookHandler;
use RSE\Delivra\WhatsApp\WhatsAppManager;

class DelivraServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/delivra.php', 'delivra');

        $this->app->singleton('delivra-sms', function ($app) {
            return new Sms($app->make('config')->get('delivra.sms'));
        });

        $this->app->singleton('delivra-telegram', function ($app) {
            return new Telegram($app->make('config')->get('delivra.telegram'));
        });

        $this->app->singleton('delivra-whatsapp', function ($app) {
            return new WhatsAppManager($app->make('config')->get('delivra.whatsapp', []));
        });

        $this->app->alias('delivra-whatsapp', WhatsAppManager::class);

        $this->app->bind(WebhookHandler::class, function ($app) {
            return new WebhookHandler(
                $app->make('delivra-whatsapp'),
                (int) $app->make('config')->get('delivra.whatsapp.webhook.deduplicate_for', 86400),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/delivra.php' => config_path('delivra.php'),
            ], 'delivra-config');

            $this->publishes([
                __DIR__ . '/../stubs/whatsapp-template.stub' => base_path('stubs/whatsapp-template.stub'),
            ], 'delivra-stubs');

            $this->commands([
                ListTemplatesCommand::class,
                MakeTemplateCommand::class,
            ]);
        }

        if (config('delivra.whatsapp.webhook.enabled')) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/whatsapp.php');
        }

        $this->app->afterResolving(ChannelManager::class, function (ChannelManager $manager) {
            $manager->extend('sms', function ($app) {
                return new SmsChannel($app->make('delivra-sms'));
            });

            $manager->extend('telegram', function ($app) {
                return new TelegramChannel($app->make('delivra-telegram'));
            });

            $manager->extend('whatsapp', function () {
                return new WhatsAppChannel;
            });
        });
    }
}
