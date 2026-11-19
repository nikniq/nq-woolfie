<?php

namespace Tests\Feature;

use App\Models\GameRun;
use App\Models\Level;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_and_players_cannot_reach_the_admin_area(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/admin/users')->assertForbidden();
    }

    public function test_admins_see_the_dashboard_users_and_runs(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['name' => 'Sneaky Pete']);

        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Sneaky Pete');
        $this->actingAs($admin)->get('/admin/users?q=Sneaky')->assertOk()->assertSee('Sneaky Pete');
        $this->actingAs($admin)->get('/admin/runs')->assertOk();
    }

    public function test_admin_can_toggle_admin_but_not_on_themselves(): void
    {
        $admin = User::factory()->admin()->create();
        $player = User::factory()->create();

        $this->actingAs($admin)->patch("/admin/users/{$player->id}/admin");
        $this->assertTrue($player->fresh()->is_admin);

        $this->actingAs($admin)->patch("/admin/users/{$admin->id}/admin");
        $this->assertTrue($admin->fresh()->is_admin);
    }

    public function test_admin_can_delete_users_and_runs(): void
    {
        $admin = User::factory()->admin()->create();
        $player = User::factory()->create();
        $level = Level::create(['number' => 1, 'name' => 'Room', 'map' => [[1]], 'player_start' => [0, 0], 'guards' => [], 'castle_x' => 0, 'castle_y' => 0]);
        $run = GameRun::create(['level_id' => $level->id, 'player_name' => 'x', 'outcome' => 'caught', 'time_ms' => 1000, 'score' => 0]);

        $this->actingAs($admin)->delete("/admin/users/{$player->id}");
        $this->actingAs($admin)->delete("/admin/runs/{$run->id}");

        $this->assertModelMissing($player);
        $this->assertModelMissing($run);
    }

    public function test_admins_can_use_the_room_editor_without_the_password(): void
    {
        config(['game.editor_password' => '']);

        $this->actingAs(User::factory()->admin()->create())->get('/editor')->assertOk();
    }

    public function test_make_admin_command_promotes_a_user(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com']);

        $this->artisan('app:make-admin', ['email' => 'a@example.com'])->assertSuccessful();
        $this->assertTrue($user->fresh()->is_admin);
    }
}
