<?php

namespace RSE\Delivra\Tests\Unit\Fcm;

use PHPUnit\Framework\TestCase;
use RSE\Delivra\Exceptions\Fcm\InvalidMessageException;
use RSE\Delivra\Fcm\Messages\AndroidConfig;
use RSE\Delivra\Fcm\Messages\ApnsConfig;
use RSE\Delivra\Fcm\Messages\FcmMessage;
use RSE\Delivra\Fcm\Messages\WebpushConfig;
use RSE\Delivra\Fcm\Target;

enum FcmTestType: string
{
    case Order = 'order';
}

class FcmMessageTest extends TestCase
{
    public function test_builds_notification_and_string_data(): void
    {
        $payload = FcmMessage::make('Order ready', 'Order #15')
            ->image('https://x.test/a.png')
            ->data([
                'id'     => 15,
                'paid'   => false,
                'type'   => FcmTestType::Order,
                'meta'   => ['a' => 1],
                'empty'  => null,
                'amount' => 12.5,
            ])
            ->toPayload();

        $this->assertSame(['title' => 'Order ready', 'body' => 'Order #15', 'image' => 'https://x.test/a.png'], $payload['notification']);
        $this->assertSame(['id' => '15', 'paid' => 'false', 'type' => 'order', 'meta' => '{"a":1}', 'amount' => '12.5'], $payload['data']);
        $this->assertArrayNotHasKey('android', $payload);
    }

    public function test_reserved_data_keys_throw(): void
    {
        $this->expectException(InvalidMessageException::class);

        FcmMessage::make()->data(['google.sent_time' => 'x']);
    }

    public function test_platform_builders(): void
    {
        $payload = FcmMessage::make('Hi')
            ->android(fn (AndroidConfig $android) => $android->channel('orders')->sound()->priority('HIGH'))
            ->apns(fn (ApnsConfig $apns) => $apns->sound()->badge(0)->category('order'))
            ->webpush(fn (WebpushConfig $web) => $web->link('https://app.test/orders/15')->icon('/i.png')->renotify(false))
            ->toPayload();

        $this->assertEquals(['priority' => 'high', 'notification' => ['channel_id' => 'orders', 'sound' => 'default']], $payload['android']);
        $this->assertSame(['aps' => ['sound' => 'default', 'badge' => 0, 'category' => 'order']], $payload['apns']['payload']);
        $this->assertSame('https://app.test/orders/15', $payload['webpush']['fcm_options']['link']);
        $this->assertFalse($payload['webpush']['notification']['renotify']);
    }

    public function test_ttl_and_collapse_key_go_to_each_platform_and_builder_wins(): void
    {
        $before  = time();
        $payload = FcmMessage::make('Hi')
            ->ttl(3600)
            ->collapseKey('order-15')
            ->android(fn (AndroidConfig $android) => $android->ttl(60))
            ->toPayload();

        $this->assertSame('60s', $payload['android']['ttl']);
        $this->assertSame('order-15', $payload['android']['collapse_key']);
        $this->assertSame('order-15', $payload['apns']['headers']['apns-collapse-id']);
        $this->assertGreaterThanOrEqual($before + 3600, (int) $payload['apns']['headers']['apns-expiration']);
        $this->assertSame('3600', $payload['webpush']['headers']['TTL']);
    }

    public function test_silent_message_is_data_only_background_push(): void
    {
        $payload = FcmMessage::silent(['notify_type' => 'settings_changed'])->toPayload();

        $this->assertArrayNotHasKey('notification', $payload);
        $this->assertSame(['notify_type' => 'settings_changed'], $payload['data']);
        $this->assertSame('normal', $payload['android']['priority']);
        $this->assertSame(['apns-push-type' => 'background', 'apns-priority' => '5'], $payload['apns']['headers']);
        $this->assertSame(1, $payload['apns']['payload']['aps']['content-available']);
    }

    public function test_raw_fields_merge_last(): void
    {
        $payload = FcmMessage::make('Hi')
            ->android(['direct_boot_ok' => true])
            ->with(['fcm_options' => ['analytics_label' => 'promo']])
            ->toPayload();

        $this->assertTrue($payload['android']['direct_boot_ok']);
        $this->assertSame('promo', $payload['fcm_options']['analytics_label']);
    }

    public function test_targets(): void
    {
        $this->assertSame(['topic' => 'news'], Target::topic('/topics/news')->toPayload());
        $this->assertSame(['a', 'b'], Target::tokens(['a', '', 'b', 'a']));
        $this->assertSame('abcdef...wxyz', Target::token('abcdefghijklmnopqrstuvwxyz')->masked());
        $this->assertSame('topic:news', Target::topic('news')->masked());

        $this->expectException(InvalidMessageException::class);
        Target::topic('bad topic');
    }
}
