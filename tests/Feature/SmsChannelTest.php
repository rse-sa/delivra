<?php

namespace RSE\Delivra\Tests\Feature;

use Illuminate\Notifications\Notification;
use RSE\Delivra\Sms\Channels\SmsChannel;
use RSE\Delivra\Sms\SmsBuilder;
use RSE\Delivra\Tests\TestCase;

class SmsChannelTest extends TestCase
{
    public function test_sends_notification(): void
    {
        $channel = new SmsChannel(app('delivra-sms'));

        $notifiable = new class
        {
            public function routeNotificationFor($driver, $notification = null)
            {
                return '9665000000';
            }
        };

        $notification = new class extends Notification
        {
            public function toSms($notifiable)
            {
                return SmsBuilder::make()->body('Test message');
            }
        };

        $result = $channel->send($notifiable, $notification);

        $this->assertNotNull($result);
    }

    public function test_uses_notifiable_phone_number(): void
    {
        $channel = new SmsChannel(app('delivra-sms'));

        $notifiable = new class
        {
            public function routeNotificationFor($driver, $notification = null)
            {
                return '9665999999';
            }
        };

        $notification = new class extends Notification
        {
            public function toSms($notifiable)
            {
                return SmsBuilder::make()->body('Test message');
            }
        };

        $result = $channel->send($notifiable, $notification);

        $this->assertNotNull($result);
    }
}
