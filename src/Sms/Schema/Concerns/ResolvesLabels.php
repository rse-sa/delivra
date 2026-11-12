<?php

namespace RSE\Delivra\Sms\Schema\Concerns;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

trait ResolvesLabels
{
    protected function resolveLabel(?string $label, string $translationKey, string $name): string
    {
        if ($label !== null) {
            return $label;
        }

        return Lang::has($translationKey) ? __($translationKey) : Str::headline($name);
    }
}
