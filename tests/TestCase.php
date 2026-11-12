<?php

namespace RSE\Delivra\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use RSE\Delivra\DelivraServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('delivra.sms.default', 'null');
        $app['config']->set('delivra.sms.credits', false);
        $app['config']->set('delivra.sms.drivers', [
            'null'      => [],
            'unifonic'  => ['app_sid' => 'test-sid', 'username' => 'user', 'password' => 'secret', 'sender' => 'test-sender'],
            'msegat'    => ['username' => 'test', 'key' => 'test', 'sender' => 'test'],
            'yamamah'   => ['username' => 'test', 'password' => 'test', 'sender' => 'test'],
            'shamelsms' => ['username' => 'test', 'password' => 'test', 'sender' => 'test'],
        ]);

        $app['config']->set('delivra.telegram.default_token', 'test-token');
        $app['config']->set('delivra.telegram.default_chat_id', 'test-chat-id');
        $app['config']->set('delivra.telegram.parse_mode', 'html');

        $app['config']->set('delivra.whatsapp.default_country_code', '966');
        $app['config']->set('delivra.whatsapp.accounts', [
            'default' => [
                'token'               => 'default-token',
                'business_account_id' => 'waba-default',
                'app_secret'          => 'secret-default',
                'default_number'      => 'main',
                'numbers'             => ['main' => '1001', 'support' => '1002'],
            ],
            'sales' => [
                'token'               => 'sales-token',
                'business_account_id' => 'waba-sales',
                'app_secret'          => 'secret-sales',
                'numbers'             => ['main' => '2001'],
            ],
        ]);

        $app['config']->set('delivra.http.timeout', 10);
        $app['config']->set('delivra.http.connect_timeout', 5);
        $app['config']->set('delivra.http.retries', 3);
        $app['config']->set('delivra.http.retry_delay', 100);
    }

    protected function getPackageProviders($app): array
    {
        return [
            DelivraServiceProvider::class,
        ];
    }
}
