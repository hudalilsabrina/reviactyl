<?php

namespace App\Events\Auth;

use App\Events\Event;
use App\Models\User;

class DirectLogin extends Event
{
    public function __construct(public User $user, public bool $remember) {}
}
