<?php

namespace RSE\Delivra\Contracts;

use RSE\Delivra\Sms\SmsBatchResponse;
use RSE\Delivra\Sms\SmsBuilder;
use RSE\Delivra\Sms\SmsResponse;

interface SmsDriverInterface
{
    /**
     * Set the message builder.
     */
    public function setBuilder(SmsBuilder $smsBuilder): self;

    /**
     * Get the current message builder.
     */
    public function getBuilder(): SmsBuilder;

    /**
     * Send SMS to recipients.
     */
    public function send(): SmsResponse|SmsBatchResponse;

    /**
     * Send SMS to a single recipient.
     */
    public function sendSingle(string $recipient): SmsResponse;

    /**
     * Send SMS to multiple recipients.
     */
    public function sendMultiple(array $recipients): SmsBatchResponse;

    /**
     * Get current account balance.
     */
    public function getBalance(): ?int;

    /**
     * Format phone number according to driver requirements.
     */
    public function formatNumber(string $number): string;
}
