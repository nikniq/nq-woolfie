<?php

namespace Tests\Feature;

use App\Models\GameRun;
use App\Models\Level;
use Database\Seeders\LevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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

    public function test_campaign_board_lists_only_full_runs(): void
    {
        $levels = Level::orderBy('number')->get();
        $full = '11111111-1111-4111-8111-111111111111';
        $partial = '22222222-2222-4222-8222-222222222222';
        foreach ($levels as $i => $level) {
            GameRun::create(['level_id' => $level->id, 'player_name' => 'Hero', 'outcome' => 'completed', 'time_ms' => 1000, 'score' => 300, 'campaign' => $full]);
            if ($i === 0) {
                GameRun::create(['level_id' => $level->id, 'player_name' => 'Quitter', 'outcome' => 'completed', 'time_ms' => 500, 'score' => 999, 'campaign' => $partial]);
            }
        }

        $response = $this->get('/leaderboard')
            ->assertOk()
            ->assertSeeInOrder(['Full castle escapes', 'Hero', (string) (300 * $levels->count())]);

        $campaignTable = Str::before(Str::after($response->getContent(), 'Full castle escapes'), 'Level 1');
        $this->assertStringNotContainsString('Quitter', $campaignTable, 'partial campaigns must not appear on the full-castle board');
    }

    public function test_run_rejects_malformed_campaign_id(): void
    {
        $this->postJson('/api/runs', [
            'level_id' => $this->level->id,
            'outcome' => 'completed',
            'time_ms' => 100,
            'campaign' => 'not-a-uuid',
        ])->assertUnprocessable()->assertJsonValidationErrors(['campaign']);
    }
}
