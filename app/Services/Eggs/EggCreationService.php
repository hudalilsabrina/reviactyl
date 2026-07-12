<?php

namespace App\Services\Eggs;

use App\Contracts\Repository\EggRepositoryInterface;
use App\Exceptions\Model\DataValidationException;
use App\Exceptions\Service\Egg\NoParentConfigurationFoundException;
use App\Models\Egg;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Ramsey\Uuid\Uuid;

class EggCreationService
{
    /**
     * EggCreationService constructor.
     */
    public function __construct(private ConfigRepository $config, private EggRepositoryInterface $repository) {}

    /**
     * Create a new service option and assign it to the given service.
     *
     * @throws DataValidationException
     * @throws NoParentConfigurationFoundException
     */
    public function handle(array $data): Egg
    {
        $data['config_from'] = array_get($data, 'config_from');
        if (! is_null($data['config_from'])) {
            $results = $this->repository->findCountWhere([
                ['nest_id', '=', array_get($data, 'nest_id')],
                ['id', '=', array_get($data, 'config_from')],
            ]);

            if ($results !== 1) {
                throw new NoParentConfigurationFoundException(trans('exceptions.nest.egg.must_be_child'));
            }
        }

        return $this->repository->create(array_merge($data, [
            'uuid' => Uuid::uuid4()->toString(),
            'author' => $this->config->get('panel.service.author'),
        ]), true, true);
    }
}

// :::::::                                          Hey, you! A developer? Please read CONTRIBUTING.md and help us make Reviactyl better! :)
//  -::::::::::
//     -::::::::::
//       --::::::::::
//         -:::::::::::     :::::::
//           -:::::::::::      -::::::::::
//            -:::::::::::       ::::::::::::
//             -------:::::      ::::::::::::::::
//              -----------:   ::::::---      ---::
//              ------------:::------
//              ------------:-------
//              -------------------
//              -------:---------
//              ----------------
//              -- :---------
//               ---------------
//             ---------------
//                  --------
//                 -------
//                ------
//               -----
//              ---
//             ---
//            --
