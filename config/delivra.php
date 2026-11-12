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
                'app_sid'  => env('DELIVRA_SMS_UNIFONIC_APP_SID', ''),
                'username' => env('DELIVRA_SMS_UNIFONIC_USERNAME', ''),
                'password' => env('DELIVRA_SMS_UNIFONIC_PASSWORD', ''),
                'sender'   => env('DELIVRA_SMS_UNIFONIC_SENDER', ''),
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
    | WhatsApp Configuration (Meta Cloud API)
    |--------------------------------------------------------------------------
    |
    | Each account is one WhatsApp Business Account (WABA) with its own token
    | and any number of phone numbers (name => phone_number_id).
    */

    'whatsapp' => [
        'default'       => env('DELIVRA_WHATSAPP_ACCOUNT', 'default'),
        'graph_url'     => env('DELIVRA_WHATSAPP_GRAPH_URL', 'https://graph.facebook.com'),
        'graph_version' => env('DELIVRA_WHATSAPP_GRAPH_VERSION', 'v24.0'),

        // Prefix for local numbers starting with 0, e.g. "966" turns 0501234567 into 966501234567.
        'default_country_code' => env('DELIVRA_WHATSAPP_COUNTRY_CODE'),

        'default_language' => env('DELIVRA_WHATSAPP_LANGUAGE', 'en_US'),

        'accounts' => [
            'default' => [
                'token'               => env('DELIVRA_WHATSAPP_TOKEN'),
                'business_account_id' => env('DELIVRA_WHATSAPP_BUSINESS_ACCOUNT_ID'),
                'app_secret'          => env('DELIVRA_WHATSAPP_APP_SECRET'),
                'default_number'      => 'main',
                'numbers'             => [
                    'main' => env('DELIVRA_WHATSAPP_PHONE_NUMBER_ID'),
                ],
            ],
        ],

        /*
        | Template aliases. Code uses the alias, the env picks the real Meta name.
        |
        | 'order_ready' => [
        |     'name'     => env('WHATSAPP_ORDER_READY_TEMPLATE'),
        |     'language' => 'ar',
        |     'account'  => 'default',
        |     'from'     => 'main',
        | ],
        */
        'templates' => [],

        // Check params against the approved Meta definition before sending (cached).
        'validate_templates'  => env('DELIVRA_WHATSAPP_VALIDATE_TEMPLATES', false),
        'templates_cache_ttl' => env('DELIVRA_WHATSAPP_TEMPLATES_CACHE_TTL', 3600),

        'webhook' => [
            'enabled'      => env('DELIVRA_WHATSAPP_WEBHOOK', false),
            'path'         => env('DELIVRA_WHATSAPP_WEBHOOK_PATH', 'delivra/whatsapp/webhook'),
            'verify_token' => env('DELIVRA_WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
            'middleware'   => [],

            // Seconds to remember processed inbound message ids (Meta retries webhooks).
            'deduplicate_for' => 86400,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging (HTTP v1)
    |--------------------------------------------------------------------------
    |
    | Each project is one Firebase project with its service account.
    | Use "credentials" (path to the JSON file, or the JSON itself), or the
    | three single values when you keep them in .env.
    */

    'fcm' => [
        'default' => env('DELIVRA_FCM_PROJECT', 'default'),

        'projects' => [
            'default' => [
                'credentials'  => env('DELIVRA_FCM_CREDENTIALS'),
                'project_id'   => env('DELIVRA_FCM_PROJECT_ID'),
                'client_email' => env('DELIVRA_FCM_CLIENT_EMAIL'),
                'private_key'  => env('DELIVRA_FCM_PRIVATE_KEY'),
            ],
        ],

        // Notification channel name. Null skips registration, via() can still use FcmChannel::class.
        'channel' => env('DELIVRA_FCM_CHANNEL', 'fcm'),

        // Parallel requests per chunk of tokens.
        'concurrency' => env('DELIVRA_FCM_CONCURRENCY', 50),

        // Transient failures (429, 5xx) are retried once. Max seconds to wait, Retry-After is capped by it.
        'retry_max_delay' => env('DELIVRA_FCM_RETRY_MAX_DELAY', 5),

        'url'       => env('DELIVRA_FCM_URL', 'https://fcm.googleapis.com'),
        'iid_url'   => env('DELIVRA_FCM_IID_URL', 'https://iid.googleapis.com'),
        'oauth_url' => env('DELIVRA_FCM_OAUTH_URL', 'https://oauth2.googleapis.com/token'),
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
