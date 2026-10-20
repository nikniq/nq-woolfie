<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_registration_creates_the_account_and_signs_in(): void
    {
        $response = $this->post('/register', [
            'name' => 'Blazko', 'email' => 'bj@example.com', 'password' => 'correct horse', 'password_confirmation' => 'correct horse',
        ]);

        $response->assertRedirect('/')->assertSessionHas('status');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['name' => 'Blazko', 'email' => 'bj@example.com']);
    }

    public function test_registration_rejects_a_taken_player_name(): void
    {
        User::factory()->create(['name' => 'Blazko']);

        $response = $this->from('/register')->post('/register', [
            'name' => ' Blazko ', 'email' => 'other@example.com', 'password' => 'correct horse', 'password_confirmation' => 'correct horse',
        ]);

        $response->assertRedirect('/register')->assertSessionHasErrors(['name' => 'That player name is taken.']);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_rejects_names_with_markup(): void
    {
        $this->from('/register')->post('/register', [
            'name' => '<b>x</b>', 'email' => 'x@example.com', 'password' => 'correct horse', 'password_confirmation' => 'correct horse',
        ])->assertSessionHasErrors('name');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_with_wrong_password_returns_an_error_and_stays_guest(): void
    {
        $user = User::factory()->create();

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_with_correct_password_signs_in_and_redirects_to_the_game(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_logout_ends_the_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_signed_in_player_sees_their_name_locked_in_the_game_hud(): void
    {
        $user = User::factory()->create(['name' => 'Blazko']);

        $this->actingAs($user)->get('/')->assertOk()
            ->assertSee('data-user-name="Blazko"', false)
            ->assertSee('value="Blazko" readonly', false)
            ->assertSee('Sign out');
    }

    public function test_guest_sees_sign_in_links_instead(): void
    {
        $this->get('/')->assertOk()->assertSee('Register')->assertDontSee('data-user-name', false);
    }
}
