<?php

namespace Tests\Feature;

use App\Models\Achievement;
use Database\Seeders\LevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignResultApiTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $over = []): array
    {
        return $over + [
            'campaign' => '33333333-3333-4333-8333-333333333333', 'player_name' => 'BJ', 'difficulty' => 'hard', 'outcome' => 'escaped',
            'score' => 4200, 'time_ms' => 240000, 'kills' => 0, 'alarms' => 0, 'lives_lost' => 0, 'stones' => 2,
            'rooms_cleared' => 5, 'rooms_total' => 5, 'all_gold' => true, 'all_prisoners' => false, 'disguised_at_end' => true,
        ];
    }

    public function test_a_clean_hard_escape_earns_the_matching_achievements_once(): void
    {
        $res = $this->postJson('/api/campaigns', $this->payload())->assertCreated();
        $earned = collect($res->json('earned'))->pluck('key')->all();
        sort($earned);
        $this->assertSame(['chameleon', 'escapee', 'ghost', 'hardened', 'master_thief', 'pacifist', 'speedrunner', 'untouchable'], $earned);

        // the same campaign reported again does not duplicate anything
        $this->postJson('/api/campaigns', $this->payload())->assertCreated()->assertJsonCount(0, 'earned');
        $this->assertSame(8, Achievement::where('player_name', 'BJ')->count());
        $this->assertDatabaseCount('campaign_results', 1);
    }

    public function test_a_failed_campaign_can_still_earn_marksman(): void
    {
        $this->postJson('/api/campaigns', $this->payload(['outcome' => 'failed', 'kills' => 6, 'rooms_cleared' => 2]))
            ->assertCreated()->assertJsonPath('earned.0.key', 'marksman')->assertJsonCount(1, 'earned');
    }

    public function test_hall_of_fame_lists_players_and_badges(): void
    {
        $this->seed(LevelSeeder::class);
        $this->postJson('/api/campaigns', $this->payload(['player_name' => 'Ghostly']))->assertCreated();
        $this->get('/leaderboard')->assertOk()->assertSeeInOrder(['Hall of fame', 'Ghostly', 'Ghost']);
    }

    public function test_result_is_validated(): void
    {
        $this->postJson('/api/campaigns', ['campaign' => 'nope', 'outcome' => 'won'])
            ->assertUnprocessable()->assertJsonValidationErrors(['campaign', 'outcome', 'score']);
    }

    public function test_hiding_three_bodies_earns_undertaker(): void
    {
        $this->postJson('/api/campaigns', $this->payload(['outcome' => 'failed', 'bodies_hidden' => 3, 'campaign' => '55555555-5555-4555-8555-555555555555']))
            ->assertCreated()->assertJsonPath('earned.0.key', 'undertaker');
    }
}
