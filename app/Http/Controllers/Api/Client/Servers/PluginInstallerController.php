<?php

namespace App\Http\Controllers\Api\Client\Servers;

use App\Exceptions\Http\Connection\DaemonConnectionException;
use App\Facades\Activity;
use App\Http\Controllers\Api\Client\ClientApiController;
use App\Models\Permission;
use App\Models\Server;
use App\Repositories\Agent\DaemonFileRepository;
use App\Services\Plugins\PluginProviderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PluginInstallerController extends ClientApiController
{
    public function __construct(
        private PluginProviderService $providers,
        private DaemonFileRepository $fileRepository,
    ) {
        parent::__construct();
    }

    /**
     * Search a plugin provider for plugins.
     */
    public function search(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'provider' => ['required', Rule::in(PluginProviderService::PROVIDERS)],
            'query' => 'nullable|string|max:100',
        ]);

        $provider = $request->input('provider');
        $this->ensureAvailable($provider);

        return new JsonResponse([
            'data' => $this->providers->search(
                $provider,
                trim((string) $request->input('query', '')),
                $server,
                (int) $request->input('page', 0)
            ),
            'meta' => [
                'minecraft_version' => $this->providers->minecraftVersion($server),
            ],
        ]);
    }

    /**
     * Get full details for a single plugin.
     */
    public function details(Request $request, Server $server, string $provider, string $plugin): JsonResponse
    {
        $this->ensureAvailable($provider);

        $details = $this->providers->details($provider, $plugin);
        if ($details === null) {
            throw new NotFoundHttpException('Plugin not found.');
        }

        return new JsonResponse(['data' => $details]);
    }

    /**
     * List installable versions of a plugin.
     */
    public function versions(Request $request, Server $server, string $provider, string $plugin): JsonResponse
    {
        $this->ensureAvailable($provider);

        return new JsonResponse([
            'data' => $this->providers->versions($provider, $plugin, $server),
        ]);
    }

    /**
     * Download a plugin version into the server's plugins directory.
     *
     * @throws DaemonConnectionException
     */
    public function install(Request $request, Server $server): JsonResponse
    {
        $request->validate([
            'provider' => ['required', Rule::in(PluginProviderService::PROVIDERS)],
            'id' => 'required|string|max:200',
            'version_id' => 'required|string|max:200',
        ]);

        if (! $request->user()->can(Permission::ACTION_FILE_CREATE, $server)) {
            throw new AccessDeniedHttpException();
        }

        $provider = $request->input('provider');
        $this->ensureAvailable($provider);

        $download = $this->providers->resolveDownload($provider, $request->input('id'), $request->input('version_id'));
        if ($download === null) {
            throw new BadRequestHttpException('This plugin version cannot be installed automatically. Please download it manually from the provider.');
        }

        $this->fileRepository->setServer($server)->pull($download['url'], '/plugins', [
            'filename' => $download['filename'],
            'foreground' => true,
        ]);

        Activity::event('server:plugin.install')
            ->property('plugin', $provider.'/'.$request->input('id').'@'.$request->input('version_id'))
            ->log();

        return new JsonResponse(['data' => ['filename' => $download['filename']]]);
    }

    private function ensureAvailable(string $provider): void
    {
        if (! in_array($provider, PluginProviderService::PROVIDERS, true)) {
            throw new NotFoundHttpException();
        }

        if (! $this->providers->isAvailable($provider)) {
            throw new UnprocessableEntityHttpException('This plugin provider is not configured.');
        }
    }
}
