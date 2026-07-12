<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repository\DatabaseHostRepositoryInterface;
use App\Models\DatabaseHost;
use Illuminate\Support\Collection;

class DatabaseHostRepository extends EloquentRepository implements DatabaseHostRepositoryInterface
{
    /**
     * Return the model backing this repository.
     */
    public function model(): string
    {
        return DatabaseHost::class;
    }

    /**
     * Return database hosts with a count of databases and the node
     * information for which it is attached.
     */
    public function getWithViewDetails(): Collection
    {
        return $this->getBuilder()->withCount('databases')->with('node')->get();
    }
}
