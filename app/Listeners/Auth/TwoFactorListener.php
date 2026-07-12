<?php

namespace App\Listeners\Auth;

use App\Events\Auth\ProvidedAuthenticationToken;
use App\Facades\Activity;

class TwoFactorListener
{
    public function handle(ProvidedAuthenticationToken $event): void
    {
        Activity::event($event->recovery ? 'auth:recovery-token' : 'auth:token')
            ->withRequestMetadata()
            ->subject($event->user)
            ->log();
    }
}
