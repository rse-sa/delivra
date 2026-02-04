<?php

namespace RSE\Delivra\Tests\Unit\Telegram;

use RSE\Delivra\Telegram\TelegramBatchResponse;
use RSE\Delivra\Telegram\TelegramResponse;
use RSE\Delivra\Tests\TestCase;

class TelegramBatchResponseTest extends TestCase
{
    public function test_adds_success(): void
    {
        $batch    = new TelegramBatchResponse;
        $response = new TelegramResponse('chat-1', ['ok' => true, 'result' => ['message_id' => 1]]);

        $batch->addSuccess('chat-1', $response);

        $this->assertCount(1, $batch->successful());
        $this->assertEquals(1, $batch->successfulCount());
    }

    public function test_adds_failure(): void
    {
        $batch = new TelegramBatchResponse;

        $batch->addFailure('chat-2', 'Invalid chat ID');

        $this->assertCount(1, $batch->failed());
        $this->assertEquals(1, $batch->failedCount());
    }

    public function test_checks_has_failures(): void
    {
        $batch = new TelegramBatchResponse;

        $batch->addFailure('chat-2', 'Invalid chat ID');

        $this->assertTrue($batch->hasFailures());
    }

    public function test_calculates_total_count(): void
    {
        $batch    = new TelegramBatchResponse;
        $response = new TelegramResponse('chat-1', ['ok' => true, 'result' => ['message_id' => 1]]);

        $batch->addSuccess('chat-1', $response);
        $batch->addFailure('chat-2', 'Invalid chat ID');

        $this->assertEquals(2, $batch->totalCount());
        $this->assertCount(2, $batch);
    }
}
