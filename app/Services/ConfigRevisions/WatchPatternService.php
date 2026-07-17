<?php

namespace App\Services\ConfigRevisions;

use App\Models\Server;
use App\Models\ServerConfigWatchPattern;
use App\Repositories\Agent\DaemonFileRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WatchPatternService
{
    public function __construct(
        private readonly DaemonFileRepository $fileRepository,
    ) {}

    /**
     * Get watch patterns for a server (custom or defaults).
     */
    public function getPatterns(Server $server): array
    {
        $customPatterns = ServerConfigWatchPattern::where('server_id', $server->id)
            ->pluck('pattern')
            ->toArray();

        return ! empty($customPatterns) ? $customPatterns : ServerConfigWatchPattern::defaults();
    }

    /**
     * Get all tracked files for a server by walking the root directory
     * and matching against watch patterns.
     *
     * @return array<string>
     */
    public function getTrackedFiles(Server $server): array
    {
        $patterns = $this->getPatterns($server);
        $excludeDirs = ['logs', 'world', 'world_nether', 'world_the_end', '.git', 'cache', 'tmp'];

        return $this->walkDirectory($server, '/', $patterns, $excludeDirs);
    }

    /**
     * Check if a file path matches any of the watch patterns.
     */
    public function matchesPattern(string $filePath, array $patterns): bool
    {
        $fileName = basename($filePath);

        foreach ($patterns as $pattern) {
            if ($this->matchGlob($pattern, $filePath) || $this->matchGlob($pattern, $fileName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Update watch patterns for a server.
     *
     * @param  array<string>  $patterns
     */
    public function updatePatterns(Server $server, array $patterns): void
    {
        DB::transaction(function () use ($server, $patterns) {
            ServerConfigWatchPattern::where('server_id', $server->id)->delete();

            foreach ($patterns as $pattern) {
                ServerConfigWatchPattern::create([
                    'server_id' => $server->id,
                    'pattern' => $pattern,
                ]);
            }
        });
    }

    /**
     * Reset watch patterns to defaults.
     */
    public function resetPatterns(Server $server): void
    {
        ServerConfigWatchPattern::where('server_id', $server->id)->delete();
    }

    /**
     * Recursively walk a directory and collect files matching patterns.
     *
     * @return array<string>
     */
    private function walkDirectory(Server $server, string $path, array $patterns, array $excludeDirs, int $depth = 0): array
    {
        if ($depth > 10) {
            return [];
        }

        try {
            $contents = $this->fileRepository->setServer($server)->getDirectory($path);
        } catch (\Throwable $e) {
            Log::debug('ConfigRevisions: Failed to list directory', [
                'server_id' => $server->id,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $files = [];

        foreach ($contents as $item) {
            $name = $item['name'] ?? '';
            $isFile = ($item['file'] ?? false) === true;
            $itemPath = rtrim($path, '/').'/'.$name;

            if (! $isFile) {
                if (! in_array($name, $excludeDirs)) {
                    $files = array_merge($files, $this->walkDirectory($server, $itemPath, $patterns, $excludeDirs, $depth + 1));
                }
            } else {
                if ($this->matchesPattern($itemPath, $patterns)) {
                    $files[] = $itemPath;
                }
            }
        }

        return $files;
    }

    /**
     * Simple glob matching supporting * and **.
     */
    private function matchGlob(string $pattern, string $value): bool
    {
        // Convert glob to regex — replace ** before * to avoid partial consumption
        $regex = str_replace(
            ['\*\*', '\*', '\?'],
            ['.*', '[^/]*', '[^/]'],
            preg_quote($pattern, '/')
        );

        return (bool) preg_match('/^'.$regex.'$/', $value);
    }
}
