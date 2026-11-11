<?php

namespace RSE\Delivra\Fcm;

use ArrayIterator;
use Countable;
use Illuminate\Support\Collection;
use IteratorAggregate;
use Traversable;

/**
 * Results of one send or topic call, one per target.
 *
 * @implements IteratorAggregate<int, FcmResult>
 */
class FcmReport implements Countable, IteratorAggregate
{
    /** @param list<FcmResult> $results */
    public function __construct(protected array $results = []) {}

    public function add(FcmResult $result): static
    {
        $this->results[] = $result;

        return $this;
    }

    public function merge(FcmReport $report): static
    {
        array_push($this->results, ...$report->all());

        return $this;
    }

    /** @return list<FcmResult> */
    public function all(): array
    {
        return $this->results;
    }

    /** @return Collection<int, FcmResult> */
    public function results(): Collection
    {
        return collect($this->results);
    }

    /** @return Collection<int, FcmResult> */
    public function successes(): Collection
    {
        return $this->results()->filter->successful()->values();
    }

    /** @return Collection<int, FcmResult> */
    public function failures(): Collection
    {
        return $this->results()->filter->failed()->values();
    }

    /**
     * Tokens FCM reported as no longer valid. Delete or disable them.
     *
     * @return list<string>
     */
    public function invalidTokens(): array
    {
        return $this->results()->filter->isInvalidToken()->map->token()->values()->all();
    }

    public function firstMessageId(): ?string
    {
        return $this->successes()->first()?->messageId;
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

    public function count(): int
    {
        return count($this->results);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->results);
    }
}
