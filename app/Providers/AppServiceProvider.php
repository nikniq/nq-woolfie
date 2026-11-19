<?php

namespace App\Providers;

use App\Socialite\NikNiqProvider;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Contracts\Factory as Socialite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->make(Socialite::class)->extend('nikniq', fn ($app) => $app->make(Socialite::class)->buildProvider(NikNiqProvider::class, config('services.nikniq')));
    }
}
