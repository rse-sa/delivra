<?php

namespace RSE\Delivra\Telegram;

use Illuminate\Support\Facades\Http;
use RSE\Delivra\Events\DelivraMessageFailed;
use RSE\Delivra\Events\DelivraMessageSending;
use RSE\Delivra\Events\DelivraMessageSent;
use RSE\Delivra\Exceptions\Telegram\ApiErrorException;
use RSE\Delivra\Exceptions\Telegram\TelegramBatchException;
use RSE\Delivra\Exceptions\Telegram\TelegramMessageFailedException;
use RSE\Delivra\Telegram\Messages\ContactMessage;
use RSE\Delivra\Telegram\Messages\DocumentMessage;
use RSE\Delivra\Telegram\Messages\LocationMessage;
use RSE\Delivra\Telegram\Messages\PhotoMessage;
use RSE\Delivra\Telegram\Messages\PollMessage;
use RSE\Delivra\Telegram\Messages\TextMessage;
use RSE\Delivra\Telegram\Messages\VideoMessage;

class Telegram
{
    protected array $config;

    protected ?string $defaultToken;

    protected ?string $defaultChatId;

    protected string $parseMode;

    protected ?array $pendingReceivers = null;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->defaultToken = $config['default_token'] ?? null;
        $this->defaultChatId = $config['default_chat_id'] ?? null;
        $this->parseMode = $config['parse_mode'] ?? 'html';
    }

    public function text(string $message): TextMessage
    {
        return TextMessage::make()
            ->message($message)
            ->token($this->defaultToken)
            ->setParseMode($this->parseMode);
    }

    public function photo(string $path): PhotoMessage
    {
        return PhotoMessage::make()
            ->photo($path)
            ->token($this->defaultToken)
            ->setParseMode($this->parseMode);
    }

    public function video(string $path): VideoMessage
    {
        return VideoMessage::make()
            ->video($path)
            ->token($this->defaultToken)
            ->setParseMode($this->parseMode);
    }

    public function document(string $path): DocumentMessage
    {
        return DocumentMessage::make()
            ->document($path)
            ->token($this->defaultToken)
            ->setParseMode($this->parseMode);
    }

    public function poll(string $question, array $options): PollMessage
    {
        return PollMessage::make()
            ->question($question)
            ->options($options)
            ->token($this->defaultToken);
    }

    public function location(float $lat, float $lng): LocationMessage
    {
        return LocationMessage::make()
            ->latitude($lat)
            ->longitude($lng)
            ->token($this->defaultToken);
    }

    public function contact(string $phone, string $name): ContactMessage
    {
        return ContactMessage::make()
            ->phone($phone)
            ->firstName($name)
            ->token($this->defaultToken);
    }

    public function to(string|array $chatIds): self
    {
        $this->pendingReceivers = is_array($chatIds) ? $chatIds : [$chatIds];

        return $this;
    }

    public function send($message = null): TelegramResponse|TelegramBatchResponse
    {
        if ($message === null) {
            throw new TelegramMessageFailedException(null, null, 'No message provided to send()');
        }

        // Apply pending receivers if set
        if ($this->pendingReceivers !== null) {
            $message->to($this->pendingReceivers);
            $this->pendingReceivers = null;
        }

        // Apply default token if message has no token
        if (method_exists($message, 'getToken') && empty($message->getToken()) && $this->defaultToken) {
            $message->token($this->defaultToken);
        }

        $receivers = $this->getMessageReceivers($message);

        // Single recipient - send directly
        if (count($receivers) === 1) {
            return $this->sendMessageToSingle($message, $receivers[0]);
        }

        // Multiple recipients - batch with throttling
        return $this->sendMessageToMultiple($message, $receivers);
    }

    protected function getMessageReceivers($message): array
    {
        if (method_exists($message, 'getReceivers')) {
            return $message->getReceivers();
        }

        if ($this->defaultChatId) {
            return [$this->defaultChatId];
        }

        throw new TelegramMessageFailedException(null, get_class($message), 'No receivers specified');
    }

    protected function sendMessageToSingle($message, string $chatId): TelegramResponse
    {
        $endpoint = $this->getEndpointForMessage($message);
        $payload = $message->toArray();
        $payload['chat_id'] = $chatId;

        $token = $this->getTokenForMessage($message);
        $body = $this->getMessageBody($message);

        event(new DelivraMessageSending('telegram', $chatId, $body, $payload, null, $token, null, null, $message));

        try {
            $response = Http::timeout(config('delivra.http.timeout', 10))
                ->connectTimeout(config('delivra.http.connect_timeout', 5))
                ->retry(config('delivra.http.retries', 3), config('delivra.http.retry_delay', 100))
                ->acceptJson()
                ->post("https://api.telegram.org/bot{$token}/{$endpoint}", $payload);

            $result = $response->json();

            if (!($result['ok'] ?? false)) {
                throw new ApiErrorException(
                    $result['error_code'] ?? 'UNKNOWN',
                    $result['description'] ?? 'Unknown error',
                    $result
                );
            }

            $responseObj = new TelegramResponse($chatId, $result);
            event(new DelivraMessageSent('telegram', $chatId, $body, $responseObj->getMessageId(), $responseObj, null, null, $message));

            return $responseObj;
        } catch (\Throwable $e) {
            event(new DelivraMessageFailed('telegram', $chatId, $body, $e->getMessage(), $e, null, null, $message));

            if (!$e instanceof ApiErrorException) {
                report($e);
            }

            throw new TelegramMessageFailedException($chatId, get_class($message), $e->getMessage());
        }
    }

    protected function sendMessageToMultiple($message, array $chatIds): TelegramBatchResponse
    {
        $batch = new TelegramBatchResponse();
        $endpoint = $this->getEndpointForMessage($message);
        $token = $this->getTokenForMessage($message);
        $body = $this->getMessageBody($message);

        foreach ($chatIds as $chatId) {
            $payload = $message->toArray();
            $payload['chat_id'] = $chatId;

            event(new DelivraMessageSending('telegram', $chatId, $body, $payload, null, $token, null, null, $message));

            try {
                $response = Http::timeout(config('delivra.http.timeout', 10))
                    ->connectTimeout(config('delivra.http.connect_timeout', 5))
                    ->retry(config('delivra.http.retries', 3), config('delivra.http.retry_delay', 100))
                    ->acceptJson()
                    ->post("https://api.telegram.org/bot{$token}/{$endpoint}", $payload);

                $result = $response->json();

                if (!($result['ok'] ?? false)) {
                    $error = $result['description'] ?? 'Unknown error';
                    event(new DelivraMessageFailed('telegram', $chatId, $body, $error, null, null, null, $message));
                    $batch->addFailure($chatId, $error);
                } else {
                    $responseObj = new TelegramResponse($chatId, $result);
                    event(new DelivraMessageSent('telegram', $chatId, $body, $responseObj->getMessageId(), $responseObj, null, null, $message));
                    $batch->addSuccess($chatId, $responseObj);
                }

                // Throttle to avoid 429 errors (Telegram limit: ~30 messages/second)
                usleep(35000); // ~35ms = ~28 messages/second (safe margin)
            } catch (\Throwable $e) {
                event(new DelivraMessageFailed('telegram', $chatId, $body, $e->getMessage(), $e, null, null, $message));
                $batch->addFailure($chatId, $e->getMessage());
            }
        }

        if ($batch->hasFailures()) {
            throw new TelegramBatchException($batch);
        }

        return $batch;
    }

    public function getEndpointForMessage($message): string
    {
        $class = get_class($message);

        return match (true) {
            $class === TextMessage::class => 'sendMessage',
            $class === PhotoMessage::class => 'sendPhoto',
            $class === VideoMessage::class => 'sendVideo',
            $class === DocumentMessage::class => 'sendDocument',
            $class === PollMessage::class => 'sendPoll',
            $class === LocationMessage::class => 'sendLocation',
            $class === ContactMessage::class => 'sendContact',
            default => throw new TelegramMessageFailedException(null, $class, 'Unknown message type'),
        };
    }

    public function getTokenForMessage($message): string
    {
        if (method_exists($message, 'getToken') && $message->getToken()) {
            return $message->getToken();
        }

        if ($this->defaultToken) {
            return $this->defaultToken;
        }

        throw new TelegramMessageFailedException(null, get_class($message), 'No token specified');
    }

    private function getMessageBody($message): string
    {
        if (method_exists($message, 'getCaption') && $message->getCaption()) {
            return $message->getCaption();
        }

        if (method_exists($message, 'getMessage') && $message->getMessage()) {
            return $message->getMessage();
        }

        if (method_exists($message, 'getQuestion') && $message->getQuestion()) {
            return $message->getQuestion();
        }

        return get_class($message);
    }
}
