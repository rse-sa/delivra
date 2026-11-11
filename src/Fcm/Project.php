<?php

namespace RSE\Delivra\Fcm;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RSE\Delivra\Exceptions\Fcm\FcmConfigurationException;
use RSE\Delivra\Fcm\Messages\FcmMessage;

/**
 * One Firebase project with its service account.
 */
class Project
{
    protected const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    protected ?array $credentials = null;

    public function __construct(
        protected FcmManager $manager,
        public readonly string $name,
        protected array $config,
    ) {}

    public function projectId(): string
    {
        return $this->credentials()['project_id'];
    }

    /**
     * @return array{project_id: string, client_email: string, private_key: string}
     */
    public function credentials(): array
    {
        if ($this->credentials !== null) {
            return $this->credentials;
        }

        $credentials = $this->config;

        if (! empty($this->config['credentials'])) {
            $credentials = array_merge($credentials, $this->readCredentials($this->config['credentials']));
        }

        foreach (['project_id', 'client_email', 'private_key'] as $key) {
            if (empty($credentials[$key])) {
                throw new FcmConfigurationException("FCM credential [{$key}] is missing for project [{$this->name}].");
            }
        }

        return $this->credentials = [
            'project_id'   => $credentials['project_id'],
            'client_email' => $credentials['client_email'],
            // keys from .env often hold literal \n
            'private_key' => str_replace('\n', "\n", $credentials['private_key']),
        ];
    }

    protected function readCredentials(string|array $credentials): array
    {
        if (is_array($credentials)) {
            return $credentials;
        }

        if (! str_starts_with(ltrim($credentials), '{')) {
            $path = is_file($credentials) ? $credentials : base_path($credentials);

            if (! is_file($path)) {
                throw new FcmConfigurationException("FCM credentials file for project [{$this->name}] does not exist.");
            }

            $credentials = file_get_contents($path);
        }

        $decoded = json_decode($credentials, true);

        if (! is_array($decoded)) {
            throw new FcmConfigurationException("FCM credentials for project [{$this->name}] are not valid JSON.");
        }

        return $decoded;
    }

    /**
     * OAuth access token, cached until shortly before it expires.
     */
    public function accessToken(bool $fresh = false): string
    {
        $key = $this->cacheKey();

        if ($fresh) {
            Cache::forget($key);
        }

        if ($token = Cache::get($key)) {
            return $token;
        }

        [$token, $expiresIn] = $this->fetchAccessToken();

        Cache::put($key, $token, max(60, $expiresIn - 300));

        return $token;
    }

    public function forgetAccessToken(): void
    {
        Cache::forget($this->cacheKey());
    }

    protected function cacheKey(): string
    {
        return 'delivra:fcm:token:' . sha1($this->name . '|' . $this->credentials()['client_email']);
    }

    /**
     * @return array{0: string, 1: int}
     */
    protected function fetchAccessToken(): array
    {
        $response = Http::asForm()
            ->acceptJson()
            ->timeout(config('delivra.http.timeout', 10))
            ->connectTimeout(config('delivra.http.connect_timeout', 5))
            ->post($this->manager->getConfig('oauth_url', 'https://oauth2.googleapis.com/token'), [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $this->assertion(),
            ]);

        if ($response->failed() || ! $response->json('access_token')) {
            $reason = $response->json('error_description') ?? $response->json('error') ?? $response->reason();

            throw new FcmConfigurationException("FCM token exchange failed for project [{$this->name}]: {$reason}", $response->status());
        }

        return [$response->json('access_token'), (int) ($response->json('expires_in') ?? 3600)];
    }

    /**
     * Signed RS256 JWT for the service account.
     */
    protected function assertion(): string
    {
        $credentials = $this->credentials();
        $now         = time();

        $segments = [
            $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            $this->base64Url(json_encode([
                'iss'   => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud'   => $this->manager->getConfig('oauth_url', 'https://oauth2.googleapis.com/token'),
                'iat'   => $now,
                'exp'   => $now + 3600,
            ])),
        ];

        $key = openssl_pkey_get_private($credentials['private_key']);

        if ($key === false || ! openssl_sign(implode('.', $segments), $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new FcmConfigurationException("FCM private key for project [{$this->name}] cannot be read.");
        }

        $segments[] = $this->base64Url($signature);

        return implode('.', $segments);
    }

    protected function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * Apply the FCM base URL, token and timeouts. Works on pool requests too.
     */
    public function configure(PendingRequest $request, ?string $baseUrl = null): PendingRequest
    {
        return $request
            ->baseUrl($baseUrl ?? $this->messagesUrl())
            ->withToken($this->accessToken())
            ->acceptJson()
            ->timeout(config('delivra.http.timeout', 10))
            ->connectTimeout(config('delivra.http.connect_timeout', 5));
    }

    /**
     * Client for FCM endpoints Delivra does not cover.
     */
    public function http(): PendingRequest
    {
        return $this->configure(Http::withOptions([]), rtrim($this->manager->getConfig('url', 'https://fcm.googleapis.com'), '/'));
    }

    public function messagesUrl(): string
    {
        return rtrim($this->manager->getConfig('url', 'https://fcm.googleapis.com'), '/') . "/v1/projects/{$this->projectId()}";
    }

    /**
     * @param  string|iterable<string>  $tokens
     */
    public function to(string|iterable $tokens): PendingFcm
    {
        return (new PendingFcm($this->manager, $this))->to($tokens);
    }

    public function topic(string $topic): PendingFcm
    {
        return (new PendingFcm($this->manager, $this))->topic($topic);
    }

    public function condition(string $condition): PendingFcm
    {
        return (new PendingFcm($this->manager, $this))->condition($condition);
    }

    /**
     * Send a message that carries its own topic or condition.
     */
    public function send(FcmMessage $message): FcmReport
    {
        return (new PendingFcm($this->manager, $this))->send($message);
    }

    /**
     * @param  string|iterable<string>  $tokens
     */
    public function subscribe(string $topic, string|iterable $tokens): FcmReport
    {
        return $this->manager->manageTopic($this, 'subscribe', Target::normalizeTopic($topic), Target::tokens($tokens));
    }

    /**
     * @param  string|iterable<string>  $tokens
     */
    public function unsubscribe(string $topic, string|iterable $tokens): FcmReport
    {
        return $this->manager->manageTopic($this, 'unsubscribe', Target::normalizeTopic($topic), Target::tokens($tokens));
    }

    public function manager(): FcmManager
    {
        return $this->manager;
    }
}
