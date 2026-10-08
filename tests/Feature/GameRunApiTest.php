<?php

namespace Tests\Feature;

use App\Models\GameRun;
use App\Models\Level;
use Database\Seeders\LevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameRunApiTest extends TestCase
{
    use RefreshDatabase;

    private Level $level;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LevelSeeder::class);
        $this->level = Level::where('number', 1)->firstOrFail();
    }

    public function test_completed_run_is_stored_and_flagged_as_best(): void
    {
        $this->postJson('/api/runs', [
            'level_id' => $this->level->id,
            'player_name' => 'BJ',
            'outcome' => 'completed',
            'time_ms' => 12345,
            'score' => 650,
        ])->assertCreated()
            ->assertJsonPath('data.player_name', 'BJ')
            ->assertJsonPath('data.score', 650)
            ->assertJsonPath('best_time_ms', 12345)
            ->assertJsonPath('is_personal_best', true);

        $this->postJson('/api/runs', [
            'level_id' => $this->level->id,
            'outcome' => 'completed',
            'time_ms' => 20000,
        ])->assertCreated()
            ->assertJsonPath('data.player_name', 'Anonymous')
            ->assertJsonPath('data.score', 0)
            ->assertJsonPath('best_time_ms', 12345)
            ->assertJsonPath('is_personal_best', false);

        $this->assertDatabaseCount('game_runs', 2);
    }

    public function test_invalid_run_is_rejected(): void
    {
        $this->postJson('/api/runs', [
            'level_id' => 999,
            'outcome' => 'teleported',
            'time_ms' => -1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['level_id', 'outcome', 'time_ms']);
    }

    public function test_leaderboard_ranks_escapes_by_score_then_time(): void
    {
        GameRun::create(['level_id' => $this->level->id, 'player_name' => 'Fast', 'outcome' => 'completed', 'time_ms' => 5000, 'score' => 250]);
        GameRun::create(['level_id' => $this->level->id, 'player_name' => 'Slow', 'outcome' => 'completed', 'time_ms' => 9000, 'score' => 250]);
        GameRun::create(['level_id' => $this->level->id, 'player_name' => 'Looter', 'outcome' => 'completed', 'time_ms' => 20000, 'score' => 900]);
        GameRun::create(['level_id' => $this->level->id, 'player_name' => 'Unlucky', 'outcome' => 'caught', 'time_ms' => 1000, 'score' => 100]);

        $this->get('/leaderboard')
            ->assertOk()
            ->assertSeeInOrder(['Looter', '900', 'Fast', '5.00s', 'Slow', '9.00s'])
            ->assertDontSee('Unlucky');
    }
}
