<?php

namespace RSE\Delivra\Tests\Unit\Sms;

use RSE\Delivra\Sms\SmsResponse;
use RSE\Delivra\Sms\SmsResponseCollection;
use RSE\Delivra\Tests\TestCase;

class SmsResponseCollectionTest extends TestCase
{
    public function test_counts_successful(): void
    {
        $collection = new SmsResponseCollection();

        $response1 = new SmsResponse('unifonic', '9665000000', 'Test');
        $response1->setSuccessful();
        $collection->append('1', $response1);

        $response2 = new SmsResponse('unifonic', '9666000000', 'Test');
        $response2->setFailed();
        $collection->append('2', $response2);

        $this->assertEquals(1, $collection->successCount());
    }

    public function test_counts_failed(): void
    {
        $collection = new SmsResponseCollection();

        $response1 = new SmsResponse('unifonic', '9665000000', 'Test');
        $response1->setSuccessful();
        $collection->append('1', $response1);

        $response2 = new SmsResponse('unifonic', '9666000000', 'Test');
        $response2->setFailed();
        $collection->append('2', $response2);

        $this->assertEquals(1, $collection->failureCount());
    }

    public function test_checks_success(): void
    {
        $collection = new SmsResponseCollection();

        $response = new SmsResponse('unifonic', '9665000000', 'Test');
        $response->setSuccessful();
        $collection->append('1', $response);

        $this->assertTrue($collection->success());
    }

    public function test_checks_failed(): void
    {
        $collection = new SmsResponseCollection();

        $response = new SmsResponse('unifonic', '9665000000', 'Test');
        $response->setFailed();
        $collection->append('1', $response);

        $this->assertTrue($collection->failed());
    }

    public function test_calculates_credits(): void
    {
        $collection = new SmsResponseCollection();

        $response1 = new SmsResponse('unifonic', '9665000000', 'Test');
        $response1->setSuccessful();
        $response1->setCredits(1.5);
        $collection->append('1', $response1);

        $response2 = new SmsResponse('unifonic', '9666000000', 'Test');
        $response2->setSuccessful();
        $response2->setCredits(0.5);
        $collection->append('2', $response2);

        $this->assertEquals(2.0, $collection->credits());
    }
}
