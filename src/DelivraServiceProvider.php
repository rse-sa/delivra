<?php

namespace RSE\Delivra;

use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\ServiceProvider;
use RSE\Delivra\Sms\Channels\SmsChannel;
use RSE\Delivra\Telegram\Channels\TelegramChannel;

class DelivraServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/delivra.php', 'delivra');

        $this->app->bind('delivra-sms', fn ($app) => new \RSE\Delivra\Sms\Sms(
            $app->make('config')->get('delivra.sms')
        ));

        $this->app->bind('delivra-telegram', fn ($app) => new \RSE\Delivra\Telegram\Telegram(
            $app->make('config')->get('delivra.telegram')
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/delivra.php' => config_path('delivra.php'),
            ], 'delivra-config');
        }

        $this->app->afterResolving(ChannelManager::class, function (ChannelManager $manager) {
            $manager->extend('sms', function ($app) {
                return new SmsChannel($app->make('delivra-sms'));
            });

            $manager->extend('telegram', function ($app) {
                return new TelegramChannel($app->make('delivra-telegram'));
            });
        });
    }
}
