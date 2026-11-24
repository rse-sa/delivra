<?php

namespace RSE\Delivra\WebPush\Testing;

use Closure;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert as PHPUnit;
use RSE\Delivra\WebPush\Messages\WebPushMessage;
use RSE\Delivra\WebPush\Subscription;
use RSE\Delivra\WebPush\WebPushManager;
use RSE\Delivra\WebPush\WebPushResult;

/**
 * Records pushes instead of sending them. Needs no VAPID keys and no minishlink/web-push.
 */
class WebPushFake extends WebPushManager
{
    /** @var list<SentWebPush> */
    protected array $sent = [];

    /** @var array<string, int> endpoint => HTTP status */
    protected array $failures = [];

    /**
     * Make the push service answer 410 Gone for these endpoints.
     *
     * @param  string|iterable<string>  $endpoints
     */
    public function expire(string|iterable $endpoints): static
    {
        return $this->failFor($endpoints, 410);
    }

    /**
     * Make the push service answer with this HTTP status for these endpoints.
     *
     * @param  string|iterable<string>  $endpoints
     */
    public function failFor(string|iterable $endpoints, int $status = 500): static
    {
        foreach (is_string($endpoints) ? [$endpoints] : $endpoints as $endpoint) {
            $this->failures[$endpoint] = $status;
        }

        return $this;
    }

    public function deliver(array $subscriptions, WebPushMessage $message): array
    {
        return array_map(function (Subscription $subscription) use ($message) {
            $this->sent[] = new SentWebPush($subscription, $message, $message->toArray(), $message->getOptions() + $this->defaultOptions());

            $status = $this->failures[$subscription->endpoint] ?? null;

            return match (true) {
                $status === null                     => WebPushResult::sent($subscription),
                in_array($status, [404, 410], true) => new WebPushResult($subscription, WebPushResult::EXPIRED, $status, 'Faked expiry'),
                default                              => new WebPushResult($subscription, WebPushResult::FAILED, $status, 'Faked failure'),
            };
        }, $subscriptions);
    }

    /**
     * @param  (Closure(SentWebPush): bool)|null  $callback
     * @return Collection<int, SentWebPush>
     */
    public function sent(?Closure $callback = null): Collection
    {
        return collect($this->sent)->filter($callback ?? fn () => true)->values();
    }

    /**
     * @param  (Closure(WebPushMessage, SentWebPush): bool)|null  $callback
     */
    public function assertSent(?Closure $callback = null): static
    {
        PHPUnit::assertTrue($this->matching($callback)->isNotEmpty(), 'The expected web push was not sent.');

        return $this;
    }

    /**
     * @param  (Closure(WebPushMessage, SentWebPush): bool)|null  $callback
     */
    public function assertSentTo(string $endpoint, ?Closure $callback = null): static
    {
        PHPUnit::assertTrue(
            $this->matching($callback)->contains(fn (SentWebPush $push) => $push->endpoint() === $endpoint),
            "No matching web push was sent to [{$endpoint}]."
        );

        return $this;
    }

    /**
     * @param  (Closure(WebPushMessage, SentWebPush): bool)|null  $callback
     */
    public function assertNotSent(?Closure $callback = null): static
    {
        PHPUnit::assertTrue($this->matching($callback)->isEmpty(), 'An unexpected web push was sent.');

        return $this;
    }

    public function assertNothingSent(): static
    {
        PHPUnit::assertEmpty($this->sent, count($this->sent) . ' unexpected web pushes were sent.');

        return $this;
    }

    public function assertSentCount(int $count): static
    {
        PHPUnit::assertCount($count, $this->sent, 'Unexpected number of web pushes sent.');

        return $this;
    }

    /**
     * @return Collection<int, SentWebPush>
     */
    protected function matching(?Closure $callback): Collection
    {
        return $this->sent(fn (SentWebPush $push) => $callback === null || $callback($push->message, $push));
    }
}
