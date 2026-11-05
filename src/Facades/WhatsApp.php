<?php

namespace RSE\Delivra\Facades;

use Illuminate\Support\Facades\Facade;
use RSE\Delivra\WhatsApp\Testing\WhatsAppFake;
use RSE\Delivra\WhatsApp\WhatsAppManager;

/**
 * @method static \RSE\Delivra\WhatsApp\Account account(?string $name = null)
 * @method static \RSE\Delivra\WhatsApp\PendingWhatsApp to(string|array $recipients)
 * @method static \RSE\Delivra\WhatsApp\WhatsAppResponse|\RSE\Delivra\WhatsApp\WhatsAppBatchResponse|null send(\RSE\Delivra\WhatsApp\Messages\Message|\RSE\Delivra\WhatsApp\Templates\Template $message)
 * @method static \RSE\Delivra\WhatsApp\WhatsAppManager canSendUsing(?\Closure $callback)
 * @method static \RSE\Delivra\WhatsApp\Messages\TextMessage text(string $body)
 * @method static \RSE\Delivra\WhatsApp\Messages\TemplateMessage template(string $name, ?string $language = null)
 * @method static bool markAsRead(string $messageId, bool $typing = false)
 * @method static \RSE\Delivra\WhatsApp\Media downloadMedia(string $mediaId)
 * @method static \RSE\Delivra\WhatsApp\Templates\TemplateRegistry templates()
 *
 * @see WhatsAppManager
 */
class WhatsApp extends Facade
{
    public static function fake(): WhatsAppFake
    {
        $fake = new WhatsAppFake(static::getFacadeApplication()['config']->get('delivra.whatsapp', []));

        static::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return 'delivra-whatsapp';
    }
}
