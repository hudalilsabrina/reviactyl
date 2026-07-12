<?php

namespace App\Facades;

use App\Services\Activity\ActivityLogBatchService;
use Illuminate\Support\Facades\Facade;

class LogBatch extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ActivityLogBatchService::class;
    }
}
