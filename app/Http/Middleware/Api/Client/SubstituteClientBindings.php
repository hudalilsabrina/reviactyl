<?php

namespace App\Http\Middleware\Api\Client;

use App\Models\Server;
use App\Models\Subuser;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Str;

class SubstituteClientBindings extends SubstituteBindings
{
    /**
     * @param  Request  $request
     */
    public function handle($request, \Closure $next): mixed
    {
        // Override default behavior of the model binding to use a specific table
        // column rather than the default 'id'.
        $this->router->bind('server', function ($value) {
            return Server::query()
                ->when(
                    Str::startsWith($value, 'serv_'),
                    fn ($builder) => $builder->whereIdentifier($value),
                    fn ($builder) => $builder->where(strlen($value) === 8 ? 'uuidShort' : 'uuid', $value)
                )
                ->firstOrFail();
        });

        $this->router->bind('user', function ($value, $route) {
            /** @var Subuser $match */
            $match = $route->parameter('server')
                ->subusers()
                ->whereRelation('user', 'uuid', '=', $value)
                ->firstOrFail();

            return $match->user;
        });

        return parent::handle($request, $next);
    }
}
