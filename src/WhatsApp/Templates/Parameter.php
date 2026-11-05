<?php

namespace RSE\Delivra\WhatsApp\Templates;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Support\Str;
use Stringable;

class Parameter
{
    public function __construct(public readonly string $type, public readonly array $value) {}

    public static function text(string|int|float|Stringable $text): static
    {
        return new static('text', ['text' => (string) $text]);
    }

    /**
     * @param  string|null  $fallback  shown on clients that can't localize, defaults to "1,250.00 SAR"
     */
    public static function currency(float|int $amount, string $code, ?string $fallback = null): static
    {
        return new static('currency', ['currency' => [
            'fallback_value' => $fallback ?? number_format($amount, 2) . " {$code}",
            'code'           => $code,
            'amount_1000'    => (int) round($amount * 1000),
        ]]);
    }

    public static function dateTime(DateTimeInterface|string $value, string $format = 'Y-m-d H:i'): static
    {
        return new static('date_time', ['date_time' => [
            'fallback_value' => $value instanceof DateTimeInterface ? $value->format($format) : $value,
        ]]);
    }

    /**
     * @param  string  $media  public link or uploaded media id
     */
    public static function image(string $media): static
    {
        return static::media('image', $media);
    }

    public static function video(string $media): static
    {
        return static::media('video', $media);
    }

    public static function document(string $media, ?string $filename = null): static
    {
        return static::media('document', $media, array_filter(['filename' => $filename]));
    }

    public static function location(float $latitude, float $longitude, ?string $name = null, ?string $address = null): static
    {
        return new static('location', ['location' => array_filter([
            'latitude'  => $latitude,
            'longitude' => $longitude,
            'name'      => $name,
            'address'   => $address,
        ], fn ($value) => $value !== null)]);
    }

    public static function media(string $type, string $media, array $extra = []): static
    {
        $source = Str::startsWith($media, ['http://', 'https://']) ? 'link' : 'id';

        return new static($type, [$type => [$source => $media] + $extra]);
    }

    /**
     * Wrap a plain value into a parameter.
     */
    public static function from(mixed $value): static
    {
        return match (true) {
            $value instanceof static            => $value,
            $value instanceof DateTimeInterface => static::dateTime($value),
            $value instanceof BackedEnum        => static::text($value->value),
            default                             => static::text($value ?? ''),
        };
    }

    public function isMedia(): bool
    {
        return in_array($this->type, ['image', 'video', 'document'], true);
    }

    public function toArray(?string $name = null): array
    {
        $parameter = ['type' => $this->type] + $this->value;

        if ($name !== null) {
            $parameter['parameter_name'] = $name;
        }

        return $parameter;
    }
}
