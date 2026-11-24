<?php

namespace RSE\Delivra\WebPush;

use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Database\Eloquent\Model;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\Subscription as LibrarySubscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush as LibraryWebPush;
use Psr\Http\Client\ClientInterface;
use RSE\Delivra\Concerns\HasCustomSenders;
use RSE\Delivra\Events\DelivraMessageFailed;
use RSE\Delivra\Events\DelivraMessageSending;
use RSE\Delivra\Events\DelivraMessageSent;
use RSE\Delivra\Exceptions\WebPush\WebPushConfigurationException;
use RSE\Delivra\Exceptions\WebPush\WebPushException;
use RSE\Delivra\WebPush\Events\WebPushSubscriptionExpired;
use RSE\Delivra\WebPush\Messages\WebPushMessage;
use Throwable;

/**
 * Browser push with VAPID keys. minishlink/web-push does the encryption and the JWT signing.
 */
class WebPushManager
{
    use HasCustomSenders;

    protected ?ClientInterface $httpClient = null;

    public function __construct(protected array $config) {}

    /**
     * A model, the browser's subscription JSON, or a list of them.
     */
    public function to(mixed $subscriptions): PendingWebPush
    {
        return (new PendingWebPush($this))->to($subscriptions);
    }

    public function message(?string $title = null, ?string $body = null): WebPushMessage
    {
        return WebPushMessage::make($title, $body);
    }

    /**
     * The key the browser needs for pushManager.subscribe({ applicationServerKey }).
     */
    public function publicKey(): ?string
    {
        return $this->getConfig('vapid.public_key') ?: null;
    }

    /**
     * Keys set and the library installed. Apps use it to hide the subscribe button.
     */
    public function configured(): bool
    {
        return $this->libraryInstalled()
            && filled($this->getConfig('vapid.public_key'))
            && filled($this->getConfig('vapid.private_key'));
    }

    /**
     * Send one message to many subscriptions with events. Throws only for configuration errors.
     *
     * @param  list<Subscription>  $subscriptions
     */
    public function sendTo(array $subscriptions, WebPushMessage $message, mixed $notifiable = null, mixed $notification = null): WebPushReport
    {
        $report  = new WebPushReport;
        $payload = $message->toArray();
        $body    = $message->description();
        $allowed = [];

        foreach ($subscriptions as $subscription) {
            // halt mode: the first listener that returns false cancels this subscription
            $result = event(new DelivraMessageSending('webpush', $subscription->masked(), $body, $payload, null, null, $notifiable, $notification, $message), [], true);

            if ($result !== false) {
                $allowed[] = $subscription;
            }
        }

        foreach ($allowed === [] ? [] : $this->deliver($allowed, $message) as $result) {
            $report->add($result);

            $this->dispatchResultEvents($result, $body, $message, $notifiable, $notification);
        }

        return $report;
    }

    /**
     * The only place that calls the push services. The fake overrides it.
     *
     * @param  list<Subscription>  $subscriptions
     * @return list<WebPushResult>
     */
    public function deliver(array $subscriptions, WebPushMessage $message): array
    {
        $client  = $this->client();
        $payload = $message->toPayload();
        $options = $message->getOptions();

        // one at a time: the library sends in sequence anyway, and a subscription
        // with broken keys throws while encrypting, which would stop a whole batch
        return array_map(function (Subscription $subscription) use ($client, $payload, $options) {
            try {
                return $this->classify($subscription, $client->sendOneNotification($this->toLibrarySubscription($subscription), $payload, $options));
            } catch (Throwable $e) {
                return new WebPushResult($subscription, WebPushResult::FAILED, reason: $e->getMessage(), exception: $e);
            }
        }, array_values($subscriptions));
    }

    public function classify(Subscription $subscription, MessageSentReport $report): WebPushResult
    {
        $status = $report->getResponse()?->getStatusCode();

        return match (true) {
            $report->isSuccess()             => WebPushResult::sent($subscription, $status),
            $report->isSubscriptionExpired() => new WebPushResult($subscription, WebPushResult::EXPIRED, $status, $report->getReason()),
            default                          => new WebPushResult($subscription, WebPushResult::FAILED, $status, $report->getReason()),
        };
    }

