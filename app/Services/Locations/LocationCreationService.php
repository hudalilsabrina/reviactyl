<?php

namespace App\Services\Locations;

use App\Contracts\Repository\LocationRepositoryInterface;
use App\Exceptions\Model\DataValidationException;
use App\Models\Location;

class LocationCreationService
{
    /**
     * LocationCreationService constructor.
     */
    public function __construct(protected LocationRepositoryInterface $repository) {}

    /**
     * Create a new location.
     *
     * @throws DataValidationException
     */
    public function handle(array $data): Location
    {
        return $this->repository->create($data);
    }
}
