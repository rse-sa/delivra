<?php

namespace RSE\Delivra\Fcm\Messages;

/**
 * The "webpush" block (browsers) of an FCM v1 message.
 */
class WebpushConfig extends PlatformConfig
{
    /**
     * Page that opens when the user clicks the notification. Must be https.
     */
    public function link(string $url): static
    {
        return $this->set('fcm_options.link', $url);
    }

    public function header(string $name, string|int $value): static
    {
        $this->fields['headers'][$name] = (string) $value;

        return $this;
    }

    /**
     * @param  int  $seconds  how long the push service keeps the message
     */
    public function ttl(int $seconds): static
    {
        return $this->header('TTL', $seconds);
    }

    /**
     * very-low, low, normal or high.
     */
    public function urgency(string $urgency): static
    {
        return $this->header('Urgency', $urgency);
    }

    /**
     * Pending messages with the same topic replace each other.
     */
    public function topic(string $topic): static
    {
        return $this->header('Topic', $topic);
    }

    public function icon(string $url): static
    {
        return $this->set('notification.icon', $url);
    }

    public function badge(string $url): static
    {
        return $this->set('notification.badge', $url);
    }

    public function image(string $url): static
    {
        return $this->set('notification.image', $url);
    }

    public function tag(string $tag): static
    {
        return $this->set('notification.tag', $tag);
    }

    public function renotify(bool $renotify = true): static
    {
        return $this->set('notification.renotify', $renotify);
    }

    public function requireInteraction(bool $require = true): static
    {
        return $this->set('notification.requireInteraction', $require);
    }

    public function silent(bool $silent = true): static
    {
        return $this->set('notification.silent', $silent);
    }

    public function dir(string $dir): static
    {
        return $this->set('notification.dir', $dir);
    }

    public function lang(string $lang): static
    {
        return $this->set('notification.lang', $lang);
    }

    /**
     * Button on the notification. The service worker handles the action.
     */
    public function action(string $action, string $title, ?string $icon = null): static
    {
        $this->fields['notification']['actions'][] = array_filter(compact('action', 'title', 'icon'), fn ($value) => $value !== null);

        return $this;
    }

    /**
     * Data for the service worker only, string values.
     */
    public function data(array $data): static
    {
        return $this->with(['data' => FcmMessage::stringifyData($data)]);
    }
}
