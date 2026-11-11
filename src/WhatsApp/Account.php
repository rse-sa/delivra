<?php

namespace RSE\Delivra\WhatsApp;

use finfo;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RSE\Delivra\Exceptions\WhatsApp\ApiErrorException;
use RSE\Delivra\Exceptions\WhatsApp\WhatsAppException;
use RSE\Delivra\WhatsApp\Messages\Message;
use RSE\Delivra\WhatsApp\Templates\Template;
use RSE\Delivra\WhatsApp\Templates\TemplateRegistry;
use SplFileInfo;

/**
 * One WhatsApp Business Account with its phone numbers.
 */
class Account
{
    protected ?TemplateRegistry $templates = null;

    /**
     * @param  string|null  $number  number name from config or a raw phone_number_id
     */
    public function __construct(
        protected WhatsAppManager $manager,
        public readonly string $name,
        protected array $config,
        protected ?string $number = null,
    ) {}

    /**
     * Same account, sending from another number.
     */
    public function from(string $number): static
    {
        return new static($this->manager, $this->name, $this->config, $number);
    }

    public function hasExplicitNumber(): bool
    {
        return $this->number !== null;
    }

    public function phoneNumberId(): string
    {
        $numbers = $this->numbers();
        $number  = $this->number ?? $this->config['default_number'] ?? array_key_first($numbers);

        $id = $numbers[$number] ?? (in_array($number, $numbers, true) ? $number : null);

        if (! $id) {
            throw new WhatsAppException("WhatsApp number [{$number}] is not configured for account [{$this->name}].");
        }

        return $id;
    }

    /** @return array<string, string> name => phone_number_id */
    public function numbers(): array
    {
        return array_filter($this->config['numbers'] ?? []);
    }

    public function ownsPhoneNumberId(string $phoneNumberId): bool
    {
        return in_array($phoneNumberId, $this->numbers(), true);
    }

    public function businessAccountId(): ?string
    {
        return $this->config['business_account_id'] ?? null;
    }

    public function appSecret(): ?string
    {
        return $this->config['app_secret'] ?? null;
    }

    public function http(): PendingRequest
    {
        $token = $this->config['token'] ?? null;

        if (! $token) {
            throw new WhatsAppException("WhatsApp token is missing for account [{$this->name}].");
        }

        $baseUrl = rtrim($this->manager->getConfig('graph_url', 'https://graph.facebook.com'), '/')
            . '/' . $this->manager->getConfig('graph_version', 'v24.0');

        // retry only on network errors, a 4xx retry would send the message twice
        return Http::baseUrl($baseUrl)
            ->withToken($token)
            ->acceptJson()
            ->timeout(config('delivra.http.timeout', 10))
            ->connectTimeout(config('delivra.http.connect_timeout', 5))
            ->retry(
                config('delivra.http.retries', 3),
                config('delivra.http.retry_delay', 100),
                fn ($exception) => $exception instanceof ConnectionException,
                throw: false,
            );
    }

    public function to(string|array $recipients): PendingWhatsApp
    {
        return new PendingWhatsApp($this->manager, $this, (array) $recipients);
    }

    public function send(Message|Template $message): WhatsAppResponse|WhatsAppBatchResponse|null
    {
        return (new PendingWhatsApp($this->manager, $this))->send($message);
    }

    /**
     * Mark an inbound message as read, optionally showing the typing indicator.
     */
    public function markAsRead(string $messageId, bool $typing = false): bool
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'status'            => 'read',
            'message_id'        => $messageId,
        ];

        if ($typing) {
            $payload['typing_indicator'] = ['type' => 'text'];
        }

        $response = $this->http()->post($this->phoneNumberId() . '/messages', $payload);

        if ($response->failed()) {
            throw ApiErrorException::fromResponse($response);
        }

        return (bool) $response->json('success');
    }

    /**
     * Upload a local file to Meta and return its media id. Meta keeps it for 30 days.
     */
    public function upload(string|SplFileInfo $file, ?string $filename = null): string
    {
        $path = $file instanceof SplFileInfo ? $file->getPathname() : $file;

        if (! is_file($path)) {
            throw new WhatsAppException("WhatsApp media file [{$path}] does not exist.");
        }

        $filename ??= $file instanceof UploadedFile ? $file->getClientOriginalName() : basename($path);

        return $this->uploadContents(file_get_contents($path), $filename, File::mimeType($path) ?: null);
    }

    /**
     * Upload raw bytes, e.g. from a Storage disk, and return the media id.
     */
    public function uploadContents(string $contents, string $filename, ?string $mimeType = null): string
    {
        $mimeType ??= (new finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: 'application/octet-stream';

        return $this->manager->uploadMedia($this, $contents, $filename, $mimeType);
    }

    public function mediaUrl(string $mediaId): string
    {
        $response = $this->http()->get($mediaId);

        if ($response->failed()) {
            throw ApiErrorException::fromResponse($response);
        }

        return $response->json('url');
    }

    public function downloadMedia(string $mediaId): Media
    {
        $url      = $this->mediaUrl($mediaId);
        $response = $this->http()->withHeaders(['Accept' => '*/*'])->get($url);

        if ($response->failed()) {
            throw ApiErrorException::fromResponse($response);
        }

        return new Media($mediaId, $response->header('Content-Type') ?: 'application/octet-stream', $response->body());
    }

    public function templates(): TemplateRegistry
    {
        return $this->templates ??= new TemplateRegistry($this, (int) $this->manager->getConfig('templates_cache_ttl', 3600));
    }

    public function manager(): WhatsAppManager
    {
        return $this->manager;
    }
}
