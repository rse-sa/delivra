<?php

namespace RSE\Delivra\Tests\Unit\Sms;

use RSE\Delivra\Sms\SmsResponse;
use RSE\Delivra\Tests\TestCase;

class SmsResponseTest extends TestCase
{
    public function test_creates_response(): void
    {
        $response = new SmsResponse('unifonic', '9665000000', 'Test message');

        $this->assertEquals('unifonic', $response->getDriver());
        $this->assertEquals('9665000000', $response->getRecipient());
        $this->assertEquals('Test message', $response->getBody());
    }

    public function test_sets_successful(): void
    {
        $response = new SmsResponse('unifonic', '9665000000', 'Test message');
        $response->setSuccessful();

        $this->assertTrue($response->successful());
        $this->assertFalse($response->failed());
    }

    public function test_sets_failed(): void
    {
        $response = new SmsResponse('unifonic', '9665000000', 'Test message');
        $response->setFailed();

        $this->assertFalse($response->successful());
        $this->assertTrue($response->failed());
    }

    public function test_sets_message_id(): void
    {
        $response = new SmsResponse('unifonic', '9665000000', 'Test message');
        $response->setMessageId('msg-123');

        $this->assertEquals('msg-123', $response->getMessageId());
    }

    public function test_sets_credits(): void
    {
        $response = new SmsResponse('unifonic', '9665000000', 'Test message');
        $response->setCredits(1.5);

        $this->assertEquals(1.5, $response->getCredits());
    }

    public function test_sets_response(): void
    {
        $response = new SmsResponse('unifonic', '9665000000', 'Test message');
        $response->setResponse('{"success": true}');

        $this->assertEquals('{"success": true}', $response->getResponse());
    }

    public function test_gets_response_array(): void
    {
        $response = new SmsResponse('unifonic', '9665000000', 'Test message');
        $response->setResponse('{"success": true}');

        $this->assertEquals(['success' => true], $response->getResponseArray());
    }

    public function test_to_array(): void
    {
        $response = new SmsResponse('unifonic', '9665000000', 'Test message');
        $response->setSuccessful();
        $response->setResponse('{"success": true}');

        $array = $response->toArray();

        $this->assertEquals('unifonic', $array['driver']);
        $this->assertEquals('9665000000', $array['recipient']);
        $this->assertEquals('Test message', $array['body']);
        $this->assertEquals('success', $array['status']);
    }
}
