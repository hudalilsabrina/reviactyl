<?php

namespace App\Services\Users;

use App\Contracts\Repository\ServerRepositoryInterface;
use App\Contracts\Repository\UserRepositoryInterface;
use App\Exceptions\DisplayException;
use App\Models\User;
use Illuminate\Contracts\Translation\Translator;

class UserDeletionService
{
    /**
     * UserDeletionService constructor.
     */
    public function __construct(
        protected UserRepositoryInterface $repository,
        protected ServerRepositoryInterface $serverRepository,
        protected Translator $translator,
    ) {}

    /**
     * Delete a user from the panel only if they have no servers attached to their account.
     *
     * @throws DisplayException
     */
    public function handle(int|User $user): ?bool
    {
        if ($user instanceof User) {
            $user = $user->id;
        }

        $servers = $this->serverRepository->setColumns('id')->findCountWhere([['owner_id', '=', $user]]);
        if ($servers > 0) {
            throw new DisplayException($this->translator->get('admin/user.exceptions.user_has_servers'));
        }

        return $this->repository->delete($user); // @phpstan-ignore return.type (this has been like this, unsure at the moment what is truly correct)
    }
}
