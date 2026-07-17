<?php

namespace App\Http\Controllers\Api\Client\Servers;

use App\Facades\Activity;
use App\Http\Controllers\Api\Client\ClientApiController;
use App\Models\Server;
use App\Models\ServerConfigFile;
use App\Models\ServerConfigRevision;
use App\Models\ServerConfigWatchPattern;
use App\Repositories\Agent\DaemonFileRepository;
use App\Services\ConfigRevisions\ConfigRevisionService;
use App\Services\ConfigRevisions\DiffService;
use App\Services\ConfigRevisions\WatchPatternService;
use App\Transformers\Api\Client\ConfigRevisionTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ConfigRevisionController extends ClientApiController
{
    public function __construct(
        private readonly ConfigRevisionService $revisionService,
        private readonly DiffService $diffService,
        private readonly WatchPatternService $watchPatternService,
        private readonly DaemonFileRepository $fileRepository,
    ) {
        parent::__construct();
    }

    public function index(Request $request, Server $server): array
    {
        $perPage = min((int) $request->query('per_page', 25), 100);
        $presetsOnly = $request->boolean('preset_only');

        $revisions = $this->revisionService->getRevisionHistory($server, $perPage, $presetsOnly);

        return $this->fractal->paginatedCollection($revisions, ConfigRevisionTransformer::class);
    }

    public function show(Server $server, int $revision): JsonResponse
    {
        $revisionModel = $server->configRevisions()->with(['files', 'author'])->findOrFail($revision);

        return new JsonResponse([
            'object' => 'config_revision',
            'attributes' => $this->getTransformer(ConfigRevisionTransformer::class)->transform($revisionModel),
        ]);
    }

    public function files(Server $server, int $revision): JsonResponse
    {
        $revisionModel = $server->configRevisions()->with('files')->findOrFail($revision);
        $snapshot = $this->revisionService->getFullSnapshot($revisionModel);

        $files = [];
        foreach ($snapshot as $filePath => $contentHash) {
            $fileRecord = $revisionModel->files->where('file_path', $filePath)->first();
            $files[] = [
                'path' => $filePath,
                'content_hash' => $contentHash,
                'content_length' => $fileRecord instanceof ServerConfigFile ? $fileRecord->content_length : 0,
                'changed_in_revision' => $fileRecord !== null,
            ];
        }

        return new JsonResponse(['files' => $files]);
    }

    public function fileContent(Request $request, Server $server, int $revision): Response
    {
        $request->validate(['path' => 'required|string']);

        $revisionModel = $server->configRevisions()->findOrFail($revision);
        $snapshot = $this->revisionService->getFullSnapshot($revisionModel);
        $filePath = $request->query('path');

        if (! isset($snapshot[$filePath])) {
            abort(404, 'File not found in this revision.');
        }

        $content = $this->revisionService->getBlobContent($snapshot[$filePath]);

        if ($content === null) {
            abort(404, 'Blob not found on disk.');
        }

        return new Response($content, Response::HTTP_OK, ['Content-Type' => 'text/plain']);
    }

    public function diff(Request $request, Server $server, int $revisionA, int $revisionB): JsonResponse
    {
        $revA = $server->configRevisions()->findOrFail($revisionA);
        $revB = $server->configRevisions()->findOrFail($revisionB);

        $snapshotA = $this->revisionService->getFullSnapshot($revA);
        $snapshotB = $this->revisionService->getFullSnapshot($revB);

        $allPaths = array_unique(array_merge(array_keys($snapshotA), array_keys($snapshotB)));
        $specificFile = $request->query('path');

        $diffs = [];

        foreach ($allPaths as $path) {
            if ($specificFile && $path !== $specificFile) {
                continue;
            }

            $hashA = $snapshotA[$path] ?? null;
            $hashB = $snapshotB[$path] ?? null;

            $contentA = $hashA ? $this->revisionService->getBlobContent($hashA) : '';
            $contentB = $hashB ? $this->revisionService->getBlobContent($hashB) : '';

            if ($contentA === null) {
                $contentA = '';
            }
            if ($contentB === null) {
                $contentB = '';
            }

            if ($contentA === $contentB && $hashA && $hashB) {
                continue;
            }

            $status = match (true) {
                $hashA === null => 'added',
                $hashB === null => 'deleted',
                default => 'modified',
            };

            $diff = $this->diffService->compute($contentA, $contentB);

            $diffs[$path] = [
                'status' => $status,
                'additions' => $diff['additions'],
                'deletions' => $diff['deletions'],
                'hunks' => $diff['hunks'],
            ];
        }

        return new JsonResponse([
            'object' => 'config_diff',
            'attributes' => [
                'revision_from' => $revisionA,
                'revision_to' => $revisionB,
                'files' => $diffs,
            ],
        ]);
    }

    public function diffCurrent(Request $request, Server $server, int $revision): JsonResponse
    {
        $request->validate(['path' => 'nullable|string']);

        $revisionModel = $server->configRevisions()->findOrFail($revision);
        $snapshot = $this->revisionService->getFullSnapshot($revisionModel);

        $diffs = [];
        $specificFile = $request->query('path');

        foreach ($snapshot as $filePath => $contentHash) {
            if ($specificFile && $filePath !== $specificFile) {
                continue;
            }

            $oldContent = $this->revisionService->getBlobContent($contentHash) ?? '';

            try {
                $currentContent = $this->fileRepository
                    ->setServer($server)
                    ->getContent($filePath);
            } catch (\Throwable) {
                $currentContent = '';
            }

            if ($oldContent === $currentContent) {
                continue;
            }

            $diff = $this->diffService->compute($oldContent, $currentContent);

            $diffs[$filePath] = [
                'status' => 'modified',
                'additions' => $diff['additions'],
                'deletions' => $diff['deletions'],
                'hunks' => $diff['hunks'],
            ];
        }

        return new JsonResponse([
            'object' => 'config_diff',
            'attributes' => [
                'revision_from' => $revision,
                'revision_to' => 'current',
                'files' => $diffs,
            ],
        ]);
    }

    public function store(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'message' => 'nullable|string|max:500',
            'files' => 'nullable|array',
            'files.*' => 'string',
        ]);

        $files = $request->input('files', []);
        $message = $request->input('message', 'Manual snapshot');

        $revision = $this->revisionService->createSnapshot(
            $server,
            $request->user(),
            $files,
            $message,
        );

        if (! $revision) {
            return new JsonResponse([
                'error' => 'No changes detected or no files to snapshot.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $revision->load(['author', 'files']);

        Activity::event('server:config-revision.create')
            ->property('message', $message)
            ->property('file_count', $revision->file_count)
            ->property('revision_hash', $revision->hash)
            ->log();

        return (new JsonResponse([
            'object' => 'config_revision',
            'attributes' => $this->getTransformer(ConfigRevisionTransformer::class)->transform($revision),
        ]))->setStatusCode(Response::HTTP_CREATED);
    }

    public function revert(Request $request, Server $server, int $revision): JsonResponse
    {
        $request->validate([
            'files' => 'nullable|array',
            'files.*' => 'string',
            'message' => 'nullable|string|max:500',
        ]);

        $revisionModel = $server->configRevisions()->findOrFail($revision);
        $files = $request->input('files');
        $message = $request->input('message', '');

        $newRevision = $this->revisionService->revertToRevision(
            $revisionModel,
            $request->user(),
            $files,
            $message,
        );

        if (! $newRevision) {
            return new JsonResponse([
                'error' => 'Failed to revert. Files may be unchanged or daemon unreachable.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $newRevision->load(['author', 'files']);

        Activity::event('server:config-revision.revert')
            ->property('target_revision_hash', $revisionModel->hash)
            ->property('files', $files)
            ->log();

        return new JsonResponse([
            'object' => 'config_revision',
            'attributes' => $this->getTransformer(ConfigRevisionTransformer::class)->transform($newRevision),
        ]);
    }

    public function promote(Request $request, Server $server, int $revision): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $revisionModel = $server->configRevisions()->findOrFail($revision);
        $name = $request->input('name');

        // Check preset name uniqueness
        $nameTaken = ServerConfigRevision::where('server_id', $server->id)
            ->where('is_preset', true)
            ->where('preset_name', $name)
            ->exists();

        if ($nameTaken) {
            return new JsonResponse([
                'error' => "Preset name \"{$name}\" already exists.",
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Check preset limit
        $maxPresets = config('panel.config_revisions.max_presets_per_server', 20);
        $currentPresets = $this->revisionService->getPresets($server)->count();

        if ($currentPresets >= $maxPresets) {
            return new JsonResponse([
                'error' => "Maximum preset limit ({$maxPresets}) reached.",
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->revisionService->promoteToPreset($revisionModel, $name);

        Activity::event('server:config-revision.preset.create')
            ->property('preset_name', $name)
            ->property('revision_hash', $revisionModel->hash)
            ->log();

        $freshRevision = $revisionModel->fresh()->load(['author', 'files']);

        return new JsonResponse([
            'object' => 'config_revision',
            'attributes' => $this->getTransformer(ConfigRevisionTransformer::class)->transform($freshRevision),
        ]);
    }

    public function activatePreset(Request $request, Server $server, string $presetName): JsonResponse
    {
        $revisionModel = ServerConfigRevision::where('server_id', $server->id)
            ->where('is_preset', true)
            ->where('preset_name', $presetName)
            ->firstOrFail();

        $newRevision = $this->revisionService->activatePreset($revisionModel, $request->user());

        if (! $newRevision) {
            return new JsonResponse([
                'error' => 'Failed to activate preset.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $newRevision->load(['author', 'files']);

        Activity::event('server:config-revision.preset.activate')
            ->property('preset_name', $presetName)
            ->log();

        return new JsonResponse([
            'object' => 'config_revision',
            'attributes' => $this->getTransformer(ConfigRevisionTransformer::class)->transform($newRevision),
        ]);
    }

    public function deletePreset(Server $server, string $presetName): JsonResponse
    {
        $revisionModel = ServerConfigRevision::where('server_id', $server->id)
            ->where('is_preset', true)
            ->where('preset_name', $presetName)
            ->firstOrFail();

        $this->revisionService->removePreset($revisionModel);

        Activity::event('server:config-revision.preset.delete')
            ->property('preset_name', $presetName)
            ->log();

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    public function listPresets(Server $server): JsonResponse
    {
        $presets = ServerConfigRevision::where('server_id', $server->id)
            ->where('is_preset', true)
            ->with(['author', 'files'])
            ->orderByDesc('created_at')
            ->get();

        return new JsonResponse([
            'object' => 'list',
            'data' => $presets->map(fn ($revision) => [
                'object' => 'config_revision',
                'attributes' => $this->getTransformer(ConfigRevisionTransformer::class)->transform($revision),
            ])->all(),
        ]);
    }

    public function getWatchPatterns(Server $server): JsonResponse
    {
        $patterns = $this->watchPatternService->getPatterns($server);
        $isCustom = ServerConfigWatchPattern::where('server_id', $server->id)->exists();

        return new JsonResponse([
            'patterns' => $patterns,
            'is_custom' => $isCustom,
            'defaults' => ServerConfigWatchPattern::defaults(),
        ]);
    }

    public function updateWatchPatterns(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'patterns' => 'required|array',
            'patterns.*' => 'string|max:255',
        ]);

        $this->watchPatternService->updatePatterns($server, $request->input('patterns'));

        Activity::event('server:config-revision.watch-patterns.update')
            ->property('patterns', $request->input('patterns'))
            ->log();

        return new JsonResponse([
            'patterns' => $request->input('patterns'),
            'is_custom' => true,
        ]);
    }

    public function resetWatchPatterns(Server $server): JsonResponse
    {
        $this->watchPatternService->resetPatterns($server);

        return new JsonResponse([
            'patterns' => ServerConfigWatchPattern::defaults(),
            'is_custom' => false,
        ]);
    }
}
