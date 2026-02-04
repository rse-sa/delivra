<?php

namespace RSE\Delivra\Traits;

trait SanitizesNumbers
{
    protected function sanitizeNumber(string $number): string
    {
        return preg_replace('/[\s\-+]+/', '', $number);
    }
}
