<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialUser;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SocialAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.google.client_id' => 'google-client',
            'services.google.client_secret' => 'secret',
            'services.nikniq.client_id' => 'nikniq-client',
            'services.nikniq.client_secret' => 'secret',
        ]);
    }

    private function fakeProviderUser(string $provider, array $attributes): void
    {
        $socialUser = (new SocialUser)->map($attributes + ['nickname' => null]);
        $driver = Mockery::mock();
        $driver->shouldReceive('user')->andReturn($socialUser);
        Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);
    }

    public function test_login_page_shows_buttons_only_for_configured_providers(): void
    {
        config(['services.nikniq.client_id' => null]);

        $this->get('/login')->assertOk()->assertSee('Continue with Google')->assertDontSee('Continue with NikNiq');
        $this->get('/auth/nikniq')->assertNotFound();
    }

    public function test_nikniq_redirect_goes_to_the_portal_sso_with_pkce(): void
    {
        config(['services.nikniq.url' => 'https://nikniq.test']);

        $location = $this->get('/auth/nikniq')->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith('https://nikniq.test/sso/authorize?', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('nikniq-client', $query['client_id']);
        $this->assertSame(url('/auth/callback'), $query['redirect_uri']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame(session('nikniq_state'), $query['state']);
    }

    public function test_new_google_user_gets_an_account_with_a_unique_player_name(): void
    {
        User::factory()->create(['name' => 'Blazko']);
        $this->fakeProviderUser('google', ['id' => 'g-1', 'name' => 'Blazko!', 'email' => 'new@example.com']);

        $this->get('/auth/google/callback')->assertRedirect('/play');

        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertSame('Blazko 2', $user->name);
        $this->assertSame('g-1', $user->google_id);
        $this->assertNull($user->password);
        $this->assertAuthenticatedAs($user);
    }

    public function test_nikniq_sign_in_exchanges_the_code_and_links_an_existing_account(): void
    {
        config(['services.nikniq.url' => 'https://nikniq.test']);
        Http::fake(['nikniq.test/api/sso/token' => Http::response(['player' => ['id' => 42, 'name' => 'Someone', 'email' => 'BJ@example.com']])]);
        $existing = User::factory()->create(['email' => 'bj@example.com']);

        $this->withSession(['nikniq_state' => 'abc', 'nikniq_verifier' => 'verifier'])
            ->get('/auth/callback?state=abc&code=the-code')
            ->assertRedirect('/play');

        Http::assertSent(fn (Request $request) => $request['code'] === 'the-code' && $request['code_verifier'] === 'verifier' && $request['redirect_uri'] === url('/auth/callback'));
        $this->assertSame('42', $existing->fresh()->nikniq_id);
        $this->assertSame(1, User::count());
        $this->assertAuthenticatedAs($existing);
    }

    public function test_nikniq_callback_with_a_wrong_state_is_rejected(): void
    {
        Http::fake();

        $this->withSession(['nikniq_state' => 'abc', 'nikniq_verifier' => 'v'])
            ->get('/auth/callback?state=forged&code=x')
            ->assertRedirect('/login')->assertSessionHasErrors('email');

        Http::assertNothingSent();
        $this->assertGuest();
    }

    public function test_google_without_email_or_failed_callback_returns_to_login(): void
    {
        $this->fakeProviderUser('google', ['id' => 'g-2', 'name' => 'No Mail', 'email' => null]);
        $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHasErrors('email');

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->andThrow(new RuntimeException('denied'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
        $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }
}
