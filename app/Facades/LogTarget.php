<?php

namespace App\Facades;

use App\Services\Activity\ActivityLogTargetableService;
use Illuminate\Support\Facades\Facade;

/**
 * @mixin ActivityLogTargetableService
 */
class LogTarget extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ActivityLogTargetableService::class;
    }
}
