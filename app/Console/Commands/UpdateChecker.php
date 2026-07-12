<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class UpdateChecker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'p:update:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check For Updates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $currentVersion = config('app.version');

        if ($currentVersion === 'canary') {
            $this->info('You are using the development (canary) version. No update check available.');

            return Command::SUCCESS;
        }

        try {
            $response = Http::get('https://reviactyl.app/api/v26/get-version');
            if ($response->failed()) {
                $this->error('Failed to check for updates.');

                return Command::FAILURE;
            }

            $data = $response->json();
            $latestVersion = $data['panel'] ?? null;

            if (! $latestVersion) {
                $this->error('Server sent invalid response. Reach out to Reviactyl support team.');

                return Command::FAILURE;
            }

            $this->line("Current version: <comment>{$currentVersion}</comment>");
            $this->line("Latest version: <comment>{$latestVersion}</comment>");

            if ($latestVersion === $currentVersion) {
                $this->info('You are up to date!');
            } elseif (version_compare($latestVersion, $currentVersion, '>')) {
                $this->warn('A new version is available.');
                $this->info('[TIP] Run <comment>php artisan p:upgrade</comment> to upgrade your installation.');
            } else {
                $this->info('You\'re running pre-release of Reviactyl Panel.');
            }
        } catch (\Exception $e) {
            $this->error('Error: '.$e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
