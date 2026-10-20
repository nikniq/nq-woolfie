<?php

namespace Tests\Feature;

use App\Models\Level;
use App\Models\SavedGame;
use App\Models\User;
use Database\Seeders\LevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedGameTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_requests_return_401(): void
    {
        $this->getJson('/save')->assertUnauthorized();
        $this->putJson('/save', ['state' => ['v' => 1]])->assertUnauthorized();
        $this->assertDatabaseCount('saved_games', 0);
    }

    public function test_player_can_store_and_read_back_their_own_save(): void
    {
        $user = User::factory()->create();
        $state = ['v' => 1, 'levelIndex' => 2, 'score' => 1200, 'savedAt' => 1700000000000];

        $this->actingAs($user)->putJson('/save', ['state' => $state])->assertOk();

        $this->actingAs($user)->getJson('/save')->assertOk()->assertJsonPath('data', $state);
        $this->assertDatabaseCount('saved_games', 1);
    }

    public function test_second_store_replaces_the_save_instead_of_adding_one(): void
    {
        $user = User::factory()->create();
        SavedGame::factory()->for($user)->create();

        $this->actingAs($user)->putJson('/save', ['state' => ['v' => 1, 'score' => 50]])->assertOk();

        $this->assertDatabaseCount('saved_games', 1);
        $this->assertSame(50, $user->savedGame()->first()->state['score']);
    }

    public function test_player_never_sees_another_players_save(): void
    {
        $other = User::factory()->create();
        SavedGame::factory()->for($other)->create();
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/save')->assertOk()->assertJsonPath('data', null);
    }

    public function test_delete_removes_only_the_players_own_save(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        SavedGame::factory()->for($user)->create();
        SavedGame::factory()->for($other)->create();

        $this->actingAs($user)->deleteJson('/save')->assertOk();

        $this->assertDatabaseMissing('saved_games', ['user_id' => $user->id]);
        $this->assertDatabaseHas('saved_games', ['user_id' => $other->id]);
    }

    public function test_store_rejects_a_missing_state(): void
    {
        $this->actingAs(User::factory()->create())->putJson('/save', [])->assertUnprocessable()->assertJsonValidationErrors('state');
    }

    public function test_runs_and_results_posted_while_signed_in_are_credited_to_the_account(): void
    {
        $this->seed(LevelSeeder::class);
        $user = User::factory()->create(['name' => 'Blazko']);
        $level = Level::where('number', 1)->firstOrFail();

        $this->actingAs($user)->postJson('/api/runs', ['level_id' => $level->id, 'player_name' => 'Impostor', 'outcome' => 'completed', 'time_ms' => 5000])
            ->assertCreated()->assertJsonPath('data.player_name', 'Blazko');
        $this->actingAs($user)->postJson('/api/campaigns', [
            'campaign' => '77777777-7777-4777-8777-777777777777', 'player_name' => 'Impostor', 'outcome' => 'escaped', 'score' => 100, 'time_ms' => 1000,
            'kills' => 0, 'alarms' => 0, 'lives_lost' => 0, 'stones' => 0, 'rooms_cleared' => 5, 'rooms_total' => 5, 'all_gold' => false, 'all_prisoners' => false, 'disguised_at_end' => false,
        ])->assertCreated()->assertJsonPath('data.player_name', 'Blazko');

        $this->assertDatabaseHas('game_runs', ['user_id' => $user->id, 'player_name' => 'Blazko']);
        $this->assertDatabaseHas('campaign_results', ['user_id' => $user->id, 'player_name' => 'Blazko']);
        $this->assertDatabaseHas('achievements', ['user_id' => $user->id, 'player_name' => 'Blazko', 'key' => 'escapee']);
    }
}
