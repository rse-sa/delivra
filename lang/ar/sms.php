<?php

return [
    'drivers' => [
        'unifonic'  => 'يونيفونك',
        'msegat'    => 'مسجات',
        'yamamah'   => 'اليمامة',
        'shamelsms' => 'شامل SMS',
    ],

    'auth_modes' => [
        'api_key'     => 'مفتاح API',
        'credentials' => 'اسم المستخدم وكلمة المرور',
        'app_sid'     => 'معرف التطبيق وبيانات الحساب',
    ],

    'fields' => [
        'app_sid'  => 'معرف التطبيق (App SID)',
        'username' => 'اسم المستخدم',
        'password' => 'كلمة المرور',
        'key'      => 'مفتاح API',
        'api_key'  => 'مفتاح API',
        'sender'   => 'اسم المرسل',
    ],

    'features' => [
        'bulk'    => 'الإرسال الجماعي',
        'balance' => 'الاستعلام عن الرصيد',
        'credits' => 'حساب تكلفة الرسائل',
    ],
];
