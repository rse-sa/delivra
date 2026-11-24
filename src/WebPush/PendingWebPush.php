<?php

namespace RSE\Delivra\WebPush;

use RSE\Delivra\Exceptions\WebPush\InvalidMessageException;
use RSE\Delivra\WebPush\Messages\WebPushMessage;

class PendingWebPush
{
    /** @var list<Subscription> */
    protected array $subscriptions = [];

    public function __construct(protected WebPushManager $manager) {}

    /**
     * A model, the browser's subscription JSON, or a list of them.
     */
    public function to(mixed $subscriptions): static
    {
        $this->subscriptions = Subscription::list([...$this->subscriptions, ...Subscription::list($subscriptions)]);

        return $this;
    }

    public function send(WebPushMessage $message, mixed $notifiable = null, mixed $notification = null): WebPushReport
    {
        if ($this->subscriptions === []) {
            throw new InvalidMessageException('No web push subscriptions specified.');
        }

        return $this->manager->sendTo($this->subscriptions, $message, $notifiable, $notification);
    }
}
