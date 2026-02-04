<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SMS Configuration
    |--------------------------------------------------------------------------
    */

    'sms' => [
        'default' => env('DELIVRA_SMS_DRIVER', 'null'),
        'credits' => env('DELIVRA_SMS_CREDITS', true),
        'drivers' => [
            'null'     => [],
            'unifonic' => [
                'key'    => env('DELIVRA_SMS_UNIFONIC_KEY', ''),
                'sender' => env('DELIVRA_SMS_UNIFONIC_SENDER', ''),
            ],
            'msegat' => [
                'username' => env('DELIVRA_SMS_MSEGAT_USERNAME', ''),
                'key'      => env('DELIVRA_SMS_MSEGAT_KEY', ''),
                'sender'   => env('DELIVRA_SMS_MSEGAT_SENDER', ''),
            ],
            'yamamah' => [
                'username' => env('DELIVRA_SMS_YAMAMAH_USERNAME', ''),
                'password' => env('DELIVRA_SMS_YAMAMAH_PASSWORD', ''),
                'sender'   => env('DELIVRA_SMS_YAMAMAH_SENDER', ''),
            ],
            'shamelsms' => [
                'username' => env('DELIVRA_SMS_SHAMELSMS_USERNAME', ''),
                'password' => env('DELIVRA_SMS_SHAMELSMS_PASSWORD', ''),
                'sender'   => env('DELIVRA_SMS_SHAMELSMS_SENDER', ''),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Telegram Configuration
    |--------------------------------------------------------------------------
    */

    'telegram' => [
        'default_token'   => env('DELIVRA_TELEGRAM_TOKEN'),
        'default_chat_id' => env('DELIVRA_TELEGRAM_CHAT_ID'),
        'parse_mode'      => env('DELIVRA_TELEGRAM_PARSE_MODE', 'html'),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Configuration
    |--------------------------------------------------------------------------
    |
    | Global timeouts and retries for all drivers to prevent hanging.
    */

    'http' => [
        'timeout'         => env('DELIVRA_HTTP_TIMEOUT', 10),
        'connect_timeout' => env('DELIVRA_HTTP_CONNECT_TIMEOUT', 5),
        'retries'         => env('DELIVRA_HTTP_RETRIES', 3),
        'retry_delay'     => env('DELIVRA_HTTP_RETRY_DELAY', 100),
    ],
];
