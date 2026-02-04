<?php

namespace RSE\Delivra\Tests\Feature;

use RSE\Delivra\Sms\Sms;
use RSE\Delivra\Telegram\Telegram;
use RSE\Delivra\Tests\TestCase;

class HelpersTest extends TestCase
{
    public function test_sms_helper_returns_manager(): void
    {
        $result = sms();

        $this->assertInstanceOf(Sms::class, $result);
    }

    public function test_telegram_helper_returns_manager(): void
    {
        $result = telegram();

        $this->assertInstanceOf(Telegram::class, $result);
    }

    public function test_sms_helper_fluent_interface(): void
    {
        $result = sms()->to('9665000000')->body('Test');

        $this->assertInstanceOf(Sms::class, $result);
    }

    public function test_telegram_helper_text_message(): void
    {
        $result = telegram()->text('Hello');

        $this->assertInstanceOf(\RSE\Delivra\Telegram\Messages\TextMessage::class, $result);
    }

    public function test_telegram_helper_photo_message(): void
    {
        $result = telegram()->photo('/path/to/photo.jpg');

        $this->assertInstanceOf(\RSE\Delivra\Telegram\Messages\PhotoMessage::class, $result);
    }
}
