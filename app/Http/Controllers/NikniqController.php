<?php

namespace App\Http\Controllers;

use App\Services\SocialAccounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sign in with a nikniq.com account, using the portal's SSO (/sso/authorize and
 * /api/sso/token) with PKCE, so the default public-client registration (client id
 * only) works. A client secret is sent too when configured.
 */
class NikniqController extends Controller
{
    public static function enabled(): bool
    {
        return filled(config('services.nikniq.client_id'));
    }

    /** Must exactly match a redirect URI allowed for Woolfie on the portal. */
    public static function redirectUri(): string
    {
        return (string) (config('services.nikniq.redirect') ?: route('auth.callback'));
    }

    public function redirect(Request $request): RedirectResponse
    {
        abort_unless(self::enabled(), 404);

        $state = Str::random(40);
        $verifier = Str::random(64);
        $request->session()->put(['nikniq_state' => $state, 'nikniq_verifier' => $verifier]);

        return redirect()->away(self::portal().'/sso/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.nikniq.client_id'),
            'redirect_uri' => self::redirectUri(),
            'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]));
    }

    public function callback(Request $request, SocialAccounts $accounts): RedirectResponse
    {
        abort_unless(self::enabled(), 404);

        $state = (string) $request->session()->pull('nikniq_state', '');
        $verifier = (string) $request->session()->pull('nikniq_verifier', '');
        $fail = fn (string $message): RedirectResponse => redirect()->route('login')->withErrors(['email' => $message]);

        if ($state === '' || ! hash_equals($state, (string) $request->query('state', ''))) {
            return $fail('NikNiq sign-in expired. Please try again.');
        }
        if ($request->filled('error') || ! $request->filled('code')) {
            return $fail('NikNiq sign-in was cancelled or failed. Please try again.');
        }

        try {
            $response = Http::asJson()->acceptJson()->timeout(10)->post(self::portal().'/api/sso/token', array_filter([
                'grant_type' => 'authorization_code',
                'client_id' => config('services.nikniq.client_id'),
                'client_secret' => config('services.nikniq.client_secret'),
                'code' => (string) $request->query('code'),
                'redirect_uri' => self::redirectUri(),
                'code_verifier' => $verifier,
            ]));
        } catch (Throwable $e) {
            Log::warning('nikniq.com token request error', ['message' => $e->getMessage()]);

            return $fail('Could not reach nikniq.com. Please try again.');
        }

        $player = $response->successful() ? (array) $response->json('player') : [];
        $id = (string) ($player['id'] ?? '');
        if ($id === '') {
            Log::warning('nikniq.com token exchange failed', [
                'status' => $response->status(),
                'error' => $response->json('error'),
                'error_description' => $response->json('error_description'),
                'redirect_uri' => self::redirectUri(),
            ]);

            return $fail('NikNiq sign-in failed ('.Str::limit((string) ($response->json('error_description') ?: $response->json('error') ?: 'HTTP '.$response->status()), 160).'). Please try again.');
        }

        $user = $accounts->findOrCreate('nikniq', $id, $player['email'] ?? null, $player['name'] ?? null);
        if (! $user) {
            return $fail('Your NikNiq account did not share an email address, so we could not sign you in.');
        }

        return SocialAuthController::signIn($request, $user);
    }

    private static function portal(): string
    {
        return rtrim((string) config('services.nikniq.url'), '/');
    }
}