    protected function dispatchResultEvents(WebPushResult $result, string $body, WebPushMessage $message, mixed $notifiable, mixed $notification): void
    {
        $recipient = $result->subscription->masked();

        if ($result->successful()) {
            event(new DelivraMessageSent('webpush', $recipient, $body, null, $result, $notifiable, $notification, $message));

            return;
        }

        $error = trim(($result->httpStatus ? "HTTP {$result->httpStatus}: " : '') . $result->reason);

        event(new DelivraMessageFailed(
            'webpush', $recipient, $body, $error,
            $result->exception ?? new WebPushException("Web push failed [{$result->status}]: {$error}"),
            $notifiable, $notification, $message,
        ));

        if ($result->isExpired()) {
            $this->subscriptionExpired($result, $notifiable, $notification);
        }
    }

    protected function subscriptionExpired(WebPushResult $result, mixed $notifiable, mixed $notification): void
    {
        $subscription = $result->subscription;

        event(new WebPushSubscriptionExpired($subscription->endpoint, $result->httpStatus, $subscription, $notifiable, $notification));

        if ($this->getConfig('delete_expired', true) && $subscription->model() instanceof Model) {
            $subscription->model()->delete();
        }
    }

    /**
     * The minishlink client with the VAPID keys and the config defaults.
     */
    public function client(): LibraryWebPush
    {
        $this->ensureLibraryInstalled();

        try {
            $client = new LibraryWebPush(['VAPID' => $this->vapid()], $this->defaultOptions(), $this->httpClient());
        } catch (WebPushConfigurationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new WebPushConfigurationException('Web push VAPID keys are invalid: ' . $e->getMessage(), previous: $e);
        }

        $client->setAutomaticPadding((bool) $this->getConfig('automatic_padding', true));

        return $client;
    }

    /**
     * @return array{subject: string, publicKey: string, privateKey: string}
     */
    public function vapid(): array
    {
        $public  = $this->getConfig('vapid.public_key');
        $private = $this->getConfig('vapid.private_key');

        if (blank($public) || blank($private)) {
            throw new WebPushConfigurationException('Web push needs VAPID keys. Run php artisan delivra:webpush-vapid and set DELIVRA_WEBPUSH_PUBLIC_KEY and DELIVRA_WEBPUSH_PRIVATE_KEY.');
        }

        return [
            'subject'    => $this->getConfig('vapid.subject') ?: (string) config('app.url'),
            'publicKey'  => $public,
            'privateKey' => $private,
        ];
    }

    /**
     * @return array{TTL: int, urgency: ?string, topic: ?string}
     */
    public function defaultOptions(): array
    {
        return [
            'TTL'     => (int) $this->getConfig('ttl', 2419200),
            'urgency' => $this->getConfig('urgency') ?: null,
            'topic'   => $this->getConfig('topic') ?: null,
        ];
    }

    /**
     * Swap the PSR-18 client the pushes go through, e.g. a Guzzle client with a mock handler in tests.
     */
    public function usingHttpClient(?ClientInterface $client): static
    {
        $this->httpClient = $client;

        return $this;
    }

    protected function httpClient(): ClientInterface
    {
        return $this->httpClient ?? new GuzzleClient([
            'timeout'         => (float) config('delivra.http.timeout', 10),
            'connect_timeout' => (float) config('delivra.http.connect_timeout', 5),
        ]);
    }

    /**
     * @return array{publicKey: string, privateKey: string}
     */
    public function createVapidKeys(): array
    {
        $this->ensureLibraryInstalled();

        return VAPID::createVapidKeys();
    }

    public function libraryInstalled(): bool
    {
        return class_exists(LibraryWebPush::class);
    }

    public function ensureLibraryInstalled(): void
    {
        if (! $this->libraryInstalled()) {
            throw new WebPushConfigurationException('Web push needs minishlink/web-push. Run: composer require minishlink/web-push');
        }
    }

    protected function toLibrarySubscription(Subscription $subscription): LibrarySubscription
    {
        return new LibrarySubscription($subscription->endpoint, $subscription->publicKey, $subscription->authToken, $subscription->contentEncoding);
    }

    public function getConfig(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->config : data_get($this->config, $key, $default);
    }
}
