<?php

namespace RSE\Delivra\Telegram;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RSE\Delivra\Events\DelivraMessageFailed;
use RSE\Delivra\Events\DelivraMessageSending;
use RSE\Delivra\Events\DelivraMessageSent;
use RSE\Delivra\Exceptions\Telegram\ApiErrorException;
use RSE\Delivra\Exceptions\Telegram\BotBlockedException;
use RSE\Delivra\Exceptions\Telegram\InvalidTokenException;
use RSE\Delivra\Exceptions\Telegram\RateLimitedException;
use RSE\Delivra\Exceptions\Telegram\TelegramBatchException;
use RSE\Delivra\Exceptions\Telegram\TelegramMessageFailedException;
use RSE\Delivra\Telegram\Messages\ContactMessage;
use RSE\Delivra\Telegram\Messages\DocumentMessage;
use RSE\Delivra\Telegram\Messages\LocationMessage;
use RSE\Delivra\Telegram\Messages\PhotoMessage;
use RSE\Delivra\Telegram\Messages\PollMessage;
use RSE\Delivra\Telegram\Messages\TelegramMessageAbstract;
use RSE\Delivra\Telegram\Messages\TextMessage;
use RSE\Delivra\Telegram\Messages\VideoMessage;
use RSE\Delivra\Telegram\Updates\Update;

class Telegram
{
    protected array $config;

    protected ?string $defaultToken;

    protected ?string $defaultChatId;

    protected string $parseMode;

    protected ?array $pendingReceivers = null;

    public function __construct(array $config)
    {
        $this->config        = $config;
        $this->defaultToken  = $config['default_token'] ?? null;
        $this->defaultChatId = $config['default_chat_id'] ?? null;
        $this->parseMode     = $config['parse_mode'] ?? 'html';
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

    /**
     * One receiver gives a TelegramResponse, several a TelegramBatchResponse.
     * Null when a DelivraMessageSending listener cancelled the only send.
     */
    public function send($message = null): TelegramResponse|TelegramBatchResponse|null
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
        if (method_exists($message, 'getReceivers') && $message->getReceivers() !== []) {
            return $message->getReceivers();
        }

        if ($this->defaultChatId) {
            return [$this->defaultChatId];
        }

        throw new TelegramMessageFailedException(null, get_class($message), 'No receivers specified');
    }

    protected function sendMessageToSingle($message, string $chatId): ?TelegramResponse
    {
        return $this->deliver($message, $chatId);
    }

    /**
     * One message to one chat: sending event, the call, then sent or failed event.
     * Null when a DelivraMessageSending listener returns false.
     *
     * @throws TelegramMessageFailedException (BotBlockedException, RateLimitedException)
     */
    public function deliver(TelegramMessageAbstract $message, string $chatId, mixed $notifiable = null, mixed $notification = null): ?TelegramResponse
    {
        if (empty($message->getToken()) && $this->defaultToken) {
            $message->token($this->defaultToken);
        }

        $endpoint           = $this->getEndpointForMessage($message);
        $payload            = $message->toArray();
        $payload['chat_id'] = $chatId;

        $token = $this->getTokenForMessage($message);
        $body  = $this->getMessageBody($message);

        // halt mode: Laravel never puts a listener's false into the normal results
        if (event(new DelivraMessageSending('telegram', $chatId, $body, $payload, null, $token, $notifiable, $notification, $message), [], true) === false) {
            return null;
        }

        try {
            $result = $this->request($endpoint, $payload, $token, attachments: $message->attachments());

            $responseObj = new TelegramResponse($chatId, $result);
            event(new DelivraMessageSent('telegram', $chatId, $body, $responseObj->getMessageId(), $responseObj, $notifiable, $notification, $message));

            return $responseObj;
        } catch (\Throwable $e) {
            event(new DelivraMessageFailed('telegram', $chatId, $body, $e->getMessage(), $e, $notifiable, $notification, $message));

            if (! $e instanceof ApiErrorException) {
                report($e);
            }

            throw $this->failure($e, $chatId, get_class($message));
        }
    }

    /**
     * Calls any Bot API method and returns its "result" part.
     *
     * @throws ApiErrorException when Telegram answers ok=false
     */
    public function call(string $method, array $params = [], ?string $token = null, ?int $timeout = null): mixed
    {
        return $this->request($method, $params, $token ?? $this->defaultToken, $timeout)['result'] ?? null;
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, ?string $token = null): void
    {
        $this->call('answerCallbackQuery', array_filter([
            'callback_query_id' => $callbackQueryId,
            'text'              => $text,
        ]), $token);
    }

