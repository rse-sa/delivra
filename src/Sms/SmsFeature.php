<?php

namespace RSE\Delivra\Sms;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

enum SmsFeature: string
{
    // provider has a native multi-recipient API
    case Bulk = 'bulk';

    // getBalance() returns a real value
    case Balance = 'balance';

    // provider calculates message cost, not the local estimate
    case Credits = 'credits';

    public function label(): string
    {
        $key = "delivra::sms.features.{$this->value}";

        return Lang::has($key) ? __($key) : Str::headline($this->value);
    }
}
