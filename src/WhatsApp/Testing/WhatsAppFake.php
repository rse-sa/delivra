<?php

namespace RSE\Delivra\WhatsApp\Testing;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PHPUnit\Framework\Assert as PHPUnit;
use RSE\Delivra\WhatsApp\Account;
use RSE\Delivra\WhatsApp\Messages\Message;
use RSE\Delivra\WhatsApp\WhatsAppManager;
use RSE\Delivra\WhatsApp\WhatsAppResponse;

class WhatsAppFake extends WhatsAppManager
{
    /** @var list<SentMessage> */
    protected array $sent = [];

    /** @var list<array{id: string, filename: string, mimeType: string, contents: string, account: string}> */
    protected array $uploads = [];

    public function __construct(array $config = [])
    {
        // never call Meta to validate templates in tests
        parent::__construct(['validate_templates' => false] + $config);
    }

    protected function resolve(string $name): Account
    {
        return new Account($this, $name, $this->config['accounts'][$name] ?? [
            'token'   => 'fake-token',
            'numbers' => ['main' => 'fake-phone-number-id'],
        ]);
    }

    public function deliver(Account $account, array $payload, Message $message): WhatsAppResponse
    {
        $this->sent[] = new SentMessage($account->name, $account->phoneNumberId(), $payload['to'], $message, $payload);

        return new WhatsAppResponse(
            account: $account->name,
            phoneNumberId: $account->phoneNumberId(),
            recipient: $payload['to'],
            messageId: 'wamid.' . Str::random(24),
            waId: $payload['to'],
            messageStatus: 'accepted',
        );
    }

    public function uploadMedia(Account $account, string $contents, string $filename, string $mimeType): string
    {
        $id = 'fake-media-' . (count($this->uploads) + 1);

        $this->uploads[] = compact('id', 'filename', 'mimeType', 'contents') + ['account' => $account->name];

        return $id;
    }

    /**
     * @return Collection<int, array{id: string, filename: string, mimeType: string, contents: string, account: string}>
     */
    public function uploads(): Collection
    {
        return collect($this->uploads);
    }

    /**
     * @param  string|Closure(array): bool|null  $filename  file name or a callback that gets the upload
     */
    public function assertUploaded(string|Closure|null $filename = null): static
    {
        $filter = is_string($filename) ? fn (array $upload) => $upload['filename'] === $filename : $filename;

        PHPUnit::assertTrue(
            $this->uploads()->filter($filter ?? fn () => true)->isNotEmpty(),
            $filename === null ? 'No WhatsApp media was uploaded.' : 'The expected WhatsApp media was not uploaded.'
        );

        return $this;
    }

    public function assertNothingUploaded(): static
    {
        PHPUnit::assertEmpty($this->uploads, count($this->uploads) . ' unexpected WhatsApp media files were uploaded.');

        return $this;
    }

    /**
     * @param  (Closure(SentMessage): bool)|null  $callback
     * @return Collection<int, SentMessage>
     */
    public function sent(?Closure $callback = null): Collection
    {
        return collect($this->sent)->filter($callback ?? fn () => true)->values();
    }

    /**
     * @param  string|Closure(SentMessage): bool  $message  message/template class, template name, or a callback
     */
    public function assertSent(string|Closure $message, ?Closure $callback = null): static
    {
        PHPUnit::assertTrue(
            $this->matching($message, $callback)->isNotEmpty(),
            'The expected WhatsApp message was not sent.'
        );

        return $this;
    }

    public function assertSentTimes(string|Closure $message, int $times): static
    {
        $count = $this->matching($message)->count();

        PHPUnit::assertSame($times, $count, "The expected WhatsApp message was sent {$count} times instead of {$times} times.");

        return $this;
    }

    public function assertSentTo(string $recipient, string|Closure|null $message = null): static
    {
        $recipient = $this->formatNumber($recipient);

        PHPUnit::assertTrue(
            $this->matching($message ?? fn () => true, fn ($source, SentMessage $sent) => $sent->to === $recipient)->isNotEmpty(),
            "No matching WhatsApp message was sent to [{$recipient}]."
        );

        return $this;
    }

    /**
     * @param  string  $template  Meta name, config alias or Template class
     */
    public function assertTemplateSent(string $template, ?Closure $callback = null): static
    {
        PHPUnit::assertTrue(
            $this->matching(fn (SentMessage $sent) => $sent->isTemplate($template), $callback)->isNotEmpty(),
            "The WhatsApp template [{$template}] was not sent."
        );

        return $this;
    }

    public function assertNotSent(string|Closure $message, ?Closure $callback = null): static
    {
        PHPUnit::assertTrue(
            $this->matching($message, $callback)->isEmpty(),
            'An unexpected WhatsApp message was sent.'
        );

        return $this;
    }

    public function assertNothingSent(): static
    {
        PHPUnit::assertEmpty($this->sent, count($this->sent) . ' unexpected WhatsApp messages were sent.');

        return $this;
    }

    public function assertSentCount(int $count): static
    {
        PHPUnit::assertCount($count, $this->sent, 'Unexpected number of WhatsApp messages sent.');

        return $this;
    }

    /**
     * @return Collection<int, SentMessage>
     */
    protected function matching(string|Closure $message, ?Closure $callback = null): Collection
    {
        $filter = $message instanceof Closure
            ? $message
            : fn (SentMessage $sent) => $sent->source() instanceof $message || $sent->isTemplate($message);

        // callbacks get the source (Template class or message) and the record
        return $this->sent($filter)->filter(
            fn (SentMessage $sent) => $callback === null || $callback($sent->source(), $sent)
        );
    }
}
