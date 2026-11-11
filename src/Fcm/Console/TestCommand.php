<?php

namespace RSE\Delivra\Fcm\Console;

use Illuminate\Console\Command;
use RSE\Delivra\Fcm\Messages\FcmMessage;
use RuntimeException;
use Throwable;

class TestCommand extends Command
{
    protected $signature = 'delivra:fcm-test
                            {token : Device token to send the test push to}
                            {--project= : Project name from config/delivra.php}
                            {--validate : Ask FCM to check the message without delivering it}';

    protected $description = 'Check FCM credentials, the OAuth token exchange and one send';

    public function handle(): int
    {
        $project = fcm()->project($this->option('project'));

        $steps = [
            'Credentials'    => fn () => $project->projectId(),
            'OAuth token'    => fn () => $project->accessToken(fresh: true) ? 'ok' : 'empty',
            'Send test push' => function () use ($project) {
                $result = $project->to($this->argument('token'))
                    ->send(FcmMessage::make('Delivra test', 'FCM works for project ' . $project->name)->validateOnly((bool) $this->option('validate')))
                    ->all()[0];

                if ($result->failed()) {
                    throw new RuntimeException("{$result->status} [{$result->error}]: {$result->errorMessage}");
                }

                return $result->messageId;
            },
        ];

        foreach ($steps as $label => $step) {
            try {
                $detail = $step();
            } catch (Throwable $e) {
                $this->components->twoColumnDetail($label, '<fg=red;options=bold>FAIL</>');
                $this->components->error($e->getMessage());

                return self::FAILURE;
            }

            $this->components->twoColumnDetail("{$label} <fg=gray>{$detail}</>", '<fg=green;options=bold>OK</>');
        }

        return self::SUCCESS;
    }
}
