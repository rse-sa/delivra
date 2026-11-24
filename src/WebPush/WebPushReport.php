<?php

namespace RSE\Delivra\WebPush;

use ArrayIterator;
use Countable;
use Illuminate\Support\Collection;
use IteratorAggregate;
use Traversable;

/**
 * Results of one send, one per subscription.
 *
 * @implements IteratorAggregate<int, WebPushResult>
 */
class WebPushReport implements Countable, IteratorAggregate
{
    /** @param list<WebPushResult> $results */
    public function __construct(protected array $results = []) {}

    public function add(WebPushResult $result): static
    {
        $this->results[] = $result;

        return $this;
    }

    /** @return list<WebPushResult> */
    public function all(): array
    {
        return $this->results;
    }

    /** @return Collection<int, WebPushResult> */
    public function results(): Collection
    {
        return collect($this->results);
    }

    /** @return Collection<int, WebPushResult> */
    public function successes(): Collection
    {
        return $this->results()->filter->successful()->values();
    }

    /** @return Collection<int, WebPushResult> */
    public function failures(): Collection
    {
        return $this->results()->filter->failed()->values();
    }

    /**
     * Endpoints the push service reported as gone. Delete them, unless delete_expired does it.
     *
     * @return list<string>
     */
    public function expiredEndpoints(): array
    {
        return $this->results()->filter->isExpired()->map->endpoint()->values()->all();
    }

    public function successfulCount(): int
    {
        return $this->successes()->count();
    }

    public function failedCount(): int
    {
        return $this->failures()->count();
    }

    public function hasFailures(): bool
    {
        return $this->failedCount() > 0;
    }

    public function isEmpty(): bool
    {
        return $this->results === [];
    }

    public function count(): int
    {
        return count($this->results);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->results);
    }
}
