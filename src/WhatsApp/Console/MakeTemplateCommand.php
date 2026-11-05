<?php

namespace RSE\Delivra\WhatsApp\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use RSE\Delivra\WhatsApp\Templates\TemplateDefinition;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

class MakeTemplateCommand extends GeneratorCommand
{
    protected $name = 'make:whatsapp-template';

    protected $description = 'Create a WhatsApp template class, filled from the Meta definition when found';

    protected $type = 'WhatsApp template';

    protected function getStub(): string
    {
        $published = $this->laravel->basePath('stubs/whatsapp-template.stub');

        return file_exists($published) ? $published : __DIR__ . '/../../../stubs/whatsapp-template.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\WhatsApp\Templates';
    }

    protected function buildClass($name): string
    {
        $template   = $this->option('template') ?: Str::snake(class_basename($name));
        $definition = $this->definition($template);

        return str_replace(
            ['{{ template }}', '{{ language }}', '{{ body }}', '{{ buttons }}', '{{ header }}'],
            [
                $template,
                $this->option('language') ?: $definition?->language ?: config('delivra.whatsapp.default_language', 'en_US'),
                $this->bodyStub($definition),
                $this->buttonsStub($definition),
                $this->headerStub($definition),
            ],
            parent::buildClass($name),
        );
    }

    protected function definition(string $template): ?TemplateDefinition
    {
        try {
            return whatsapp()->account($this->option('account'))->templates()->find($template, $this->option('language'));
        } catch (Throwable $e) {
            $this->components->warn("Could not read templates from Meta: {$e->getMessage()}");

            return null;
        }
    }

    protected function bodyStub(?TemplateDefinition $definition): string
    {
        $variables = $definition?->bodyVariables() ?? [];

        if ($variables === []) {
            return '';
        }

        return collect($variables)
            ->map(fn (string $variable) => $definition->usesNamedParameters() ? "'{$variable}' => ''," : "'', // {{{$variable}}}")
            ->map(fn (string $line) => "\n            {$line}")
            ->implode('') . "\n        ";
    }

    protected function buttonsStub(?TemplateDefinition $definition): string
    {
        $buttons = collect($definition?->buttons() ?? [])->where('needs_parameter', true);

        if ($buttons->isEmpty()) {
            return '';
        }

        return $buttons
            ->map(fn (array $button) => $button['type'] === 'COPY_CODE'
                ? "{$button['index']} => Button::copyCode(''),"
                : "{$button['index']} => Button::url(''), // {$button['url']}")
            ->map(fn (string $line) => "\n            {$line}")
            ->implode('') . "\n        ";
    }

    protected function headerStub(?TemplateDefinition $definition): string
    {
        if (! $definition?->headerNeedsParameter()) {
            return 'null';
        }

        return match ($definition->headerFormat()) {
            'IMAGE'    => "Parameter::image('')",
            'VIDEO'    => "Parameter::video('')",
            'DOCUMENT' => "Parameter::document('')",
            'LOCATION' => 'Parameter::location(0, 0)',
            default    => "''",
        };
    }

    protected function getOptions(): array
    {
        return [
            ['template', 't', InputOption::VALUE_REQUIRED, 'The Meta template name (defaults to the snake cased class name)'],
            ['language', 'l', InputOption::VALUE_REQUIRED, 'The template language'],
            ['account', 'a', InputOption::VALUE_REQUIRED, 'The account to read the definition from'],
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if it already exists'],
        ];
    }
}
