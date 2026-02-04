<?php

namespace RSE\Delivra\Tests\Unit\Sms;

use RSE\Delivra\Sms\SmsBatchResponse;
use RSE\Delivra\Sms\SmsResponse;
use RSE\Delivra\Tests\TestCase;

class SmsBatchResponseTest extends TestCase
{
    public function test_adds_success(): void
    {
        $batch    = new SmsBatchResponse;
        $response = new SmsResponse('unifonic', '9665000000', 'Test');
        $response->setSuccessful();

        $batch->addSuccess('9665000000', $response);

        $this->assertCount(1, $batch->successful());
        $this->assertEquals(1, $batch->successfulCount());
    }

    public function test_adds_failure(): void
    {
        $batch = new SmsBatchResponse;

        $batch->addFailure('9665000000', 'Invalid number');

        $this->assertCount(1, $batch->failed());
        $this->assertEquals(1, $batch->failedCount());
    }

    public function test_checks_has_failures(): void
    {
        $batch = new SmsBatchResponse;

        $batch->addFailure('9665000000', 'Invalid number');

        $this->assertTrue($batch->hasFailures());
    }

    public function test_calculates_total_count(): void
    {
        $batch    = new SmsBatchResponse;
        $response = new SmsResponse('unifonic', '9665000000', 'Test');
        $response->setSuccessful();

        $batch->addSuccess('9665000000', $response);
        $batch->addFailure('9666000000', 'Invalid number');

        $this->assertEquals(2, $batch->totalCount());
        $this->assertCount(2, $batch);
    }
}
