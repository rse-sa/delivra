<?php

namespace RSE\Delivra\Exceptions\WhatsApp;

use Illuminate\Http\Client\Response;

class ApiErrorException extends WhatsAppException
{
    /**
     * Meta error codes mapped to a more specific exception.
     *
     * @var array<int, class-string<ApiErrorException>>
     */
    public static array $map = [
        190    => AuthenticationException::class,
        4      => RateLimitException::class,
        80007  => RateLimitException::class,
        130429 => RateLimitException::class,
        131048 => RateLimitException::class,
        131056 => RateLimitException::class,
        131026 => RecipientUnavailableException::class,
        131047 => ReEngagementRequiredException::class,
        132000 => TemplateParameterException::class,
        132012 => TemplateParameterException::class,
        132001 => TemplateNotFoundException::class,
    ];

    public function __construct(
        string $message,
        public readonly int $errorCode = 0,
        public readonly ?int $errorSubcode = null,
        public readonly ?string $errorType = null,
        public readonly ?string $details = null,
        public readonly ?string $traceId = null,
        public readonly int $status = 0,
        public readonly ?array $response = null,
    ) {
        parent::__construct("WhatsApp API error [{$errorCode}]: {$message}" . ($details ? " ({$details})" : ''));
    }

    public static function fromResponse(Response $response): static
    {
        $error = $response->json('error') ?? [];
        $code  = (int) ($error['code'] ?? 0);

        $class = static::$map[$code] ?? static::class;

        return new $class(
            message: $error['message'] ?? ($response->reason() ?: 'Unknown error'),
            errorCode: $code,
            errorSubcode: isset($error['error_subcode']) ? (int) $error['error_subcode'] : null,
            errorType: $error['type'] ?? null,
            details: $error['error_data']['details'] ?? null,
            traceId: $error['fbtrace_id'] ?? null,
            status: $response->status(),
            response: $response->json(),
        );
    }

    public function getErrorCode(): int
    {
        return $this->errorCode;
    }
}
