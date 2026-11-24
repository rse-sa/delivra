<?php

namespace RSE\Delivra\Tests\Feature;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RSE\Delivra\Fcm\Channels\FcmChannel;
use RSE\Delivra\Fcm\FcmManager;
use RSE\Delivra\Sms\Channels\SmsChannel;
use RSE\Delivra\Sms\Sms;
use RSE\Delivra\Sms\SmsBuilder;
use RSE\Delivra\Telegram\Channels\TelegramChannel;
use RSE\Delivra\Telegram\Telegram;
use RSE\Delivra\Tests\TestCase;
use RSE\Delivra\WebPush\Channels\WebPushChannel;
use RSE\Delivra\WebPush\WebPushManager;
use RSE\Delivra\WhatsApp\Channels\WhatsAppChannel;
use RSE\Delivra\WhatsApp\WhatsAppManager;

class CustomSendersTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach ([Telegram::class, Sms::class, WhatsAppManager::class, FcmManager::class, WebPushManager::class] as $manager) {
            $manager::flushSenders();
        }

        parent::tearDown();
    }

    /**
     * Every channel: [manager, channel, notification method, message factory].
     */
    public static function channels(): array
    {
        return [
            'telegram' => [Telegram::class, fn () => new TelegramChannel(telegram()), 'toTelegram', fn () => telegram()->text('Hi')],
            'sms'      => [Sms::class, fn () => new SmsChannel(app('delivra-sms')), 'toSms', fn () => SmsBuilder::make()->body('Hi')],
            'whatsapp' => [WhatsAppManager::class, fn () => new WhatsAppChannel, 'toWhatsApp', fn () => whatsapp()->text('Hi')],
            'fcm'      => [FcmManager::class, fn () => new FcmChannel, 'toFcm', fn () => fcm()->message('Title', 'Hi')],
            'webpush'  => [WebPushManager::class, fn () => new WebPushChannel, 'toWebPush', fn () => webpush()->message('Title', 'Hi')],
        ];
    }

    #[DataProvider('channels')]
    public function test_route_objects_go_to_the_registered_sender(string $manager, \Closure $channel, string $method, \Closure $message): void
    {
        Http::fake();
        $calls = [];

        $manager::sendUsing(Recipient::class, function ($sent, Recipient $route, $notifiable, $notification) use (&$calls) {
            $calls[] = [$route->id, $notification->id];

            return 'sent-1';
        });

        $result = $channel()->send(new RoutesTo(new Recipient(7)), $this->notification($method, $message, 'n-1'));

        $this->assertSame(['sent-1'], $result);
        $this->assertSame([[7, 'n-1']], $calls);
        Http::assertNothingSent();
    }

    #[DataProvider('channels')]
    public function test_a_sender_that_sends_nothing_gives_an_empty_array(string $manager, \Closure $channel, string $method, \Closure $message): void
    {
        $manager::sendUsing(Recipient::class, fn () => null);

        $this->assertSame([], $channel()->send(new RoutesTo(new Recipient(7)), $this->notification($method, $message)));
    }

    #[DataProvider('channels')]
    public function test_no_route_gives_an_empty_array(string $manager, \Closure $channel, string $method, \Closure $message): void
    {
        Http::fake();

        $this->assertSame([], $channel()->send(new RoutesTo(null), $this->notification($method, $message)));
        Http::assertNothingSent();
    }

    public function test_senders_are_kept_per_channel(): void
    {
        Telegram::sendUsing(Recipient::class, fn () => 'telegram');

        $this->assertNotNull(Telegram::senderFor(new Recipient(1)));
        $this->assertNull(Sms::senderFor(new Recipient(1)));
    }

    public function test_a_sender_can_be_an_invokable_class_from_the_container(): void
    {
        Telegram::sendUsing(Recipient::class, InvokableSender::class);

        $result = (new TelegramChannel(telegram()))->send(new RoutesTo(new Recipient(3)), $this->notification('toTelegram', fn () => telegram()->text('Hi')));

        $this->assertSame(['invoked-3'], $result);
    }

    protected function notification(string $method, \Closure $message, ?string $id = null): Notification
    {
        $notification = new class($message) extends Notification
        {
            public function __construct(public \Closure $message) {}

            public function toTelegram($notifiable)
            {
                return ($this->message)();
            }

            public function toSms($notifiable)
            {
                return ($this->message)();
            }

            public function toWhatsApp($notifiable)
            {
                return ($this->message)();
            }

            public function toFcm($notifiable, ?string $platform = null)
            {
                return ($this->message)();
            }

            public function toWebPush($notifiable, $notification)
            {
                return ($this->message)();
            }
        };

        $notification->id = $id;

        return $notification;
    }
}

class Recipient
{
    public function __construct(public int $id) {}
}

class RoutesTo
{
    public function __construct(public mixed $route) {}

    public function routeNotificationFor($driver, $notification = null)
    {
        return $this->route;
    }
}

class InvokableSender
{
    public function __invoke($message, Recipient $route): string
    {
        return 'invoked-' . $route->id;
    }
}
