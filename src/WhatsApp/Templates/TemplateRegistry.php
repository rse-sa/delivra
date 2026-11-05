<?php

namespace RSE\Delivra\WhatsApp\Templates;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use RSE\Delivra\Exceptions\WhatsApp\ApiErrorException;
use RSE\Delivra\Exceptions\WhatsApp\TemplateValidationException;
use RSE\Delivra\Exceptions\WhatsApp\WhatsAppException;
use RSE\Delivra\WhatsApp\Account;
use RSE\Delivra\WhatsApp\Messages\TemplateMessage;

/**
 * Reads the account's templates from Meta, cached.
 */
class TemplateRegistry
{
    public function __construct(protected Account $account, protected int $ttl = 3600) {}

    /**
     * @return Collection<int, TemplateDefinition>
     */
    public function all(bool $fresh = false): Collection
    {
        if ($fresh) {
            Cache::forget($this->cacheKey());
        }

        $templates = Cache::remember($this->cacheKey(), $this->ttl, fn () => $this->fetch());

        return collect($templates)->map(fn (array $template) => TemplateDefinition::fromArray($template));
    }

    /**
     * @return Collection<int, TemplateDefinition>
     */
    public function refresh(): Collection
    {
        return $this->all(fresh: true);
    }

    public function find(string $name, ?string $language = null): ?TemplateDefinition
    {
        return $this->all()->first(
            fn (TemplateDefinition $template) => $template->name === $name && ($language === null || $template->language === $language)
        );
    }

    /**
     * Check a message against the Meta definition. Throws with all problems found.
     */
    public function validate(TemplateMessage $message): void
    {
        $definition = $this->find($message->getName(), $message->getLanguage());

        if ($definition === null) {
            throw new TemplateValidationException($message->getName(), [
                "Template not found for language [{$message->getLanguage()}] in account [{$this->account->name}].",
            ]);
        }

        $errors = [];

        if (! $definition->isApproved()) {
            $errors[] = "Template status is [{$definition->status}].";
        }

        if ($definition->headerNeedsParameter() && $message->getHeader() === null) {
            $errors[] = "Header [{$definition->headerFormat()}] needs a parameter.";
        }

        $errors = [...$errors, ...$this->bodyErrors($definition, $message)];

        foreach ($definition->buttons() as $button) {
            if ($button['needs_parameter'] && ! isset($message->getButtons()[$button['index']])) {
                $errors[] = "Button {$button['index']} [{$button['type']}] needs a parameter.";
            }
        }

        if ($errors !== []) {
            throw new TemplateValidationException($message->getName(), $errors);
        }
    }

    /**
     * @return list<string>
     */
    protected function bodyErrors(TemplateDefinition $definition, TemplateMessage $message): array
    {
        $expected = $definition->bodyVariables();
        $given    = array_map('strval', array_keys($message->getBody()));

        if (! $definition->usesNamedParameters()) {
            return count($given) === count($expected)
                ? []
                : ['Body needs ' . count($expected) . ' parameters, ' . count($given) . ' given.'];
        }

        $errors = [];

        if ($missing = array_diff($expected, $given)) {
            $errors[] = 'Body is missing parameters: ' . implode(', ', $missing) . '.';
        }

        if ($unknown = array_diff($given, $expected)) {
            $errors[] = 'Body has unknown parameters: ' . implode(', ', $unknown) . '.';
        }

        return $errors;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function fetch(): array
    {
        $businessAccountId = $this->account->businessAccountId();

        if (! $businessAccountId) {
            throw new WhatsAppException("WhatsApp business_account_id is missing for account [{$this->account->name}].");
        }

        $templates = [];
        $url       = "{$businessAccountId}/message_templates";
        $query     = ['fields' => 'id,name,language,status,category,parameter_format,components', 'limit' => 100];

        while ($url) {
            $response = $this->account->http()->get($url, $query);

            if ($response->failed()) {
                throw ApiErrorException::fromResponse($response);
            }

            $templates = [...$templates, ...($response->json('data') ?? [])];

            // the next link already has the query string
            $url   = $response->json('paging.next');
            $query = [];
        }

        return $templates;
    }

    protected function cacheKey(): string
    {
        return "delivra:whatsapp:{$this->account->name}:templates";
    }
}
