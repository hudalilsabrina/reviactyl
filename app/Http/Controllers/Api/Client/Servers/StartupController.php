<?php

namespace App\Http\Controllers\Api\Client\Servers;

use App\Exceptions\Model\DataValidationException;
use App\Exceptions\Repository\RecordNotFoundException;
use App\Facades\Activity;
use App\Http\Controllers\Api\Client\ClientApiController;
use App\Http\Requests\Api\Client\Servers\Startup\GetStartupRequest;
use App\Http\Requests\Api\Client\Servers\Startup\UpdateStartupPartsRequest;
use App\Http\Requests\Api\Client\Servers\Startup\UpdateStartupVariableRequest;
use App\Models\Server;
use App\Repositories\Eloquent\ServerVariableRepository;
use App\Services\Servers\StartupCommandService;
use App\Transformers\Api\Client\EggStartupPartTransformer;
use App\Transformers\Api\Client\EggVariableTransformer;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class StartupController extends ClientApiController
{
    /**
     * StartupController constructor.
     */
    public function __construct(
        private StartupCommandService $startupCommandService,
        private ServerVariableRepository $repository,
    ) {
        parent::__construct();
    }

    /**
     * Returns the startup information for the server including all the variables.
     */
    public function index(GetStartupRequest $request, Server $server): array
    {
        $startup = $this->startupCommandService->handle($server);

        $variables = $this->fractal->collection(
            $server->variables()->where('user_viewable', true)->get()
        )
            ->transformWith($this->getTransformer(EggVariableTransformer::class))
            ->toArray();

        $parts = $this->fractal->collection(
            $server->egg->startupParts
        )
            ->transformWith($this->getTransformer(EggStartupPartTransformer::class))
            ->toArray();

        // Merge user's saved choices into parts
        $userChoices = $server->startup_parts ?? [];
        $partsData = collect($parts['data'] ?? [])->map(function ($part) use ($userChoices) {
            $attrs = $part['attributes'] ?? $part;
            $choice = collect($userChoices)->firstWhere('part_id', $attrs['id']);
            $attrs['user_enabled'] = $choice['enabled'] ?? $attrs['default_enabled'];

            return $attrs;
        })->toArray();

        return [
            'data' => $variables['data'] ?? [],
            'meta' => [
                'startup_command' => $startup,
                'docker_images' => $server->egg->docker_images,
                'raw_startup_command' => $server->startup,
                'startup_parts' => $partsData,
                'has_modular_startup' => $server->egg->startupParts->isNotEmpty(),
            ],
        ];
    }

    /**
     * Updates a single variable for a server.
     *
     * @throws ValidationException
     * @throws DataValidationException
     * @throws RecordNotFoundException
     */
    public function update(UpdateStartupVariableRequest $request, Server $server): array
    {
        $variable = $server->variables()->where('env_variable', $request->input('key'))->first();

        if (is_null($variable) || ! $variable->user_viewable) {
            throw new BadRequestHttpException('The environment variable you are trying to edit does not exist.');
        } elseif (! $variable->user_editable) {
            throw new BadRequestHttpException('The environment variable you are trying to edit is read-only.');
        }

        $original = $variable->server_value;

        // Revalidate the variable value using the egg variable specific validation rules for it.
        $this->validate($request, ['value' => $variable->rules]);

        $this->repository->updateOrCreate([
            'server_id' => $server->id,
            'variable_id' => $variable->id,
        ], [
            'variable_value' => $request->input('value') ?? '',
        ]);

        $variable = $variable->refresh();
        $variable->server_value = $request->input('value');

        $startup = $this->startupCommandService->handle($server);

        if ($original !== $request->input('value')) {
            Activity::event('server:startup.edit')
                ->subject($variable)
                ->property([
                    'variable' => $variable->env_variable,
                    'old' => $original,
                    'new' => $request->input('value') ?? '',
                ])
                ->log();
        }

        return $this->fractal->item($variable)
            ->transformWith($this->getTransformer(EggVariableTransformer::class))
            ->addMeta([
                'startup_command' => $startup,
                'raw_startup_command' => $server->startup,
            ])
            ->toArray();
    }

    /**
     * Updates the startup parts configuration for a server.
     *
     * @throws ValidationException
     */
    public function updateParts(UpdateStartupPartsRequest $request, Server $server): array
    {
        $eggParts = $server->egg->startupParts;

        if ($eggParts->isEmpty()) {
            throw new BadRequestHttpException('This server does not have configurable startup parts.');
        }

        $requestedParts = $request->input('parts', []);

        // Validate that all part IDs belong to this server's egg
        $validPartIds = $eggParts->pluck('id')->toArray();
        $seenIds = [];

        foreach ($requestedParts as $part) {
            if (! in_array($part['part_id'], $validPartIds)) {
                throw new BadRequestHttpException('Invalid startup part ID: '.$part['part_id']);
            }
            if (in_array($part['part_id'], $seenIds)) {
                throw new BadRequestHttpException('Duplicate startup part ID: '.$part['part_id']);
            }
            $seenIds[] = $part['part_id'];
        }

        // Validate required parts — must be present AND enabled
        foreach ($eggParts->where('required', true) as $requiredPart) {
            $choice = collect($requestedParts)->firstWhere('part_id', $requiredPart->id);
            if (! $choice || ! $choice['enabled']) {
                throw new BadRequestHttpException("The startup part '{$requiredPart->name}' is required and cannot be disabled.");
            }
        }

        // Build the parts array preserving order from request
        $partsData = collect($requestedParts)->map(function ($part) {
            return [
                'part_id' => $part['part_id'],
                'enabled' => $part['enabled'],
            ];
        })->values()->toArray();

        $server->update(['startup_parts' => $partsData]);

        $startup = $this->startupCommandService->handle($server);

        Activity::event('server:startup.edit')
            ->subject($server)
            ->property([
                'variable' => 'startup_parts',
                'old' => 'updated',
                'new' => json_encode($partsData),
            ])
            ->log();

        return [
            'meta' => [
                'startup_command' => $startup,
                'raw_startup_command' => $server->startup,
            ],
        ];
    }
}
