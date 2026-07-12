<?php

namespace App\Providers;

use App\Http\ViewComposers\AssetComposer;
use App\Http\ViewComposers\DesignifyComposer;
use Illuminate\Support\ServiceProvider;

class ViewComposerServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     */
    public function boot(): void
    {
        $this->app->make('view')->composer('*', AssetComposer::class);
        $this->app->make('view')->composer('*', DesignifyComposer::class);
    }
}
