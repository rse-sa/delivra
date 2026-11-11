<?php

namespace RSE\Delivra\Tests\Feature;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use RSE\Delivra\Events\DelivraMessageSending;
use RSE\Delivra\Sms\Channels\SmsChannel;
use RSE\Delivra\Sms\SmsBuilder;
use RSE\Delivra\Telegram\Channels\TelegramChannel;
use RSE\Delivra\Telegram\Messages\TextMessage;
use RSE\Delivra\Telegram\Telegram;
use RSE\Delivra\Tests\TestCase;

/**
 * A DelivraMessageSending listener that returns false skips that recipient.
 */
class SendingListenerCancelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        Event::listen(DelivraMessageSending::class, fn () => false);
    }

    public function test_whatsapp_send_is_cancelled(): void
    {
        $this->assertNull(whatsapp()->to('966500000001')->text('Hello'));

        Http::assertNothingSent();
    }

    public function test_sms_channel_send_is_cancelled(): void
    {
        $notification = new class extends Notification
        {
            public function toSms($notifiable)
            {
                return SmsBuilder::make()->body('Hello')->to(['966500000001']);
            }
        };

        $this->assertSame([], (new SmsChannel(app('delivra-sms')))->send(new \stdClass, $notification));
    }

    public function test_telegram_channel_send_is_cancelled(): void
    {
        $notification = new class extends Notification
        {
            public function toTelegram($notifiable)
            {
                return TextMessage::make()->message('Hello')->token('test-token')->to('123456');
            }
        };

        (new TelegramChannel(new Telegram(['default_token' => 'test-token'])))->send(new \stdClass, $notification);

        Http::assertNothingSent();
    }
}
