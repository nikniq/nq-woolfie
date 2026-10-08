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

        $response->assertOk()->assertJsonCount(3, 'data');
        $this->assertSame([1, 2, 3], array_column($response->json('data'), 'number'));

        foreach ($response->json('data') as $level) {
            $flat = array_merge(...$level['map']);
            $this->assertContains(Level::TILE_KEY, $flat, "Level {$level['number']} has no key");
            $this->assertContains(Level::TILE_DOOR, $flat, "Level {$level['number']} has no door");
            $this->assertContains(Level::TILE_EXIT, $flat, "Level {$level['number']} has no exit");
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

        $this->get('/')->assertOk()->assertSee('id="game"', false)->assertSee('/ 3');
    }
}
