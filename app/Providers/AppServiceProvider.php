<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\ServiceProvider;

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
        // Credit each new account to the visit that brought it (see TrackVisit), whichever way it signed up.
        User::creating(function (User $user): void {
            $attribution = ! request()->hasSession() ? null : request()->session()->get('attribution');
            if ($attribution && $user->signup_source === null) {
                $user->forceFill([
                    'signup_source' => $attribution['source'],
                    'signup_medium' => $attribution['medium'],
                    'signup_campaign' => $attribution['campaign'],
                    'signup_landing' => $attribution['landing'],
                ]);
            }
        });
    }
}
