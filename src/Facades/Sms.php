<?php

namespace RSE\Delivra\Facades;

use Illuminate\Support\Facades\Facade;

class Sms extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'delivra-sms';
    }
}
