<?php

namespace RSE\Delivra\Concerns;

use Illuminate\Notifications\Notification;

/**
 * Lets another package take over a channel's sends for its own route objects.
 *
 * routeNotificationFor{Channel}() usually returns an address (chat id, phone, token).
 * When it returns an object whose class has a sender, the channel calls the sender instead.
 * Each manager keeps its own senders: Telegram::sendUsing() does not touch Sms.
 */
trait HasCustomSenders
{
    /**
     * @var array<class-string, callable|class-string>
     */
    protected static array $senders = [];

    /**
     * The sender gets ($message, $route, $notifiable, $notification) and returns what was sent,
     * or null when it sent nothing. A closure, any callable, or a class with __invoke() from the container.
     */
    public static function sendUsing(string $routeClass, callable|string $sender): void
    {
        static::$senders[$routeClass] = $sender;
    }

    public static function senderFor(mixed $route): ?callable
    {
        if (! is_object($route)) {
            return null;
        }

        foreach (static::$senders as $class => $sender) {
            if ($route instanceof $class) {
                return is_string($sender) && ! is_callable($sender) ? app($sender) : $sender;
            }
        }

        return null;
    }

    public static function flushSenders(): void
    {
        static::$senders = [];
    }

    /**
     * Channel results of a custom send: [result], or [] when the sender sent nothing.
     * Null when no sender takes the route, so the channel sends the usual way.
     */
    public static function sendThroughSender(mixed $message, mixed $route, mixed $notifiable, Notification $notification): ?array
    {
        if (! $sender = static::senderFor($route)) {
            return null;
        }

        $result = $sender($message, $route, $notifiable, $notification);

        return $result === null ? [] : [$result];
    }
}
