<?php

namespace RSE\Delivra;

use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\ServiceProvider;
use RSE\Delivra\Fcm\Channels\FcmChannel;
use RSE\Delivra\Fcm\Console\TestCommand as FcmTestCommand;
use RSE\Delivra\Fcm\FcmManager;
use RSE\Delivra\Sms\Channels\SmsChannel;
use RSE\Delivra\Sms\Sms;
use RSE\Delivra\Telegram\Channels\TelegramChannel;
use RSE\Delivra\Telegram\Console\PollCommand as TelegramPollCommand;
use RSE\Delivra\Telegram\Console\WebhookCommand as TelegramWebhookCommand;
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

        $this->app->alias('delivra-telegram', Telegram::class);

        $this->app->alias('delivra-whatsapp', WhatsAppManager::class);

        $this->app->singleton('delivra-fcm', function ($app) {
            return new FcmManager($app->make('config')->get('delivra.fcm', []));
        });

        $this->app->alias('delivra-fcm', FcmManager::class);

        $this->app->bind(WebhookHandler::class, function ($app) {
            return new WebhookHandler(
                $app->make('delivra-whatsapp'),
                (int) $app->make('config')->get('delivra.whatsapp.webhook.deduplicate_for', 86400),
            );
        });
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'delivra');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../lang' => $this->app->langPath('vendor/delivra'),
            ], 'delivra-lang');

            $this->publishes([
                __DIR__ . '/../config/delivra.php' => config_path('delivra.php'),
            ], 'delivra-config');

            $this->publishes([
                __DIR__ . '/../stubs/whatsapp-template.stub' => base_path('stubs/whatsapp-template.stub'),
            ], 'delivra-stubs');

            $this->commands([
                ListTemplatesCommand::class,
                MakeTemplateCommand::class,
                FcmTestCommand::class,
                TelegramWebhookCommand::class,
                TelegramPollCommand::class,
            ]);
        }

        if (config('delivra.whatsapp.webhook.enabled')) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/whatsapp.php');
        }

        if (config('delivra.telegram.webhook.enabled')) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/telegram.php');
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

            if ($name = config('delivra.fcm.channel')) {
                $manager->extend($name, function () {
                    return new FcmChannel;
                });
            }
        });
    }
}
