<?php

namespace Tests\Feature;

use App\Models\GameRun;
use App\Models\Level;
use Database\Seeders\LevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_shows_runs_achievements_and_campaigns(): void
    {
        $this->seed(LevelSeeder::class);
        $level = Level::where('number', 1)->firstOrFail();
        GameRun::create(['level_id' => $level->id, 'player_name' => 'Vera', 'outcome' => 'completed', 'time_ms' => 8000, 'score' => 350]);
        $this->postJson('/api/campaigns', [
            'campaign' => '44444444-4444-4444-8444-444444444444', 'player_name' => 'Vera', 'difficulty' => 'normal', 'outcome' => 'escaped',
            'score' => 900, 'time_ms' => 120000, 'kills' => 0, 'alarms' => 1, 'lives_lost' => 1, 'stones' => 0,
            'rooms_cleared' => 5, 'rooms_total' => 5, 'all_gold' => false, 'all_prisoners' => false, 'disguised_at_end' => false,
        ])->assertCreated();

        $this->get('/players/Vera')->assertOk()
            ->assertSeeInOrder(['Vera', 'Escapes: <strong>1</strong>', 'Escapee', 'The Cellar', '8.00s', 'Escaped'], false);
        $this->get('/leaderboard')->assertOk()->assertSee('/players/Vera"', false);
    }

    public function test_unknown_player_is_404(): void
    {
        $this->get('/players/Nobody')->assertNotFound();
    }
}
