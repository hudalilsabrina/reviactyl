<?php

namespace App\Services\Nests;

use App\Contracts\Repository\NestRepositoryInterface;
use App\Exceptions\Model\DataValidationException;
use App\Exceptions\Repository\RecordNotFoundException;

class NestUpdateService
{
    /**
     * NestUpdateService constructor.
     */
    public function __construct(protected NestRepositoryInterface $repository) {}

    /**
     * Update a nest and prevent changing the author once it is set.
     *
     * @throws DataValidationException
     * @throws RecordNotFoundException
     */
    public function handle(int $nest, array $data): void
    {
        if (! is_null(array_get($data, 'author'))) {
            unset($data['author']);
        }

        $this->repository->withoutFreshModel()->update($nest, $data);
    }
}
