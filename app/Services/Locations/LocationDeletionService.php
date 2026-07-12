<?php

namespace App\Services\Locations;

use App\Contracts\Repository\LocationRepositoryInterface;
use App\Contracts\Repository\NodeRepositoryInterface;
use App\Exceptions\Service\Location\HasActiveNodesException;
use App\Models\Location;

class LocationDeletionService
{
    /**
     * LocationDeletionService constructor.
     */
    public function __construct(
        protected LocationRepositoryInterface $repository,
        protected NodeRepositoryInterface $nodeRepository,
    ) {}

    /**
     * Delete an existing location.
     *
     * @throws HasActiveNodesException
     */
    public function handle(Location|int $location): ?int
    {
        $location = $location instanceof Location ? $location->id : $location;

        $count = $this->nodeRepository->findCountWhere([['location_id', '=', $location]]);
        if ($count > 0) {
            throw new HasActiveNodesException(trans('exceptions.locations.has_nodes'));
        }

        return $this->repository->delete($location);
    }
}