    /**
     * Replaces the text and buttons of a sent message. No buttons removes the keyboard.
     */
    public function editMessageText(string $chatId, int $messageId, string $text, array $buttons = [], ?string $token = null): void
    {
        $this->call('editMessageText', [
            'chat_id'      => $chatId,
            'message_id'   => $messageId,
            'text'         => $text,
            'parse_mode'   => $this->parseMode,
            'reply_markup' => json_encode(['inline_keyboard' => $buttons]),
        ], $token);
    }

    public function setWebhook(string $url, ?string $secretToken = null, array $allowedUpdates = [], ?string $token = null): void
    {
        $this->call('setWebhook', array_filter([
            'url'             => $url,
            'secret_token'    => $secretToken,
            'allowed_updates' => $allowedUpdates ? json_encode($allowedUpdates) : null,
        ]), $token);
    }

    public function deleteWebhook(?string $token = null): void
    {
        $this->call('deleteWebhook', [], $token);
    }

    public function getWebhookInfo(?string $token = null): array
    {
        return (array) $this->call('getWebhookInfo', [], $token);
    }

    /**
     * Long polling. Only works while no webhook is set for the bot.
     *
     * @return Update[]
     */
    public function getUpdates(?int $offset = null, int $timeout = 30, ?string $token = null): array
    {
        $updates = $this->call('getUpdates', array_filter([
            'offset'  => $offset,
            'timeout' => $timeout,
        ]), $token, $timeout + 10);

        return array_map(fn (array $update) => Update::fromArray($update), (array) $updates);
    }

    /**
     * Retries connection failures and 5xx only. Telegram's 4xx answers come back as they are.
     */
    protected function request(string $method, array $params, ?string $token, ?int $timeout = null, array $attachments = []): array
    {
        if (blank($token)) {
            throw new InvalidTokenException;
        }

        $request = Http::timeout($timeout ?? config('delivra.http.timeout', 10))
            ->connectTimeout(config('delivra.http.connect_timeout', 5))
            ->retry(
                config('delivra.http.retries', 3),
                config('delivra.http.retry_delay', 100),
                fn (\Throwable $e) => $e instanceof ConnectionException || ($e instanceof RequestException && $e->response->serverError()),
                throw: false,
            )
            ->acceptJson();

        foreach ($attachments as $field => $path) {
            $request->attach($field, File::get($path), basename($path));
        }

        // multipart fields are text: booleans as "true"/"false", the rest as strings
        if ($attachments !== []) {
            $params = array_map(fn ($value) => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value, $params);
        }

        $response = $request->post("https://api.telegram.org/bot{$token}/{$method}", $params);

        $result = (array) $response->json();

        if (! ($result['ok'] ?? false)) {
            throw new ApiErrorException(
                (string) ($result['error_code'] ?? $response->status()),
                $result['description'] ?? 'Unknown error',
                $result
            );
        }

        return $result;
    }

    protected function failure(\Throwable $e, string $chatId, string $messageType): TelegramMessageFailedException
    {
        if (! $e instanceof ApiErrorException) {
            return new TelegramMessageFailedException($chatId, $messageType, $e->getMessage());
        }

        return match ($e->getErrorCode()) {
            '403'   => new BotBlockedException($chatId, $messageType, $e->getMessage()),
            '429'   => new RateLimitedException($chatId, $messageType, $e->getMessage(), (int) ($e->getApiResponse()['parameters']['retry_after'] ?? 1)),
            default => new TelegramMessageFailedException($chatId, $messageType, $e->getMessage()),
        };
    }

    protected function sendMessageToMultiple($message, array $chatIds): TelegramBatchResponse
    {
        $batch = new TelegramBatchResponse;

        foreach ($chatIds as $chatId) {
            try {
                if ($response = $this->deliver($message, (string) $chatId)) {
                    $batch->addSuccess($chatId, $response);
                }

                // Throttle to avoid 429 errors (Telegram limit: ~30 messages/second)
                usleep(35000); // ~35ms = ~28 messages/second (safe margin)
            } catch (TelegramMessageFailedException $e) {
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
        return match (true) {
            $message instanceof TextMessage     => 'sendMessage',
            $message instanceof PhotoMessage    => 'sendPhoto',
            $message instanceof VideoMessage    => 'sendVideo',
            $message instanceof DocumentMessage => 'sendDocument',
            $message instanceof PollMessage     => 'sendPoll',
            $message instanceof LocationMessage => 'sendLocation',
            $message instanceof ContactMessage  => 'sendContact',
            default                             => throw new TelegramMessageFailedException(null, get_class($message), 'Unknown message type'),
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
