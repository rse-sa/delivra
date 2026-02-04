<?php

namespace RSE\Delivra\Tests\Unit\Sms\Drivers;

use RSE\Delivra\Sms\Drivers\NullDriver;
use RSE\Delivra\Tests\TestCase;

class NullDriverTest extends TestCase
{
    public function test_sends_message(): void
    {
        $driver = new NullDriver('null', []);
        $driver->to('9665000000');
        $driver->message('Test message');

        $response = $driver->sendSingle('9665000000');

        $this->assertTrue($response->successful());
    }

    public function test_gets_balance(): void
    {
        $driver = new NullDriver('null', []);

        $this->assertEquals(0, $driver->getBalance());
    }

    public function test_formats_number(): void
    {
        $driver = new NullDriver('null', []);

        $this->assertEquals('9665000000', $driver->formatNumber('9665000000'));
    }
}
