<?php

namespace RSE\Delivra\Fcm;

use RSE\Delivra\Exceptions\Fcm\InvalidMessageException;
use RSE\Delivra\Fcm\Messages\FcmMessage;

class PendingFcm
{
    /** @var list<Target> */
    protected array $targets = [];

    /**
     * @param  Project|null  $project  null lets the message pick its project
     */
    public function __construct(
        protected FcmManager $manager,
        protected ?Project $project = null,
    ) {}

    /**
     * @param  string|iterable<string>  $tokens
     */
    public function to(string|iterable $tokens): static
    {
        foreach (Target::tokens($tokens) as $token) {
            $this->targets[] = Target::token($token);
        }

        return $this;
    }

    public function topic(string $topic): static
    {
        $this->targets[] = Target::topic($topic);

        return $this;
    }

    public function condition(string $condition): static
    {
        $this->targets[] = Target::condition($condition);

        return $this;
    }

    public function send(FcmMessage $message, mixed $notifiable = null, mixed $notification = null): FcmReport
    {
        $project = $this->project ?? $this->manager->project($message->getProject());
        $targets = $this->targets ?: $this->manager->targetsFor($message);

        if ($targets === []) {
            throw new InvalidMessageException('No FCM tokens, topic or condition specified.');
        }

        return $this->manager->sendTo($project, $targets, $message, $notifiable, $notification);
    }
}
