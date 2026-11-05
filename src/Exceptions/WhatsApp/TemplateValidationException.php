<?php

namespace RSE\Delivra\Exceptions\WhatsApp;

class TemplateValidationException extends WhatsAppException
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(public readonly string $template, public readonly array $errors)
    {
        parent::__construct("WhatsApp template [{$template}] is invalid: " . implode(' ', $errors));
    }
}
