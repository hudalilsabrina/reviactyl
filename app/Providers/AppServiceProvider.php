<?php

namespace App\Providers;

use App\Models;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Destructive artisan commands blocked in production.
     */
    private const array PRODUCTION_BLOCKED_COMMANDS = [
        'migrate:fresh',
        'migrate:reset',
        'migrate:rollback',
        'db:wipe',
        'schema:dump',
    ];

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        $this->blockDestructiveCommandsInProduction();

        Sanctum::usePersonalAccessTokenModel(Models\ApiKey::class);

        View::share('appVersion', $this->versionData()['version'] ?? 'undefined');
        View::share('appIsGit', $this->versionData()['is_git'] ?? false);

        Paginator::useBootstrap();

        // If the APP_URL value is set with https:// make sure we force it here. Theoretically
        // this should just work with the proxy logic, but there are a lot of cases where it
        // doesn't, and it triggers a lot of support requests, so lets just head it off here.
        //
        // @see https://github.com/pterodactyl/panel/issues/3623
        if (Str::startsWith(config('app.url') ?? '', 'https://')) {
            URL::forceScheme('https');
        }

        Relation::enforceMorphMap([
            'allocation' => Models\Allocation::class,
            'api_key' => Models\ApiKey::class,
            'backup' => Models\Backup::class,
            'database' => Models\Database::class,
            'database_host' => Models\DatabaseHost::class,
            'egg' => Models\Egg::class,
            'egg_variable' => Models\EggVariable::class,
            'mount' => Models\Mount::class,
            'schedule' => Models\Schedule::class,
            'server' => Models\Server::class,
            'node' => Models\Node::class,
            'ssh_key' => Models\UserSSHKey::class,
            'task' => Models\Task::class,
            'user' => Models\User::class,
        ]);
    }

    /**
     * Register application service providers.
     */
    public function register(): void
    {
        $this->app->register(SettingsServiceProvider::class);
    }

    /**
     * Block destructive artisan commands when APP_ENV is production.
     */
    private function blockDestructiveCommandsInProduction(): void
    {
        if (config('app.env') !== 'production') {
            return;
        }

        Event::listen(function (CommandStarting $event) {
            if (in_array($event->command, self::PRODUCTION_BLOCKED_COMMANDS, true)) {
                $this->commandError(
                    "Blocked: [{$event->command}] cannot run in production (APP_ENV=production). "
                    .'Set APP_ENV=testing or use a non-production environment.'
                );
                exit(1);
            }
        });
    }

    /**
     * Output an error message to the console.
     */
    private function commandError(string $message): void
    {
        if (defined('STDERR')) {
            fwrite(STDERR, "\033[31m[SAFETY]\033[0m {$message}\n");
        } else {
            echo "[SAFETY] {$message}\n";
        }
    }

    /**
     * Return version information for the footer.
     */
    protected function versionData(): array
    {
        return Cache::remember('git-version', 5, function () {
            $headPath = base_path('.git/HEAD');

            if (is_file($headPath)) {
                $head = trim((string) file_get_contents($headPath));

                if (Str::startsWith($head, 'ref: ')) {
                    $referencePath = base_path('.git/'.trim(Str::after($head, 'ref: ')));

                    if (is_file($referencePath)) {
                        $version = trim((string) file_get_contents($referencePath));

                        if ($version !== '') {
                            return [
                                'version' => substr($version, 0, 8),
                                'is_git' => true,
                            ];
                        }
                    }
                }
            }

            return [
                'version' => config('app.version'),
                'is_git' => false,
            ];
        });
    }
}
