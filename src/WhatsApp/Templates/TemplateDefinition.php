<?php

namespace RSE\Delivra\WhatsApp\Templates;

/**
 * An approved (or pending) template as returned by the WABA message_templates API.
 */
class TemplateDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $language,
        public readonly string $status,
        public readonly ?string $category = null,
        public readonly ?string $id = null,
        public readonly ?string $parameterFormat = null,
        public readonly array $components = [],
    ) {}

    public static function fromArray(array $data): static
    {
        return new static(
            name: $data['name'],
            language: $data['language'],
            status: $data['status'] ?? 'UNKNOWN',
            category: $data['category'] ?? null,
            id: $data['id'] ?? null,
            parameterFormat: $data['parameter_format'] ?? null,
            components: $data['components'] ?? [],
        );
    }

    public function isApproved(): bool
    {
        return $this->status === 'APPROVED';
    }

    public function component(string $type): ?array
    {
        foreach ($this->components as $component) {
            if (strtoupper($component['type'] ?? '') === strtoupper($type)) {
                return $component;
            }
        }

        return null;
    }

    public function bodyText(): ?string
    {
        return $this->component('body')['text'] ?? null;
    }

    /**
     * TEXT, IMAGE, VIDEO, DOCUMENT, LOCATION or null when there is no header.
     */
    public function headerFormat(): ?string
    {
        return $this->component('header')['format'] ?? null;
    }

    public function headerNeedsParameter(): bool
    {
        $format = $this->headerFormat();

        if ($format === null) {
            return false;
        }

        return $format !== 'TEXT' || static::variablesIn($this->component('header')['text'] ?? '') !== [];
    }

    /**
     * Variable names in the body, in order: ["1", "2"] or ["name", "amount"].
     *
     * @return list<string>
     */
    public function bodyVariables(): array
    {
        return static::variablesIn($this->bodyText() ?? '');
    }

    public function usesNamedParameters(): bool
    {
        if ($this->parameterFormat !== null) {
            return strtoupper($this->parameterFormat) === 'NAMED';
        }

        $variables = $this->bodyVariables();

        return $variables !== [] && ! ctype_digit($variables[0]);
    }

    /**
     * @return list<array{index: int, type: string, text: ?string, url: ?string, needs_parameter: bool}>
     */
    public function buttons(): array
    {
        $buttons = $this->component('buttons')['buttons'] ?? [];

        return collect($buttons)->values()->map(fn (array $button, int $index) => [
            'index'           => $index,
            'type'            => strtoupper($button['type'] ?? ''),
            'text'            => $button['text'] ?? null,
            'url'             => $button['url'] ?? null,
            'needs_parameter' => match (strtoupper($button['type'] ?? '')) {
                'URL'       => str_contains($button['url'] ?? '', '{{'),
                'COPY_CODE' => true,
                default     => false,
            },
        ])->all();
    }

    /**
     * @return list<string>
     */
    public static function variablesIn(string $text): array
    {
        preg_match_all('/\{\{\s*(\w+)\s*\}\}/', $text, $matches);

        return array_values(array_unique($matches[1]));
    }

    public function toArray(): array
    {
        return [
            'name'             => $this->name,
            'language'         => $this->language,
            'status'           => $this->status,
            'category'         => $this->category,
            'id'               => $this->id,
            'parameter_format' => $this->parameterFormat,
            'components'       => $this->components,
        ];
    }
}
