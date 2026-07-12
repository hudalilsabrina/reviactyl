<?php

namespace App\Http\Middleware;

use Illuminate\Auth\AuthManager;
use Illuminate\Http\Request;

class RedirectIfAuthenticated
{
    /**
     * RedirectIfAuthenticated constructor.
     */
    public function __construct(private AuthManager $authManager) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, \Closure $next, ?string $guard = null): mixed
    {
        if ($this->authManager->guard($guard)->check()) {
            return redirect()->route('index');
        }

        return $next($request);
    }
}
