<?php

namespace Tests\Feature;

use App\Services\DailyChallenge;
use Database\Seeders\LevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DailyChallengeTest extends TestCase
{
    use RefreshDatabase;

    public function test_challenge_is_fixed_per_day_and_has_two_distinct_modifiers(): void
    {
        $svc = new DailyChallenge;
        $a = $svc->forDate(Carbon::parse('2026-10-19'));
        $b = $svc->forDate(Carbon::parse('2026-10-19'));
        $c = $svc->forDate(Carbon::parse('2026-10-20'));
        $this->assertSame($a, $b);
        $this->assertCount(2, $a['modifiers']);
        $this->assertNotSame($a['modifiers'][0]['key'], $a['modifiers'][1]['key']);
        $this->assertSame('2026-10-20', $c['date']);
        foreach (range(1, 60) as $i) {
            $keys = array_column($svc->forDate(Carbon::parse('2026-01-01')->addDays($i))['modifiers'], 'key');
            $this->assertCount(2, array_unique($keys));
            $this->assertFalse(in_array('fragile', $keys, true) && in_array('lockdown_start', $keys, true));
        }
    }

    public function test_daily_results_rank_escapes_first_then_score(): void
    {
        $this->seed(LevelSeeder::class);
        $today = now()->toDateString();
        $base = ['difficulty' => 'normal', 'time_ms' => 100000, 'kills' => 0, 'alarms' => 0, 'lives_lost' => 0, 'stones' => 0, 'rooms_cleared' => 5, 'rooms_total' => 5, 'all_gold' => false, 'all_prisoners' => false, 'disguised_at_end' => false, 'daily' => $today];
        $this->postJson('/api/campaigns', array_merge($base, ['campaign' => '66666666-6666-4666-8666-666666666661', 'player_name' => 'Fell', 'outcome' => 'failed', 'score' => 5000]))->assertCreated();
        $this->postJson('/api/campaigns', array_merge($base, ['campaign' => '66666666-6666-4666-8666-666666666662', 'player_name' => 'Made', 'outcome' => 'escaped', 'score' => 900]))->assertCreated();
        $this->postJson('/api/campaigns', array_merge($base, ['campaign' => '66666666-6666-4666-8666-666666666663', 'player_name' => 'NotDaily', 'outcome' => 'escaped', 'score' => 9999, 'daily' => null]))->assertCreated();

        $this->getJson('/api/daily')->assertOk()
            ->assertJsonPath('data.date', $today)
            ->assertJsonPath('data.board.0.player_name', 'Made')
            ->assertJsonPath('data.board.1.player_name', 'Fell')
            ->assertJsonCount(2, 'data.board');
        $this->get('/leaderboard')->assertOk()->assertSeeInOrder(["Today's challenge", 'Made', 'Fell']);
    }
}
