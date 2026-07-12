<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repository\ServerVariableRepositoryInterface;
use App\Models\ServerVariable;

class ServerVariableRepository extends EloquentRepository implements ServerVariableRepositoryInterface
{
    /**
     * Return the model backing this repository.
     */
    public function model(): string
    {
        return ServerVariable::class;
    }
}
