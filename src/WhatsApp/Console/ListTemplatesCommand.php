<?php

namespace RSE\Delivra\WhatsApp\Console;

use Illuminate\Console\Command;
use RSE\Delivra\WhatsApp\Templates\TemplateDefinition;

class ListTemplatesCommand extends Command
{
    protected $signature = 'delivra:whatsapp-templates
                            {--account= : Account name from config/delivra.php}
                            {--fresh : Skip the cache and fetch from Meta}';

    protected $description = 'List the WhatsApp templates of an account';

    public function handle(): int
    {
        $account   = whatsapp()->account($this->option('account'));
        $templates = $account->templates()->all(fresh: (bool) $this->option('fresh'));

        if ($templates->isEmpty()) {
            $this->components->info("No templates found for account [{$account->name}].");

            return self::SUCCESS;
        }

        $this->table(
            ['Name', 'Language', 'Status', 'Category', 'Header', 'Body variables', 'Buttons'],
            $templates->map(fn (TemplateDefinition $template) => [
                $template->name,
                $template->language,
                $template->status,
                $template->category,
                $template->headerFormat() ?? '-',
                implode(', ', $template->bodyVariables()) ?: '-',
                collect($template->buttons())->map(fn (array $button) => "{$button['index']}:{$button['type']}" . ($button['needs_parameter'] ? '*' : ''))->implode(', ') ?: '-',
            ])->all(),
        );

        $this->components->twoColumnDetail('* button needs a parameter');

        return self::SUCCESS;
    }
}
