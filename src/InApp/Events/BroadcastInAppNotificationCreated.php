<?php

namespace RSE\Delivra\InApp\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use ReflectionMethod;
use RSE\Delivra\InApp\InAppRenderer;
use RSE\Delivra\InApp\Models\InAppNotification;

/**
 * Sent to the receiver's private channel when delivra.in_app.broadcast is on.
 * Rendered in the receiver's preferred locale, or the app locale.
 */
class BroadcastInAppNotificationCreated implements ShouldBroadcast
{
    use SerializesModels;

    public array $rendered;

    public function __construct(
        public InAppNotification $notification,
        public Model $notifiable,
        public ?Notification $source = null,
    ) {
        $locale = $notifiable instanceof HasLocalePreference ? $notifiable->preferredLocale() : null;

        $this->rendered = InAppRenderer::forJson($notification->render($locale ?: null));
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        if ($this->usesCustomChannel()) {
            // same argument as Laravel's broadcast notifications: the notification class
            $channels = $this->notifiable->receivesBroadcastNotificationsOn($this->source);

            return array_map(
                fn ($channel) => $channel instanceof Channel ? $channel : new PrivateChannel($channel),
                is_array($channels) ? $channels : [$channels],
            );
        }

        // same name as Laravel's broadcast notifications, so apps reuse their channel rule
        return [new PrivateChannel(str_replace('\\', '.', get_class($this->notifiable)) . '.' . $this->notifiable->getKey())];
    }

    /**
     * InApp::send() has no notification class. A method that needs one gets the default channel.
     */
    protected function usesCustomChannel(): bool
    {
        if (! method_exists($this->notifiable, 'receivesBroadcastNotificationsOn')) {
            return false;
        }

        if ($this->source !== null) {
            return true;
        }

        $parameter = (new ReflectionMethod($this->notifiable, 'receivesBroadcastNotificationsOn'))->getParameters()[0] ?? null;

        return $parameter === null || $parameter->allowsNull();
    }

    public function broadcastAs(): string
    {
        return 'in-app-notification.created';
    }

    public function broadcastWith(): array
    {
        return $this->rendered;
    }
}
