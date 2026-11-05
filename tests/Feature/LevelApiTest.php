<?php

namespace Tests\Feature;

use App\Models\Level;
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
}
