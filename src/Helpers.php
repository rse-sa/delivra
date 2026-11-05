<?php

use RSE\Delivra\Sms\Sms;
use RSE\Delivra\Telegram\Telegram;
use RSE\Delivra\WhatsApp\WhatsAppManager;

if (! function_exists('sms')) {
    function sms(): Sms
    {
        return app('delivra-sms');
    }
}

if (! function_exists('telegram')) {
    function telegram(): Telegram
    {
        return app('delivra-telegram');
    }
}

if (! function_exists('whatsapp')) {
    function whatsapp(): WhatsAppManager
    {
        return app('delivra-whatsapp');
    }
}
