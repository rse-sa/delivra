<?php

namespace RSE\Delivra\Fcm\Messages;

/**
 * The "apns" block (iOS) of an FCM v1 message.
 */
class ApnsConfig extends PlatformConfig
{
    public function header(string $name, string|int $value): static
    {
        $this->fields['headers'][$name] = (string) $value;

        return $this;
    }

    /**
     * 10 sends now, 5 lets the device save power. Background pushes must use 5.
     */
    public function priority(int $priority): static
    {
        return $this->header('apns-priority', $priority);
    }

    /**
     * alert, background, voip, ...
     */
    public function pushType(string $type): static
    {
        return $this->header('apns-push-type', $type);
    }

    /**
     * @param  int  $timestamp  Unix time after which Apple drops the message, 0 tries once
     */
    public function expiration(int $timestamp): static
    {
        return $this->header('apns-expiration', $timestamp);
    }

    public function collapseId(string $id): static
    {
        return $this->header('apns-collapse-id', $id);
    }

    public function sound(string $sound = 'default'): static
    {
        return $this->set('payload.aps.sound', $sound);
    }

    public function badge(int $badge): static
    {
        return $this->set('payload.aps.badge', $badge);
    }

    public function category(string $category): static
    {
        return $this->set('payload.aps.category', $category);
    }

    public function threadId(string $threadId): static
    {
        return $this->set('payload.aps.thread-id', $threadId);
    }

    public function contentAvailable(bool $available = true): static
    {
        return $this->set('payload.aps.content-available', $available ? 1 : 0);
    }

    public function mutableContent(bool $mutable = true): static
    {
        return $this->set('payload.aps.mutable-content', $mutable ? 1 : 0);
    }

    /**
     * Custom keys next to "aps" in the APNs payload.
     */
    public function payload(array $payload): static
    {
        return $this->with(['payload' => $payload]);
    }

    public function image(string $url): static
    {
        return $this->set('fcm_options.image', $url);
    }
}
