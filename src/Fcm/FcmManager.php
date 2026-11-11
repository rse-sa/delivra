<?php

namespace RSE\Delivra\Fcm;

use Closure;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RSE\Delivra\Events\DelivraMessageFailed;
use RSE\Delivra\Events\DelivraMessageSending;
use RSE\Delivra\Events\DelivraMessageSent;
use RSE\Delivra\Exceptions\Fcm\FcmConfigurationException;
use RSE\Delivra\Exceptions\Fcm\FcmException;
use RSE\Delivra\Fcm\Events\FcmTokenInvalid;
use RSE\Delivra\Fcm\Messages\FcmMessage;
use Throwable;

/**
 * @mixin Project
 */
class FcmManager
{
    /** Marker for a 401 that a fresh OAuth token may fix. */
    protected const REAUTH = 'reauth';

    /** @var array<string, Project> */
    protected array $projects = [];

    protected ?Closure $invalidTokenCallback = null;

    public function __construct(protected array $config) {}

    public function project(?string $name = null): Project
    {
        $name ??= $this->getDefaultProject();

        return $this->projects[$name] ??= $this->resolve($name);
    }

    protected function resolve(string $name): Project
    {
        $config = $this->config['projects'][$name] ?? null;

        if ($config === null) {
            throw new FcmConfigurationException("FCM project [{$name}] is not configured.");
        }

        return new Project($this, $name, $config);
    }

    public function getDefaultProject(): string
    {
        return $this->config['default'] ?? 'default';
    }

    /** @return list<string> */
    public function projectNames(): array
    {
        return array_keys($this->config['projects'] ?? []);
    }

    /**
     * @param  string|iterable<string>  $tokens
     */
    public function to(string|iterable $tokens): PendingFcm
    {
        return (new PendingFcm($this))->to($tokens);
    }

    public function topic(string $topic): PendingFcm
    {
        return (new PendingFcm($this))->topic($topic);
    }

    public function condition(string $condition): PendingFcm
    {
        return (new PendingFcm($this))->condition($condition);
    }

    /**
     * Send a message that carries its own topic or condition.
     */
    public function send(FcmMessage $message): FcmReport
    {
        return (new PendingFcm($this))->send($message);
    }

    /**
     * Called for every token FCM reports as dead, next to the FcmTokenInvalid event.
     *
     * @param  (Closure(string $token, string $reason, mixed $notifiable, Project $project): mixed)|null  $callback
     */
    public function onInvalidToken(?Closure $callback): static
    {
        $this->invalidTokenCallback = $callback;

        return $this;
    }

    /**
     * Topic or condition set on the message itself.
     *
     * @return list<Target>
     */
    public function targetsFor(FcmMessage $message): array
    {
        return match (true) {
            $message->getTopic() !== null     => [Target::topic($message->getTopic())],
            $message->getCondition() !== null => [Target::condition($message->getCondition())],
            default                           => [],
        };
    }

    /**
     * Send one message to many targets with events. Throws only for configuration errors.
     *
     * @param  list<Target>  $targets
     */
    public function sendTo(Project $project, array $targets, FcmMessage $message, mixed $notifiable = null, mixed $notification = null): FcmReport
    {
        $report  = new FcmReport;
        $payload = $message->toPayload();
        $body    = $message->description();
        $allowed = [];

        foreach ($targets as $target) {
            // halt mode: the first listener that returns false cancels this target
            $result = event(new DelivraMessageSending('fcm', $target->masked(), $body, $payload, $project->name, null, $notifiable, $notification, $message), [], true);

            if ($result !== false) {
                $allowed[] = $target;
            }
        }

        foreach (array_chunk($allowed, max(1, (int) ($this->config['concurrency'] ?? 50))) as $chunk) {
            foreach ($this->deliver($project, $chunk, $payload, $message->isValidateOnly()) as $result) {
                $report->add($result);

                $this->dispatchResultEvents($project, $result, $body, $message, $notifiable, $notification);
            }
        }

        return $report;
    }

