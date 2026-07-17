<?php

namespace App\Services\ConfigRevisions;

use App\Models\Server;
use App\Models\ServerConfigFile;
use App\Models\ServerConfigRevision;
use App\Models\User;
use App\Repositories\Agent\DaemonFileRepository;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ConfigRevisionService
{
    public function __construct(
        private readonly DaemonFileRepository $fileRepository,
        private readonly WatchPatternService $watchPatternService,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('panel.config_revisions.enabled', true);
    }

    /**
     * Create a new config revision snapshot for the given server.
     *
     * @param  array<string>  $filePaths  Specific files to snapshot. If empty, uses watch patterns.
     */
    public function createSnapshot(Server $server, User $author, array $filePaths = [], string $message = 'Auto-snapshot'): ?ServerConfigRevision
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $storagePath = config('panel.config_revisions.storage_path');

        if (empty($filePaths)) {
            $filePaths = $this->watchPatternService->getTrackedFiles($server);
        }

        if (empty($filePaths)) {
            return null;
        }

        // Fetch file contents from daemon and store blobs
        $previousRevision = ServerConfigRevision::where('server_id', $server->id)
            ->orderByDesc('id')
            ->first();

        $storedFiles = [];
        $newHashes = [];

        foreach ($filePaths as $filePath) {
            try {
                $content = $this->fileRepository->setServer($server)->getContent($filePath);
            } catch (\Throwable) {
                continue;
            }

            $maxFileSize = config('panel.config_revisions.max_file_size', 1048576);
            if (strlen($content) > $maxFileSize) {
                continue;
            }

            $contentHash = hash('sha256', $content);
            $blobPath = $storagePath.'/'.$contentHash;

            // Store blob (CAS — only writes if not exists)
            if (! File::exists($blobPath)) {
                File::ensureDirectoryExists($storagePath);
                File::put($blobPath, $content);
            }

            $newHashes[$filePath] = $contentHash;
        }

        if (empty($newHashes)) {
            return null;
        }

        // Check for duplicate — if all hashes match previous revision, skip
        if ($previousRevision) {
            $previousFiles = $previousRevision->files()->pluck('content_hash', 'file_path')->toArray();
            $isDuplicate = true;

            foreach ($newHashes as $path => $hash) {
                if (! isset($previousFiles[$path]) || $previousFiles[$path] !== $hash) {
                    $isDuplicate = false;
                    break;
                }
            }

            // Also check if previous had files not in current set
            if ($isDuplicate && count($previousFiles) !== count($newHashes)) {
                $isDuplicate = false;
            }

            if ($isDuplicate) {
                return null;
            }
        }

        // Create revision
        $revisionHash = sha1(implode(':', [
            $server->id,
            $author->id,
            $message,
            json_encode($newHashes),
            (string) time(),
        ]));

        $revision = ServerConfigRevision::create([
            'server_id' => $server->id,
            'author_id' => $author->id,
            'parent_id' => $previousRevision?->id,
            'message' => $message,
            'hash' => $revisionHash,
            'file_count' => count($newHashes),
        ]);

        // Only store files that changed from previous revision (delta storage)
        foreach ($newHashes as $filePath => $contentHash) {
            $previousHash = $previousRevision
                ? $previousRevision->files()->where('file_path', $filePath)->value('content_hash')
                : null;

            if ($previousHash !== $contentHash) {
                ServerConfigFile::create([
                    'revision_id' => $revision->id,
                    'file_path' => $filePath,
                    'content_hash' => $contentHash,
                    'content_length' => strlen(File::get($storagePath.'/'.$contentHash)),
                ]);
            }
        }

        // If no files actually changed (edge case), clean up
        if ($revision->files()->count() === 0) {
            $revision->delete();

            return null;
        }

        // Enforce retention policy
        $this->enforceRetentionPolicy($server);

        return $revision;
    }

    /**
     * Get the full snapshot for a revision (reconstruct from delta chain).
     *
     * @return array<string, string> filePath => contentHash
     */
    public function getFullSnapshot(ServerConfigRevision $revision): array
    {
        $files = [];
        $current = $revision;

        while ($current) {
            foreach ($current->files as $file) {
                $files[$file->file_path] ??= $file->content_hash;
            }

            $current = $current->parent_id ? ServerConfigRevision::find($current->parent_id) : null;
        }

        return $files;
    }

    /**
     * Get file content from a blob hash.
     */
    public function getBlobContent(string $contentHash): ?string
    {
        $storagePath = config('panel.config_revisions.storage_path');
        $blobPath = $storagePath.'/'.$contentHash;

        if (! File::exists($blobPath)) {
            return null;
        }

        return File::get($blobPath);
    }

    /**
     * Revert files to a previous revision.
     *
     * @param  array<string>|null  $filePaths  Specific files to revert. Null = all files in snapshot.
     */
    public function revertToRevision(
        ServerConfigRevision $revision,
        User $author,
        ?array $filePaths = null,
        string $message = '',
    ): ?ServerConfigRevision {
        if (! $this->isEnabled()) {
            return null;
        }

        $snapshot = $this->getFullSnapshot($revision);
        $storagePath = config('panel.config_revisions.storage_path');

        $revertedPaths = [];

        foreach ($snapshot as $filePath => $contentHash) {
            if ($filePaths !== null && ! in_array($filePath, $filePaths)) {
                continue;
            }

            $blobPath = $storagePath.'/'.$contentHash;
            if (! File::exists($blobPath)) {
                continue;
            }

            $content = File::get($blobPath);

            try {
                $this->fileRepository->setServer($revision->server)->putContent($filePath, $content);
                $revertedPaths[] = $filePath;
            } catch (\Throwable) {
                continue;
            }
        }

        if (empty($revertedPaths)) {
            return null;
        }

        $revertMessage = $message ?: sprintf('Reverted to revision %s', substr($revision->hash, 0, 8));

        return $this->createSnapshot(
            $revision->server,
            $author,
            $revertedPaths,
            $revertMessage,
        );
    }

    /**
     * Promote a revision to a named preset.
     */
    public function promoteToPreset(ServerConfigRevision $revision, string $name): ServerConfigRevision
    {
        $revision->update([
            'is_preset' => true,
            'preset_name' => $name,
        ]);

        return $revision;
    }

    /**
     * Remove preset tag from a revision.
     */
    public function removePreset(ServerConfigRevision $revision): void
    {
        $revision->update([
            'is_preset' => false,
            'preset_name' => null,
        ]);
    }

    /**
     * Activate a preset (revert to its snapshot).
     */
    public function activatePreset(ServerConfigRevision $revision, User $author): ?ServerConfigRevision
    {
        if (! $revision->is_preset) {
            return null;
        }

        return $this->revertToRevision(
            $revision,
            $author,
            message: sprintf('Activated preset "%s"', $revision->preset_name),
        );
    }

    /**
     * Enforce retention policy — prune oldest non-preset revisions.
     */
    public function enforceRetentionPolicy(Server $server): void
    {
        $maxRevisions = config('panel.config_revisions.max_revisions_per_server', 200);

        $revisions = ServerConfigRevision::where('server_id', $server->id)
            ->where('is_preset', false)
            ->orderByDesc('id')
            ->pluck('id');

        if ($revisions->count() <= $maxRevisions) {
            return;
        }

        $toPrune = $revisions->slice($maxRevisions);

        if ($toPrune->isEmpty()) {
            return;
        }

        $this->pruneRevisions($toPrune->toArray());
    }

    /**
     * Prune specific revisions and clean up orphaned blobs.
     */
    public function pruneRevisions(array $revisionIds): void
    {
        $storagePath = config('panel.config_revisions.storage_path');

        // Collect all content hashes from revisions being pruned
        $hashes = ServerConfigFile::whereIn('revision_id', $revisionIds)
            ->pluck('content_hash')
            ->unique()
            ->toArray();

        // Delete revisions (cascade deletes files)
        ServerConfigRevision::whereIn('id', $revisionIds)->delete();

        // Check which hashes are still referenced by remaining files
        $stillReferenced = ServerConfigFile::whereIn('content_hash', $hashes)
            ->pluck('content_hash')
            ->unique()
            ->toArray();

        $orphaned = array_diff($hashes, $stillReferenced);

        foreach ($orphaned as $hash) {
            $blobPath = $storagePath.'/'.$hash;
            if (File::exists($blobPath)) {
                @File::delete($blobPath);
            }
        }
    }

    /**
     * Get all presets for a server.
     */
    public function getPresets(Server $server)
    {
        return ServerConfigRevision::where('server_id', $server->id)
            ->where('is_preset', true)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get revision history for a server with pagination.
     */
    public function getRevisionHistory(Server $server, int $perPage = 25, bool $presetsOnly = false)
    {
        $query = ServerConfigRevision::where('server_id', $server->id)
            ->with('author')
            ->orderByDesc('created_at');

        if ($presetsOnly) {
            $query->where('is_preset', true);
        }

        return $query->paginate($perPage);
    }
}
