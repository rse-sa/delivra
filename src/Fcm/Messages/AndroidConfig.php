<?php

namespace RSE\Delivra\Fcm\Messages;

/**
 * The "android" block of an FCM v1 message.
 */
class AndroidConfig extends PlatformConfig
{
    /**
     * Delivery priority: "high" wakes the device, "normal" may wait.
     */
    public function priority(string $priority): static
    {
        return $this->set('priority', strtolower($priority));
    }

    /**
     * @param  int  $seconds  how long FCM keeps the message for an offline device
     */
    public function ttl(int $seconds): static
    {
        return $this->set('ttl', "{$seconds}s");
    }

    public function collapseKey(string $key): static
    {
        return $this->set('collapse_key', $key);
    }

    public function restrictedPackageName(string $package): static
    {
        return $this->set('restricted_package_name', $package);
    }

    /**
     * Notification channel id, must exist in the app (Android 8+).
     */
    public function channel(string $channelId): static
    {
        return $this->set('notification.channel_id', $channelId);
    }

    public function sound(string $sound = 'default'): static
    {
        return $this->set('notification.sound', $sound);
    }

    public function icon(string $icon): static
    {
        return $this->set('notification.icon', $icon);
    }

    /**
     * @param  string  $color  #rrggbb
     */
    public function color(string $color): static
    {
        return $this->set('notification.color', $color);
    }

    /**
     * Notifications with the same tag replace each other in the tray.
     */
    public function tag(string $tag): static
    {
        return $this->set('notification.tag', $tag);
    }

    public function clickAction(string $action): static
    {
        return $this->set('notification.click_action', $action);
    }

    public function image(string $url): static
    {
        return $this->set('notification.image', $url);
    }

    /**
     * Tray priority: PRIORITY_MIN, PRIORITY_LOW, PRIORITY_DEFAULT, PRIORITY_HIGH, PRIORITY_MAX.
     */
    public function notificationPriority(string $priority): static
    {
        return $this->set('notification.notification_priority', strtoupper($priority));
    }

    public function visibility(string $visibility): static
    {
        return $this->set('notification.visibility', strtoupper($visibility));
    }

    public function notificationCount(int $count): static
    {
        return $this->set('notification.notification_count', $count);
    }
}
