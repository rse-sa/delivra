<?php

namespace RSE\Delivra\Tests\Feature;

use RSE\Delivra\Tests\TestCase;

class ConfigTest extends TestCase
{
    public function test_sms_config_is_loaded(): void
    {
        $this->assertEquals('null', config('delivra.sms.default'));
        $this->assertFalse(config('delivra.sms.credits'));
    }

    public function test_sms_drivers_config_is_loaded(): void
    {
        $drivers = config('delivra.sms.drivers');

        $this->assertIsArray($drivers);
        $this->assertArrayHasKey('null', $drivers);
        $this->assertArrayHasKey('unifonic', $drivers);
    }

    public function test_telegram_config_is_loaded(): void
    {
        $this->assertEquals('test-token', config('delivra.telegram.default_token'));
        $this->assertEquals('test-chat-id', config('delivra.telegram.default_chat_id'));
        $this->assertEquals('html', config('delivra.telegram.parse_mode'));
    }

    public function test_http_config_is_loaded(): void
    {
        $this->assertEquals(10, config('delivra.http.timeout'));
        $this->assertEquals(5, config('delivra.http.connect_timeout'));
        $this->assertEquals(3, config('delivra.http.retries'));
        $this->assertEquals(100, config('delivra.http.retry_delay'));
    }
}
