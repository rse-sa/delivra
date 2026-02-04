<?php

use RSE\Delivra\Sms\Sms;
use RSE\Delivra\Telegram\Telegram;

if (!function_exists('sms')) {
    function sms(): Sms
    {
        return app('delivra-sms');
    }
}

if (!function_exists('telegram')) {
    function telegram(): Telegram
    {
        return app('delivra-telegram');
    }
}
