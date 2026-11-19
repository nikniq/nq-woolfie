<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

class SocialAuthController extends Controller
{
    /** @var array<string, string> provider => label */
    public const PROVIDERS = ['google' => 'Google', 'nikniq' => 'NikNiq'];

    /**
     * Providers that have a client id configured, so their buttons can be shown.
     *
     * @return array<string, string>
     */
    public static function enabled(): array
    {
        return array_filter(self::PROVIDERS, fn (string $label, string $provider): bool => filled(config("services.{$provider}.client_id")), ARRAY_FILTER_USE_BOTH);
    }

    public function redirect(string $provider): SymfonyRedirect
    {
        $this->ensureEnabled($provider);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $this->ensureEnabled($provider);
        $label = self::PROVIDERS[$provider];

        try {
            $social = Socialite::driver($provider)->user();
        } catch (Throwable) {
            return redirect()->route('login')->withErrors(['email' => "{$label} sign-in was cancelled or failed. Please try again."]);
        }

        $user = $this->findOrCreateUser($provider, $social);
        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => "Your {$label} account did not share an email address, so we could not sign you in."]);
        }

        $isNew = $user->wasRecentlyCreated;
        Auth::login($user, remember: true);
        $request->session()->regenerate();

        if ($isNew) {
            return redirect()->route('game')->with('status', "Welcome, {$user->name}. Your campaigns are now saved to your account.");
        }

        return redirect()->intended(route('game'));
    }

    /**
     * Match on the provider id first, then link an existing account with the same email,
     * otherwise create a new account with a unique player name.
     */
    private function findOrCreateUser(string $provider, SocialUser $social): ?User
    {
        $column = "{$provider}_id";
        $id = (string) $social->getId();

        if ($user = User::where($column, $id)->first()) {
            return $user;
        }

        $email = $social->getEmail();
        if (blank($email)) {
            return null;
        }

        if ($user = User::where('email', $email)->first()) {
            $user->forceFill([$column => $id])->save();

            return $user;
        }

        $user = new User(['name' => $this->uniqueName($social->getNickname() ?: $social->getName() ?: Str::before($email, '@')), 'email' => $email]);
        $user->forceFill([$column => $id, 'email_verified_at' => now()])->save();

        return $user;
    }

    /**
     * Turn a provider display name into a free player name that passes registration rules.
     */
    private function uniqueName(string $wanted): string
    {
        $base = trim(Str::limit(preg_replace('/[^\pL\pN _.-]+/u', '', $wanted) ?? '', 20, ''));
        if (mb_strlen($base) < 2) {
            $base = 'Player';
        }

        $name = $base;
        for ($n = 2; User::where('name', $name)->exists(); $n++) {
            $name = $base.' '.$n;
        }

        return $name;
    }

    private function ensureEnabled(string $provider): void
    {
        abort_unless(array_key_exists($provider, self::enabled()), 404);
    }
}
