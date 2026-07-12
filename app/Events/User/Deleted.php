<?php

namespace App\Events\User;

use App\Events\Event;
use App\Models\User;
use Illuminate\Queue\SerializesModels;

class Deleted extends Event
{
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public User $user) {}
}
