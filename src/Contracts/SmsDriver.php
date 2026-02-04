<?php

namespace RSE\Delivra\Contracts;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RSE\Delivra\Sms\SmsBatchResponse;
use RSE\Delivra\Sms\SmsBuilder;
use RSE\Delivra\Sms\SmsResponse;
use RSE\Delivra\Traits\SanitizesNumbers;

abstract class SmsDriver implements SmsDriverInterface
{
    protected string $driver;

    protected array $settings = [];

    protected SmsBuilder $builder;

    protected array $errors = [];

    use SanitizesNumbers;

    public function __construct(string $driver, array $settings)
    {
        $this->driver = $driver;
        $this->settings = $settings;
        $this->builder = new SmsBuilder();
        $this->boot();
    }

    public function getBuilder(): SmsBuilder
    {
        return $this->builder;
    }

    public function setBuilder(SmsBuilder $smsBuilder): self
    {
        $this->builder = $smsBuilder;

        return $this;
    }

    public function shouldCalculateCredits(): bool
    {
        return $this->builder->shouldIncludeCredits() || config('delivra.sms.credits', false);
    }

    protected function report($message)
    {
        $message = is_string($message) ? '[SmsError-'.$this->driver.'] '.$message : $message;
        report($message);

        $this->errors[] = $message;

        return null;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function to($numbers): self
    {
        $recipients = is_array($numbers) ? $numbers : [$numbers];

        $recipients = array_map(static function ($item) {
            return trim($item);
        }, array_merge($this->builder->getRecipients(), $recipients));

        $this->builder->to(array_values(array_filter($recipients)));

        if (count($this->builder->getRecipients()) < 1) {
            throw new Exception('Message recipients cannot be empty.');
        }

        return $this;
    }

    public function message(string $message): self
    {
        $message = trim($message);

        if ($message === '') {
            throw new Exception('Message text cannot be empty.');
        }

        $this->builder->body($message);

        return $this;
    }

    protected function boot(): void
    {
        //
    }

    public function send(): SmsResponse|SmsBatchResponse
    {
        $recipients = $this->builder->getRecipients();

        // Single recipient - throw immediately on failure
        if (count($recipients) === 1) {
            return $this->sendSingle($this->sanitizeNumber($recipients[0]));
        }

        // Multiple recipients - use batch logic
        // Strategy A: Driver supports bulk sending
        if (method_exists($this, 'sendMultiple')) {
            $batch = $this->sendMultiple($recipients);

            return $batch;
        }

        // Strategy B: Driver requires individual sending (loop with try/catch)
        $batch = new SmsBatchResponse();
        foreach ($recipients as $recipient) {
            try {
                $number = $this->sanitizeNumber($recipient);
                $formatted = $this->formatNumber($number);
                $response = $this->sendSingle($formatted);
                $batch->addSuccess($recipient, $response);
            } catch (\Throwable $e) {
                $batch->addFailure($recipient, $e->getMessage());
            }
        }

        return $batch;
    }

    abstract public function sendSingle(string $recipient): SmsResponse;

    public function sendMultiple(array $recipients): SmsBatchResponse
    {
        // Default implementation loops through sendSingle
        // Override this if provider has bulk API
        $batch = new SmsBatchResponse();

        foreach ($recipients as $recipient) {
            try {
                $number = $this->sanitizeNumber($recipient);
                $formatted = $this->formatNumber($number);
                $response = $this->sendSingle($formatted);
                $batch->addSuccess($recipient, $response);
            } catch (\Throwable $e) {
                $batch->addFailure($recipient, $e->getMessage());
            }
        }

        return $batch;
    }

    abstract public function getBalance(): ?int;

    abstract public function formatNumber(string $number): string;

    public function credits(array $numbers, string $message): ?float
    {
        return null;
    }

    public function estimateCredits($numbers, string $message): ?float
    {
        $numbers = Arr::wrap($numbers);

        if (method_exists($this, 'credits') && !is_null($credits = $this->credits($numbers, $message))) {
            return $credits;
        }

        return $this->autoEstimateCredits($numbers, $message);
    }

    public function autoEstimateCredits($numbers, string $message): ?float
    {
        $numbers = Arr::wrap($numbers);

        $credits = 0;

        foreach ($numbers as $number) {
            $mobile = ltrim($number, '+0');

            if (Str::startsWith($mobile, '966')) {
                $isArabic = self::textHasArabic($message);

                if ($isArabic) {
                    $credits += ceil(mb_strlen($message) / 70);
                } else {
                    $credits += ceil(mb_strlen($message) / 140);
                }
            } else {
                return null;
            }
        }

        return $credits;
    }

    protected static function textHasArabic($text): bool
    {
        $ar = ['ذ', 'ض', 'ص', 'ث', 'ق', 'ف', 'غ', 'ع', 'ه', 'خ', 'ح', 'ج', 'د', 'ش', 'س', 'ي', 'ب', 'ل', 'ا', 'ت', 'ن', 'م', 'ك', 'ط', 'ئ', 'ء', 'ؤ', 'ر', 'لا', 'ى', 'ة', 'و', 'ز', 'ظ'];

        foreach ($ar as $char) {
            if (str_contains($text, $char)) {
                return true;
            }
        }

        return false;
    }

    protected function http()
    {
        return Http::timeout(config('delivra.http.timeout', 10))
            ->connectTimeout(config('delivra.http.connect_timeout', 5))
            ->retry(config('delivra.http.retries', 3), config('delivra.http.retry_delay', 100));
    }
}
