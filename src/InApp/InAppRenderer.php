<?php

namespace RSE\Delivra\InApp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use InvalidArgumentException;
use RSE\Delivra\Exceptions\InApp\InvalidPayloadException;
use RSE\Delivra\InApp\Messages\InAppMessage;
use RSE\Delivra\InApp\Models\InAppNotification;
use RSE\Delivra\InApp\Values\RenderContext;
use RSE\Delivra\InApp\Values\Value;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * A stored row to the array a bell shows, in one locale and timezone.
 * It never changes the app locale: the locale goes straight to the translator and Carbon.
 */
class InAppRenderer
{
    public function render(InAppNotification $notification, ?string $locale = null, ?string $timezone = null): array
    {
        $payload = $notification->payload ?? [];

        if (($payload['v'] ?? null) !== InAppMessage::VERSION) {
            throw new InvalidPayloadException("In-app notification [{$notification->getKey()}] has an unknown payload version.");
        }

        $context = new RenderContext(
            $locale ?? app()->getLocale(),
            $timezone ?? $this->timezoneFor($notification),
        );

        $rendered = [
            'id'          => $notification->getKey(),
            'type'        => $notification->type,
            'category'    => $notification->category,
            'title'       => $this->text($payload['title'] ?? null, $context),
            'body'        => $this->text($payload['body'] ?? null, $context),
            'icon'        => $payload['icon'] ?? null,
            'color'       => $payload['color'] ?? null,
            'url'         => $this->url($payload['link'] ?? null),
            'buttons'     => array_map(fn (array $button) => $this->button($button, $context), $payload['buttons'] ?? []),
            'data'        => (array) ($payload['data'] ?? []),
            'subject'     => $this->morph($notification, 'subject'),
            'causer'      => $this->morph($notification, 'causer'),
            'read_at'     => $notification->read_at?->toIso8601String(),
            'archived_at' => $notification->archived_at?->toIso8601String(),
            'created_at'  => $notification->created_at?->toIso8601String(),
        ];

        return InAppManager::applyRenderHooks($notification, $rendered, $context->locale);
    }

    /**
     * render() gives arrays for PHP views. JSON needs {} for empty data and params, not [].
     */
    public static function forJson(array $rendered): array
    {
        $rendered['data'] = (object) ($rendered['data'] ?? []);

        $rendered['buttons'] = array_map(
            fn (array $button) => ['params' => (object) ($button['params'] ?? [])] + $button,
            $rendered['buttons'] ?? [],
        );

        return $rendered;
    }

    protected function button(array $button, RenderContext $context): array
    {
        return [
            'key'       => (string) ($button['key'] ?? ''),
            'label'     => $this->text($button['label'] ?? null, $context),
            'url'       => $this->url($button['link'] ?? null),
            'method'    => $button['method'] ?? 'GET',
            'color'     => $button['color'] ?? null,
            'icon'      => $button['icon'] ?? null,
            'params'    => (array) ($button['params'] ?? []),
            'mark_read' => (bool) ($button['mark_read'] ?? false),
        ];
    }

    protected function text(?array $value, RenderContext $context): ?string
    {
        return $value === null ? null : Value::hydrate($value)->render($context);
    }

    /**
     * A removed route or a missing param gives null, so one old row cannot break the bell.
     */
    protected function url(?array $link): ?string
    {
        if (isset($link['url'])) {
            return $link['url'];
        }

        if (! isset($link['route'])) {
            return null;
        }

        try {
            return route($link['route'], (array) ($link['params'] ?? []));
        } catch (RouteNotFoundException|UrlGenerationException|InvalidArgumentException) {
            return null;
        }
    }

    protected function morph(InAppNotification $notification, string $name): ?array
    {
        $type = $notification->getAttribute("{$name}_type");

        return $type === null ? null : ['type' => $type, 'id' => $notification->getAttribute("{$name}_id")];
    }

    /**
     * Loads the receiver only when a timezone resolver needs it. Lists pass $timezone instead.
     */
    protected function timezoneFor(InAppNotification $notification): string
    {
        $notifiable = InAppManager::hasTimezoneResolver() ? $notification->notifiable : null;

        return InAppManager::resolveTimezone($notifiable instanceof Model ? $notifiable : null);
    }
}
