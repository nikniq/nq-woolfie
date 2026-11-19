<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SocialAccounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * Sign in with Google (via Socialite).
 */
class SocialAuthController extends Controller
{
    /**
     * Sign-in providers that are configured, so their buttons can be shown.
     *
     * @return array<string, string> route name => label
     */
    public static function enabled(): array
    {
        return array_filter([
            'oauth.google' => filled(config('services.google.client_id')) ? 'Google' : null,
            'auth.nikniq' => NikniqController::enabled() ? 'NikNiq' : null,
        ]);
    }

    public function redirect(): SymfonyRedirect
    {
        abort_unless(filled(config('services.google.client_id')), 404);

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request, SocialAccounts $accounts): RedirectResponse
    {
        abort_unless(filled(config('services.google.client_id')), 404);

        try {
            $social = Socialite::driver('google')->user();
        } catch (Throwable) {
            return redirect()->route('login')->withErrors(['email' => 'Google sign-in was cancelled or failed. Please try again.']);
        }

        $user = $accounts->findOrCreate('google', (string) $social->getId(), $social->getEmail(), $social->getName());
        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => 'Your Google account did not share an email address, so we could not sign you in.']);
        }

        return self::signIn($request, $user);
    }

    public static function signIn(Request $request, User $user): RedirectResponse
    {
        Auth::login($user, remember: true);
        $request->session()->regenerate();

        if ($user->wasRecentlyCreated) {
            return redirect()->route('game')->with('status', "Welcome, {$user->name}. Your campaigns are now saved to your account.");
        }

        return redirect()->intended(route('game'));
    }
}
