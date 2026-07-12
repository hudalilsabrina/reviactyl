<?php

namespace App\Services\Locations;

use App\Contracts\Repository\LocationRepositoryInterface;
use App\Exceptions\Model\DataValidationException;
use App\Exceptions\Repository\RecordNotFoundException;
use App\Models\Location;

class LocationUpdateService
{
    /**
     * LocationUpdateService constructor.
     */
    public function __construct(protected LocationRepositoryInterface $repository) {}

    /**
     * Update an existing location.
     *
     * @throws DataValidationException
     * @throws RecordNotFoundException
     */
    public function handle(Location|int $location, array $data): Location
    {
        $location = ($location instanceof Location) ? $location->id : $location;

        return $this->repository->update($location, $data);
    }
}
