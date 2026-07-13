<?php

namespace App\Http\Controllers\Api\Client\Servers;

use App\Exceptions\DisplayException;
use App\Facades\Activity;
use App\Http\Controllers\Api\Client\ClientApiController;
use App\Http\Requests\Api\Client\Servers\Subdomain\CreateSubdomainRequest;
use App\Http\Requests\Api\Client\Servers\Subdomain\DeleteSubdomainRequest;
use App\Http\Requests\Api\Client\Servers\Subdomain\GetSubdomainsRequest;
use App\Http\Requests\Api\Client\Servers\Subdomain\UpdateSubdomainRequest;
use App\Models\Server;
use App\Models\ServerSubdomain;
use App\Services\Subdomains\SubdomainService;
use App\Transformers\Api\Client\ServerSubdomainTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class SubdomainController extends ClientApiController
{
    public function __construct(
        private readonly SubdomainService $subdomainService,
    ) {
        parent::__construct();
    }

    /**
     * List all subdomains for a server.
     */
    public function index(GetSubdomainsRequest $request, Server $server): array
    {
        $subdomains = $this->fractal->collection($server->subdomains)
            ->transformWith($this->getTransformer(ServerSubdomainTransformer::class))
            ->toArray();

        return array_merge($subdomains, [
            'meta' => [
                'max_per_server' => $this->subdomainService->getMaxPerServer(),
                'custom_count' => ServerSubdomain::where('server_id', $server->id)
                    ->where('is_auto_generated', false)
                    ->count(),
            ],
        ]);
    }

    /**
     * Create a custom subdomain for a server.
     *
     * @throws DisplayException
     */
    public function store(CreateSubdomainRequest $request, Server $server): array
    {
        $subdomain = Activity::event('server:subdomain.create')->transaction(function ($log) use ($request, $server) {
            $result = $this->subdomainService->createCustomSubdomain(
                server: $server,
                subdomain: $request->input('subdomain'),
                customDomain: $request->input('domain'),
            );

            $log->subject($result)->property('subdomain', $result->fqdn);

            return $result;
        });

        return $this->fractal->item($subdomain)
            ->transformWith($this->getTransformer(ServerSubdomainTransformer::class))
            ->toArray();
    }

    /**
     * Update a subdomain's prefix.
     *
     * @throws DisplayException
     */
    public function update(UpdateSubdomainRequest $request, Server $server, ServerSubdomain $subdomain): array|JsonResponse
    {
        if ($subdomain->server_id !== $server->id) {
            return new JsonResponse([], Response::HTTP_NOT_FOUND);
        }

        $original = $subdomain->fqdn;

        $updated = $this->subdomainService->updateSubdomain(
            subdomain: $subdomain,
            newSubdomain: $request->input('subdomain'),
        );

        Activity::event('server:subdomain.update')
            ->subject($subdomain)
            ->property(['old' => $original, 'new' => $updated->fqdn])
            ->log();

        return $this->fractal->item($updated)
            ->transformWith($this->getTransformer(ServerSubdomainTransformer::class))
            ->toArray();
    }

    /**
     * Delete a subdomain.
     */
    public function delete(DeleteSubdomainRequest $request, Server $server, ServerSubdomain $subdomain): JsonResponse
    {
        if ($subdomain->server_id !== $server->id) {
            return new JsonResponse([], Response::HTTP_NOT_FOUND);
        }

        $fqdn = $subdomain->fqdn;

        $this->subdomainService->deleteSubdomain($subdomain);

        Activity::event('server:subdomain.delete')
            ->property('subdomain', $fqdn)
            ->log();

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }
}
