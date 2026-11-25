<?php

namespace RSE\Delivra\InApp\Values;

/**
 * Who reads the notification: the locale and timezone used for this render.
 */
class RenderContext
{
    public function __construct(
        public readonly string $locale,
        public readonly string $timezone,
    ) {}
}
