<?php

namespace RSE\Delivra\Telegram\Messages;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use RSE\Delivra\Exceptions\Telegram\ApiErrorException;

class PollMessage extends TelegramMessageAbstract
{
    protected ?string $question = null;

    protected array $options = [];

    protected string $type = 'regular';

    protected bool $anonymous = false;

    protected bool $multipleAnswers = false;

    protected ?int $correctOptionId = null;

    protected ?string $explanation = null;

    protected ?int $openPeriod = null;

    protected ?CarbonInterface $closeDate = null;

    public function question(string $question): self
    {
        $this->question = $question;

        return $this;
    }

    public function setQuestion(string $question): self
    {
        return $this->question($question);
    }

    public function options(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function setOptions(array $options): self
    {
        return $this->options($options);
    }

    public function type(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function setType(string $type): self
    {
        return $this->type($type);
    }

    public function anonymous(): self
    {
        $this->anonymous = true;

        return $this;
    }

    public function allowMultipleAnswers(): self
    {
        $this->multipleAnswers = true;

        return $this;
    }

    public function correctOption(int $index): self
    {
        $this->correctOptionId = $index;

        return $this;
    }

    public function setCorrectOption(int $index): self
    {
        return $this->correctOption($index);
    }

    public function explanation(string $explanation): self
    {
        $this->explanation = $explanation;

        return $this;
    }

    public function setExplanation(string $explanation): self
    {
        return $this->explanation($explanation);
    }

    public function openPeriod(int $seconds): self
    {
        $this->openPeriod = $seconds;

        return $this;
    }

    public function setOpenPeriod(int $seconds): self
    {
        return $this->openPeriod($seconds);
    }

    public function closeDate(CarbonInterface $date): self
    {
        $this->closeDate = $date;

        return $this;
    }

    public function setCloseDate(CarbonInterface $date): self
    {
        return $this->closeDate($date);
    }

    public function getReceivers(): array
    {
        return $this->receivers;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function toArray(): array
    {
        return [
            'question'                => $this->question,
            'options'                 => json_encode($this->options),
            'is_anonymous'            => $this->anonymous,
            'type'                    => $this->type,
            'allows_multiple_answers' => $this->multipleAnswers,
            'correct_option_id'       => $this->correctOptionId,
            'explanation'             => $this->explanation,
            'explanation_parse_mode'  => $this->explanation ? $this->parseMode : null,
            'open_period'             => $this->openPeriod,
            'close_date'              => $this->closeDate ? $this->closeDate->unix() : null,
        ];
    }

    public function send(): \RSE\Delivra\Telegram\TelegramResponse
    {
        $this->validateTokenAndReceivers();

        $response = Http::timeout(config('delivra.http.timeout', 10))
            ->connectTimeout(config('delivra.http.connect_timeout', 5))
            ->retry(config('delivra.http.retries', 3), config('delivra.http.retry_delay', 100))
            ->acceptJson()
            ->post("https://api.telegram.org/bot{$this->token}/sendPoll", $this->prepareBasicParameters($this->receivers[0], [
                'question'                => $this->question,
                'options'                 => json_encode($this->options),
                'is_anonymous'            => $this->anonymous,
                'type'                    => $this->type,
                'allows_multiple_answers' => $this->multipleAnswers,
                'correct_option_id'       => $this->correctOptionId,
                'explanation'             => $this->explanation,
                'explanation_parse_mode'  => $this->explanation ? $this->parseMode : null,
                'open_period'             => $this->openPeriod,
                'close_date'              => $this->closeDate ? $this->closeDate->unix() : null,
            ]));

        $result = $response->json();

        if (! ($result['ok'] ?? false)) {
            throw new ApiErrorException(
                $result['error_code'] ?? 'UNKNOWN',
                $result['description'] ?? 'Unknown error',
                $result
            );
        }

        return new \RSE\Delivra\Telegram\TelegramResponse($this->receivers[0], $result);
    }
}