    /**
     * The only place that calls FCM to send. The fake overrides it.
     *
     * @param  list<Target>  $targets
     * @return list<FcmResult>
     */
    public function deliver(Project $project, array $targets, array $payload, bool $validateOnly = false): array
    {
        [$results, $retryAfter] = $this->dispatch($project, $targets, $payload, $validateOnly);

        // expired OAuth token: refresh once and resend those
        if ($reauth = array_keys(array_filter($results, fn ($result) => $result === self::REAUTH))) {
            $project->forgetAccessToken();

            [$retried] = $this->dispatch($project, array_intersect_key($targets, array_flip($reauth)), $payload, $validateOnly);

            $results = array_replace($results, $this->failOnReauth($project, $retried));
        }

        // transient failures: one more try
        if ($retryable = array_keys(array_filter($results, fn (FcmResult $result) => $result->retryable))) {
            $this->pause($retryAfter);

            [$retried] = $this->dispatch($project, array_intersect_key($targets, array_flip($retryable)), $payload, $validateOnly);

            $results = array_replace($results, $this->failOnReauth($project, $retried));
        }

        ksort($results);

        return array_values($results);
    }

    /**
     * @param  array<int, Target>  $targets
     * @return array{0: array<int, FcmResult|string>, 1: int}
     */
    protected function dispatch(Project $project, array $targets, array $payload, bool $validateOnly): array
    {
        // fetch the OAuth token once, outside the pool
        $project->accessToken();

        $responses = Http::pool(function (Pool $pool) use ($project, $targets, $payload, $validateOnly) {
            foreach ($targets as $index => $target) {
                $body = ['message' => $target->toPayload() + $payload];

                if ($validateOnly) {
                    $body['validate_only'] = true;
                }

                $project->configure($pool->as((string) $index))->post('messages:send', $body);
            }
        });

        $results    = [];
        $retryAfter = 0;

        foreach ($targets as $index => $target) {
            $response = $responses[$index] ?? $responses[(string) $index] ?? null;

            if (! $response instanceof Response) {
                $results[$index] = new FcmResult($target, FcmResult::FAILED, error: 'CONNECTION', errorMessage: $response instanceof Throwable ? $response->getMessage() : 'No response', retryable: true, exception: $response instanceof Throwable ? $response : null);

                continue;
            }

            $retryAfter      = max($retryAfter, (int) $response->header('Retry-After'));
            $results[$index] = $this->classify($project, $target, $response);
        }

        return [$results, $retryAfter];
    }

    /**
     * @param  array<int, FcmResult|string>  $results
     * @return array<int, FcmResult>
     */
    protected function failOnReauth(Project $project, array $results): array
    {
        if (in_array(self::REAUTH, $results, true)) {
            throw new FcmConfigurationException("FCM rejected the credentials of project [{$project->name}].", 401);
        }

        return $results;
    }

    /**
     * Map an FCM response to a result. Wrong project or permission throws.
     */
    public function classify(Project $project, Target $target, Response $response): FcmResult|string
    {
        if ($response->successful()) {
            return FcmResult::sent($target, $response->json('name'), $response->status());
        }

        $error   = (array) ($response->json('error') ?? []);
        $code    = collect($error['details'] ?? [])->pluck('errorCode')->filter()->first();
        $status  = $error['status'] ?? null;
        $message = $error['message'] ?? $response->reason();
        $http    = $response->status();

        $result = fn (string $result, bool $retryable = false) => new FcmResult(
            $target, $result, null, $code ?? $status ?? (string) $http, $message, $http, $retryable,
        );

        return match (true) {
            $code === 'UNREGISTERED'       => $result(FcmResult::INVALID_TOKEN),
            $code === 'SENDER_ID_MISMATCH' => $result(FcmResult::WRONG_PROJECT),
            $code === 'THIRD_PARTY_AUTH_ERROR',
            $code === 'INVALID_ARGUMENT',
            $status === 'INVALID_ARGUMENT' => $result(FcmResult::FAILED),
            in_array($code, ['UNAVAILABLE', 'INTERNAL', 'QUOTA_EXCEEDED'], true),
            in_array($http, [429, 500, 502, 503, 504], true) => $result(FcmResult::FAILED, true),
            $http === 401                  => self::REAUTH,
            $http === 403, $http === 404   => throw new FcmConfigurationException("FCM rejected the request for project [{$project->name}]: {$message}", $http, $response->json()),
            default                        => $result(FcmResult::FAILED),
        };
    }

