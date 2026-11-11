<?php

namespace RSE\Delivra\Fcm\Testing;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PHPUnit\Framework\Assert as PHPUnit;
use RSE\Delivra\Fcm\FcmManager;
use RSE\Delivra\Fcm\FcmReport;
use RSE\Delivra\Fcm\FcmResult;
use RSE\Delivra\Fcm\Messages\FcmMessage;
use RSE\Delivra\Fcm\Project;
use RSE\Delivra\Fcm\Target;

class FcmFake extends FcmManager
{
    /** @var list<SentPush> */
    protected array $sent = [];

    /** @var list<array{action: string, topic: string, token: string, project: string}> */
    protected array $topicChanges = [];

    /** @var array<string, string> token => FCM error code */
    protected array $failures = [];

    protected ?FcmMessage $sending = null;

    protected function resolve(string $name): Project
    {
        return new Project($this, $name, $this->config['projects'][$name] ?? [
            'project_id'   => 'fake-project',
            'client_email' => 'fake@fake-project.iam.gserviceaccount.com',
            'private_key'  => 'fake-key',
        ]);
    }

    /**
     * Make FCM answer with an error for these tokens, e.g. UNREGISTERED.
     *
     * @param  string|iterable<string>  $tokens
     */
    public function failFor(string|iterable $tokens, string $error = 'UNREGISTERED'): static
    {
        foreach (Target::tokens($tokens) as $token) {
            $this->failures[$token] = $error;
        }

        return $this;
    }

    public function deliver(Project $project, array $targets, array $payload, bool $validateOnly = false): array
    {
        return array_map(function (Target $target) use ($project, $payload) {
            $this->sent[] = new SentPush($project->name, $target, $this->sending, $target->toPayload() + $payload);

            $error = $target->isToken() ? ($this->failures[$target->value] ?? null) : null;

            if ($error !== null) {
                return new FcmResult($target, match ($error) {
                    'UNREGISTERED'       => FcmResult::INVALID_TOKEN,
                    'SENDER_ID_MISMATCH' => FcmResult::WRONG_PROJECT,
                    default              => FcmResult::FAILED,
                }, error: $error, errorMessage: 'Faked failure', httpStatus: 400);
            }

            return FcmResult::sent($target, "projects/{$project->name}/messages/" . Str::random(16));
        }, $targets);
    }

    public function sendTo(Project $project, array $targets, FcmMessage $message, mixed $notifiable = null, mixed $notification = null): FcmReport
    {
        // deliver() only gets the payload, keep the message for assertions
        $this->sending = $message;

        return parent::sendTo($project, $targets, $message, $notifiable, $notification);
    }

    public function manageTopic(Project $project, string $action, string $topic, array $tokens): FcmReport
    {
        $report = new FcmReport;

        foreach ($tokens as $token) {
            $this->topicChanges[] = ['action' => $action, 'topic' => $topic, 'token' => $token, 'project' => $project->name];

            $report->add(FcmResult::sent(Target::token($token)));
        }

        return $report;
    }

    /**
     * @param  (Closure(SentPush): bool)|null  $callback
     * @return Collection<int, SentPush>
     */
    public function sent(?Closure $callback = null): Collection
    {
        return collect($this->sent)->filter($callback ?? fn () => true)->values();
    }

    /**
     * @param  (Closure(FcmMessage, SentPush): bool)|null  $callback
     */
    public function assertSent(?Closure $callback = null): static
    {
        PHPUnit::assertTrue($this->matching($callback)->isNotEmpty(), 'The expected FCM message was not sent.');

        return $this;
    }

    /**
     * @param  (Closure(FcmMessage, SentPush): bool)|null  $callback
     */
    public function assertSentTo(string $token, ?Closure $callback = null): static
    {
        PHPUnit::assertTrue(
            $this->matching($callback)->contains(fn (SentPush $push) => $push->token() === $token),
            'No matching FCM message was sent to the token.'
        );

        return $this;
    }

    /**
     * @param  (Closure(FcmMessage, SentPush): bool)|null  $callback
     */
    public function assertSentToTopic(string $topic, ?Closure $callback = null): static
    {
        PHPUnit::assertTrue(
            $this->matching($callback)->contains(fn (SentPush $push) => $push->topic() === Target::normalizeTopic($topic)),
            "No matching FCM message was sent to topic [{$topic}]."
        );

        return $this;
    }

    /**
     * @param  (Closure(FcmMessage, SentPush): bool)|null  $callback
     */
    public function assertNotSent(?Closure $callback = null): static
    {
        PHPUnit::assertTrue($this->matching($callback)->isEmpty(), 'An unexpected FCM message was sent.');

        return $this;
    }

    public function assertNothingSent(): static
    {
        PHPUnit::assertEmpty($this->sent, count($this->sent) . ' unexpected FCM messages were sent.');

        return $this;
    }

    public function assertSentCount(int $count): static
    {
        PHPUnit::assertCount($count, $this->sent, 'Unexpected number of FCM messages sent.');

        return $this;
    }

    public function assertSubscribed(string $topic, ?string $token = null): static
    {
        PHPUnit::assertTrue($this->hasTopicChange('subscribe', $topic, $token), "No token was subscribed to FCM topic [{$topic}].");

        return $this;
    }

    public function assertUnsubscribed(string $topic, ?string $token = null): static
    {
        PHPUnit::assertTrue($this->hasTopicChange('unsubscribe', $topic, $token), "No token was unsubscribed from FCM topic [{$topic}].");

        return $this;
    }

    /**
     * @return Collection<int, array{action: string, topic: string, token: string, project: string}>
     */
    public function topicChanges(): Collection
    {
        return collect($this->topicChanges);
    }

    protected function hasTopicChange(string $action, string $topic, ?string $token): bool
    {
        return $this->topicChanges()->contains(fn (array $change) => $change['action'] === $action
            && $change['topic'] === Target::normalizeTopic($topic)
            && ($token === null || $change['token'] === $token));
    }

    /**
     * @return Collection<int, SentPush>
     */
    protected function matching(?Closure $callback): Collection
    {
        return $this->sent(fn (SentPush $push) => $callback === null || $callback($push->message, $push));
    }
}
