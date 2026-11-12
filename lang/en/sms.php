<?php

return [
    'drivers' => [
        'unifonic'  => 'Unifonic',
        'msegat'    => 'Msegat',
        'yamamah'   => 'Yamamah',
        'shamelsms' => 'Shamel SMS',
    ],

    'auth_modes' => [
        'api_key'     => 'API key',
        'credentials' => 'Username and password',
        'app_sid'     => 'App SID and account',
    ],

    'fields' => [
        'app_sid'  => 'App SID',
        'username' => 'Username',
        'password' => 'Password',
        'key'      => 'API key',
        'api_key'  => 'API key',
        'sender'   => 'Sender name',
    ],

    'features' => [
        'bulk'    => 'Bulk sending',
        'balance' => 'Balance check',
        'credits' => 'Cost calculation',
    ],
];
