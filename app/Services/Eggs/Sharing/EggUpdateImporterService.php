<?php

namespace App\Services\Eggs\Sharing;

use App\Exceptions\Service\InvalidFileUploadException;
use App\Models\Egg;
use App\Models\EggStartupPart;
use App\Models\EggVariable;
use App\Models\Server;
use App\Services\Eggs\EggParserService;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class EggUpdateImporterService
{
    /**
     * EggUpdateImporterService constructor.
     */
    public function __construct(protected ConnectionInterface $connection, protected EggParserService $parser) {}

    /**
     * Update an existing Egg using an uploaded JSON file.
     *
     * @throws InvalidFileUploadException|\Throwable
     */
    public function handle(Egg $egg, UploadedFile $file): Egg
    {
        $parsed = $this->parser->handle($file);
        $oldStartup = $egg->startup;

        return $this->connection->transaction(function () use ($egg, $parsed, $oldStartup) {
            $egg = $this->parser->fillFromParsed($egg, $parsed);
            $egg->save();

            // Update existing variables or create new ones.
            foreach ($parsed['variables'] ?? [] as $variable) {
                EggVariable::unguarded(function () use ($egg, $variable) {
                    $egg->variables()->updateOrCreate([
                        'env_variable' => $variable['env_variable'],
                    ], Collection::make($variable)->except('egg_id', 'env_variable')->toArray());
                });
            }

            $imported = array_map(fn ($value) => $value['env_variable'], $parsed['variables'] ?? []);

            $egg->variables()->whereNotIn('env_variable', $imported)->delete();

            // Update existing startup parts or create new ones.
            foreach ($parsed['startup_parts'] ?? [] as $part) {
                EggStartupPart::unguarded(function () use ($egg, $part) {
                    $egg->startupParts()->updateOrCreate([
                        'name' => $part['name'],
                    ], Collection::make($part)->except('egg_id', 'name')->toArray());
                });
            }

            $importedParts = array_map(fn ($value) => $value['name'], $parsed['startup_parts'] ?? []);

            $egg->startupParts()->whereNotIn('name', $importedParts)->delete();

            // Sync servers whose startup still matches the old egg startup.
            if ($oldStartup !== $egg->startup) {
                Server::query()
                    ->where('egg_id', $egg->id)
                    ->where('startup', $oldStartup)
                    ->update(['startup' => $egg->startup]);
            }

            return $egg->refresh();
        });
    }
}
