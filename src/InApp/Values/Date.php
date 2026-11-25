<?php

namespace RSE\Delivra\InApp\Values;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * A date is stored as Y-m-d, so it never moves a day between timezones.
 * A datetime is stored in UTC and shown in the reader's timezone.
 */
class Date extends Value
{
    public function __construct(
        public readonly ?string $value,
        public readonly bool $withTime = false,
        public readonly ?string $format = null,
    ) {}

    public static function date(DateTimeInterface|string|null $value, ?string $format = null): static
    {
        return new static($value === null ? null : Carbon::parse($value)->format('Y-m-d'), false, $format);
    }

    public static function datetime(DateTimeInterface|string|null $value, ?string $format = null): static
    {
        return new static($value === null ? null : Carbon::parse($value)->utc()->toIso8601String(), true, $format);
    }

    /**
     * A missing date renders as an empty string.
     */
    public function render(RenderContext $context): string
    {
        if ($this->value === null) {
            return '';
        }

        $date = $this->withTime
            ? Carbon::parse($this->value)->setTimezone($context->timezone)
            : Carbon::createFromFormat('Y-m-d', $this->value, $context->timezone)->startOfDay();

        return $date->locale($context->locale)->translatedFormat($this->format ?? $this->defaultFormat());
    }

    public function toArray(): array
    {
        return array_filter([
            't'    => 'date',
            'v'    => $this->value,
            'time' => $this->withTime ?: null,
            'f'    => $this->format,
        ], fn ($value) => $value !== null);
    }

    public static function fromArray(array $payload): static
    {
        return new static($payload['v'] ?? null, (bool) ($payload['time'] ?? false), $payload['f'] ?? null);
    }

    protected function defaultFormat(): string
    {
        return $this->withTime
            ? config('delivra.in_app.datetime_format', 'j F Y, g:i a')
            : config('delivra.in_app.date_format', 'j F Y');
    }
}
