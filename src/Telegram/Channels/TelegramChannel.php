<?php

namespace RSE\Delivra\Telegram\Channels;

use Exception;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use RSE\Delivra\Events\DelivraMessageFailed;
use RSE\Delivra\Events\DelivraMessageSending;
use RSE\Delivra\Events\DelivraMessageSent;
use RSE\Delivra\Exceptions\Telegram\ApiErrorException;
use RSE\Delivra\Telegram\Messages\TelegramMessageAbstract;
use RSE\Delivra\Telegram\Telegram;
use RSE\Delivra\Telegram\TelegramResponse;

class TelegramChannel
{
    public function __construct(protected Telegram $telegram) {}

    public function send($notifiable, Notification $notification)
    {
        if (! method_exists($notification, 'toTelegram')) {
            throw new Exception('toTelegram method is missing');
        }

        $message = $notification->toTelegram($notifiable);

        if (! $message instanceof TelegramMessageAbstract) {
            throw new Exception('Wrong return format from toTelegram method');
        }

        // Get receivers from notifiable if not set on message
        if (empty($message->getReceivers())) {
            if ($to = $notifiable->routeNotificationFor('telegram', $notification)) {
                $message->to($to);
            } else {
                throw new Exception('No receivers specified for Telegram message');
            }
        }

        $receivers = $message->getReceivers();
        $results   = [];

        foreach ($receivers as $chatId) {
            $singleMessage = clone $message;
            $singleMessage->to([$chatId]);

            $endpoint           = $this->telegram->getEndpointForMessage($singleMessage);
            $payload            = $singleMessage->toArray();
            $payload['chat_id'] = $chatId;

            $token = $this->telegram->getTokenForMessage($singleMessage);
            $body  = $this->getMessageBody($singleMessage);

            $eventResults = event(new DelivraMessageSending('telegram', $chatId, $body, $payload, null, $token, $notifiable, $notification, $singleMessage));
            if (isset($eventResults[0]) && $eventResults[0] === false) {
                continue;
            }

            try {
                $response = Http::timeout(config('delivra.http.timeout', 10))
                    ->connectTimeout(config('delivra.http.connect_timeout', 5))
                    ->retry(config('delivra.http.retries', 3), config('delivra.http.retry_delay', 100))
                    ->acceptJson()
                    ->post("https://api.telegram.org/bot{$token}/{$endpoint}", $payload);

                $result = $response->json();

                if (! ($result['ok'] ?? false)) {
                    throw new ApiErrorException(
                        $result['error_code'] ?? 'UNKNOWN',
                        $result['description'] ?? 'Unknown error',
                        $result
                    );
                }

                $responseObj = new TelegramResponse($chatId, $result);
                event(new DelivraMessageSent('telegram', $chatId, $body, $responseObj->getMessageId(), $responseObj, $notifiable, $notification, $singleMessage));

                $results[] = $responseObj;
            } catch (\Throwable $e) {
                event(new DelivraMessageFailed('telegram', $chatId, $body, $e->getMessage(), $e, $notifiable, $notification, $singleMessage));
                throw $e;
            }
        }

        return $results;
    }

    private function getMessageBody($message): string
    {
        if (method_exists($message, 'getCaption') && $message->getCaption()) {
            return $message->getCaption();
        }

        if (method_exists($message, 'getMessage') && $message->getMessage()) {
            return $message->getMessage();
        }

        return get_class($message);
    }
}
