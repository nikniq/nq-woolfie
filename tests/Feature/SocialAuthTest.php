<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_nikniq_redirect_goes_to_the_passport_authorize_endpoint(): void
    {
        config(['services.nikniq.base_url' => 'https://nikniq.test']);

        $response = $this->get('/auth/nikniq');

        $response->assertRedirect();
        $this->assertStringStartsWith('https://nikniq.test/oauth/authorize?', $response->headers->get('Location'));
        $this->assertStringContainsString('client_id=nikniq-client', $response->headers->get('Location'));
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

    public function test_nikniq_sign_in_links_an_existing_account_with_the_same_email(): void
    {
        $existing = User::factory()->create(['email' => 'bj@example.com']);
        $this->fakeProviderUser('nikniq', ['id' => '42', 'name' => 'Someone', 'email' => 'bj@example.com']);

        $this->get('/auth/nikniq/callback')->assertRedirect('/play');

        $this->assertSame('42', $existing->fresh()->nikniq_id);
        $this->assertSame(1, User::count());
        $this->assertAuthenticatedAs($existing);
    }

    public function test_provider_without_email_or_failed_callback_returns_to_login(): void
    {
        $this->fakeProviderUser('google', ['id' => 'g-2', 'name' => 'No Mail', 'email' => null]);
        $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHasErrors('email');

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->andThrow(new RuntimeException('denied'));
        Socialite::shouldReceive('driver')->with('nikniq')->andReturn($driver);
        $this->get('/auth/nikniq/callback')->assertRedirect('/login')->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }
}
