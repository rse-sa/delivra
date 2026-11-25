<?php

namespace RSE\Delivra\InApp\Testing;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert as PHPUnit;
use RSE\Delivra\InApp\InAppManager;
use RSE\Delivra\InApp\Messages\InAppMessage;
use RSE\Delivra\InApp\Models\InAppNotification;

/**
 * Records bell notifications instead of writing them. Needs no table.
 */
class InAppFake extends InAppManager
{
    /** @var list<array{notification: InAppNotification, message: InAppMessage}> */
    protected array $sent = [];

    public function store(InAppNotification $notification, InAppMessage $message): InAppNotification
    {
        $this->sent[] = ['notification' => $notification, 'message' => $message];

        return $notification;
    }

    /**
     * @param  (Closure(InAppMessage, InAppNotification): bool)|null  $callback
     * @return Collection<int, InAppMessage>
     */
    public function sent(?Closure $callback = null): Collection
    {
        return collect($this->sent)
            ->filter(fn (array $sent) => $callback === null || $callback($sent['message'], $sent['notification']))
            ->pluck('message')
            ->values();
    }

    /**
     * @param  (Closure(InAppMessage, InAppNotification): bool)|null  $callback
     */
    public function assertSent(?Closure $callback = null): static
    {
        PHPUnit::assertTrue($this->sent($callback)->isNotEmpty(), 'The expected in-app notification was not sent.');

        return $this;
    }

    /**
     * @param  (Closure(InAppMessage, InAppNotification): bool)|null  $callback
     */
    public function assertSentTo(Model $notifiable, ?Closure $callback = null): static
    {
        PHPUnit::assertTrue(
            $this->sentTo($notifiable, $callback)->isNotEmpty(),
            'No matching in-app notification was sent to [' . $notifiable->getMorphClass() . ':' . $notifiable->getKey() . '].'
        );

        return $this;
    }

    /**
     * @param  (Closure(InAppMessage, InAppNotification): bool)|null  $callback
     */
    public function assertNotSentTo(Model $notifiable, ?Closure $callback = null): static
    {
        PHPUnit::assertTrue(
            $this->sentTo($notifiable, $callback)->isEmpty(),
            'An unexpected in-app notification was sent to [' . $notifiable->getMorphClass() . ':' . $notifiable->getKey() . '].'
        );

        return $this;
    }

    public function assertNothingSent(): static
    {
        PHPUnit::assertEmpty($this->sent, count($this->sent) . ' unexpected in-app notifications were sent.');

        return $this;
    }

    public function assertSentCount(int $count): static
    {
        PHPUnit::assertCount($count, $this->sent, 'Unexpected number of in-app notifications sent.');

        return $this;
    }

    /**
     * @return Collection<int, InAppMessage>
     */
    protected function sentTo(Model $notifiable, ?Closure $callback): Collection
    {
        return $this->sent(fn (InAppMessage $message, InAppNotification $notification) => $notification->notifiable_type === $notifiable->getMorphClass()
            && (string) $notification->notifiable_id === (string) $notifiable->getKey()
            && ($callback === null || $callback($message, $notification)));
    }
}