    protected function pause(int $retryAfter): void
    {
        $max     = (int) ($this->config['retry_max_delay'] ?? 5);
        $seconds = min($retryAfter > 0 ? $retryAfter : 1, $max);

        if ($seconds <= 0) {
            return;
        }

        class_exists(Sleep::class) ? Sleep::sleep($seconds) : sleep($seconds);
    }

    protected function dispatchResultEvents(Project $project, FcmResult $result, string $body, FcmMessage $message, mixed $notifiable, mixed $notification): void
    {
        $recipient = $result->target->masked();

        if ($result->successful()) {
            event(new DelivraMessageSent('fcm', $recipient, $body, $result->messageId, $result, $notifiable, $notification, $message));

            return;
        }

        event(new DelivraMessageFailed(
            'fcm', $recipient, $body, "{$result->error}: {$result->errorMessage}",
            $result->exception ?? new FcmException("FCM send failed [{$result->error}]: {$result->errorMessage}"),
            $notifiable, $notification, $message,
        ));

        if ($result->isInvalidToken()) {
            $this->tokenInvalid($project, $result->token(), $result->error ?? 'UNREGISTERED', $notifiable, $notification);
        }
    }

    protected function tokenInvalid(Project $project, string $token, string $reason, mixed $notifiable = null, mixed $notification = null): void
    {
        event(new FcmTokenInvalid($token, $reason, $project->name, $notifiable, $notification));

        if ($this->invalidTokenCallback !== null) {
            call_user_func($this->invalidTokenCallback, $token, $reason, $notifiable, $project);
        }
    }

    /**
     * Subscribe or unsubscribe tokens through the Instance ID API, 1,000 per call.
     *
     * @param  'subscribe'|'unsubscribe'  $action
     * @param  list<string>  $tokens
     */
    public function manageTopic(Project $project, string $action, string $topic, array $tokens): FcmReport
    {
        $report   = new FcmReport;
        $endpoint = $action === 'subscribe' ? 'batchAdd' : 'batchRemove';

        foreach (array_chunk($tokens, 1000) as $chunk) {
            $response = $this->callTopicApi($project, $endpoint, $topic, $chunk);

            if ($response->status() === 401) {
                $project->forgetAccessToken();

                $response = $this->callTopicApi($project, $endpoint, $topic, $chunk);
            }

            if (in_array($response->status(), [401, 403], true)) {
                throw new FcmConfigurationException("FCM rejected the topic request for project [{$project->name}]: " . ($response->json('error') ?? $response->reason()), $response->status(), $response->json());
            }

            foreach ($chunk as $index => $token) {
                $target = Target::token($token);
                $error  = $response->failed() ? ($response->json('error') ?? (string) $response->status()) : $response->json("results.{$index}.error");
                $error  = is_array($error) ? ($error['status'] ?? $error['message'] ?? 'ERROR') : $error;

                if ($error === null) {
                    $report->add(FcmResult::sent($target, null, $response->status()));

                    continue;
                }

                $invalid = $response->successful() && $error === 'NOT_FOUND';

                $report->add(new FcmResult(
                    $target,
                    $invalid ? FcmResult::INVALID_TOKEN : FcmResult::FAILED,
                    error: $error,
                    httpStatus: $response->status(),
                    retryable: in_array($response->status(), [429, 500, 502, 503, 504], true),
                ));

                if ($invalid) {
                    $this->tokenInvalid($project, $token, $error);
                }
            }
        }

        return $report;
    }

    /**
     * @param  list<string>  $tokens
     */
    protected function callTopicApi(Project $project, string $endpoint, string $topic, array $tokens): Response
    {
        return $project->configure(Http::withOptions([]), rtrim($this->getConfig('iid_url', 'https://iid.googleapis.com'), '/'))
            // required by the Instance ID API for OAuth tokens
            ->withHeaders(['access_token_auth' => 'true'])
            ->post("iid/v1:{$endpoint}", [
                'to'                  => "/topics/{$topic}",
                'registration_tokens' => $tokens,
            ]);
    }

    public function getConfig(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->config : data_get($this->config, $key, $default);
    }

    public function __call(string $method, array $parameters): mixed
    {
        return $this->project()->{$method}(...$parameters);
    }
}
