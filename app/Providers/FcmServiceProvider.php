<?php

namespace App\Providers;

use App\Services\FcmService;
use Illuminate\Support\ServiceProvider;

class FcmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FcmService::class, function () {
            return new FcmService();
        });
    }

    public function boot(): void
    {
        //
    }
}
