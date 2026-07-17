<?php

namespace App\Jobs\ConfigRevisions;

use App\Models\ServerConfigFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\File;

class CleanupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(): void
    {
        $storagePath = config('panel.config_revisions.storage_path');

        // Collect all content hashes still referenced in DB
        $referencedHashes = ServerConfigFile::pluck('content_hash')
            ->unique()
            ->flip()
            ->toArray();

        // Scan blob directory and delete orphaned files
        if (! File::isDirectory($storagePath)) {
            return;
        }

        $files = File::files($storagePath);

        foreach ($files as $file) {
            $hash = $file->getFilename();

            if (! isset($referencedHashes[$hash])) {
                @File::delete($file->getPathname());
            }
        }
    }
}
