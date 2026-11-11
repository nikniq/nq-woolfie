<?php

namespace Tests\Feature;

use App\Models\GameRun;
use App\Models\Level;
use App\Models\User;
use Database\Seeders\LevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LevelApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_levels_are_listed_in_order_with_key_and_door(): void
    {
        $this->seed(LevelSeeder::class);

        $response = $this->getJson('/api/levels');

        $response->assertOk()->assertJsonCount(12, 'data');
        $this->assertSame(range(1, 12), array_column($response->json('data'), 'number'));

        foreach ($response->json('data') as $level) {
            $flat = array_merge(...$level['map']);
            $this->assertContains(Level::TILE_KEY, $flat, "Level {$level['number']} has no key");
            $this->assertContains(Level::TILE_DOOR, $flat, "Level {$level['number']} has no door");
            $this->assertContains(Level::TILE_EXIT, $flat, "Level {$level['number']} has no exit");
            $this->assertContains(Level::TILE_TREASURE, $flat, "Level {$level['number']} has no treasure");
        }
    }

    public function test_single_level_can_be_fetched(): void
    {
        $this->seed(LevelSeeder::class);
        $level = Level::where('number', 2)->firstOrFail();

        $this->getJson("/api/levels/{$level->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'The Barracks')
            ->assertJsonPath('data.player_start', [1, 1]);
    }

    public function test_game_page_renders(): void
    {
        $this->seed(LevelSeeder::class);

        $this->get('/')->assertOk()->assertSee('id="game"', false)->assertSee('/ 12');
    }

    public function test_reseeding_moves_a_renumbered_room_without_replacing_its_row(): void
    {
        $this->seed(LevelSeeder::class);
        $warRoom = Level::where('name', 'The War Room')->firstOrFail();
        Level::where('number', 8)->delete();
        $warRoom->update(['number' => 8]);

        $this->seed(LevelSeeder::class);

        $this->assertSame(12, $warRoom->fresh()->number);
        $this->assertSame('The Crypt', Level::where('number', 8)->firstOrFail()->name);
        $this->assertSame(12, Level::count());
    }

    public function test_levels_carry_the_fastest_times_for_everyone_and_for_the_signed_in_player(): void
    {
        $this->seed(LevelSeeder::class);
        $level = Level::where('number', 1)->firstOrFail();
        $me = User::factory()->create();
        GameRun::create(['level_id' => $level->id, 'player_name' => 'Vera', 'outcome' => 'completed', 'time_ms' => 8000, 'score' => 350, 'difficulty' => 'normal']);
        GameRun::create(['user_id' => $me->id, 'level_id' => $level->id, 'player_name' => $me->name, 'outcome' => 'completed', 'time_ms' => 9500, 'score' => 300, 'difficulty' => 'normal']);
        GameRun::create(['user_id' => $me->id, 'level_id' => $level->id, 'player_name' => $me->name, 'outcome' => 'caught', 'time_ms' => 1000, 'score' => 0, 'difficulty' => 'normal']);

        $this->getJson('/api/levels')
            ->assertJsonPath('data.0.records.normal', 8000)
            ->assertJsonPath('data.0.my_best', []);

        $this->actingAs($me)->getJson('/api/levels')
            ->assertJsonPath('data.0.records.normal', 8000)
            ->assertJsonPath('data.0.my_best.normal', 9500)
            ->assertJsonPath('data.1.records', []);
    }
}
