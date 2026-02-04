<?php

namespace RSE\Delivra\Tests\Unit\Sms;

use RSE\Delivra\Sms\SmsBuilder;
use RSE\Delivra\Tests\TestCase;

class SmsBuilderTest extends TestCase
{
    public function test_sets_recipient(): void
    {
        $builder = new SmsBuilder;
        $builder->to('9665000000');

        $this->assertEquals(['9665000000'], $builder->getRecipients());
    }

    public function test_sets_multiple_recipients(): void
    {
        $builder = new SmsBuilder;
        $builder->to(['9665000000', '9666000000']);

        $this->assertEquals(['9665000000', '9666000000'], $builder->getRecipients());
    }

    public function test_sets_message_body(): void
    {
        $builder = new SmsBuilder;
        $builder->body('Test message');

        $this->assertEquals('Test message', $builder->getBody());
    }

    public function test_sets_driver(): void
    {
        $builder = new SmsBuilder;
        $builder->via('unifonic');

        $this->assertEquals('unifonic', $builder->getDriver());
    }

    public function test_enables_credits(): void
    {
        $builder = new SmsBuilder;
        $builder->withCredits(true);

        $this->assertTrue($builder->shouldIncludeCredits());
    }

    public function test_disables_credits(): void
    {
        $builder = new SmsBuilder;
        $builder->withCredits(false);

        $this->assertFalse($builder->shouldIncludeCredits());
    }

    public function test_without_credits(): void
    {
        $builder = new SmsBuilder;
        $builder->withoutCredits();

        $this->assertFalse($builder->shouldIncludeCredits());
    }
}
