<?php

namespace RSE\Delivra\Facades;

use Illuminate\Support\Facades\Facade;

class Telegram extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'delivra-telegram';
    }
}
