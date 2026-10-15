<?php

namespace Tests\Feature;

use App\Models\Level;
use Database\Seeders\LevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['game.editor_password' => 'secret']);
        $this->seed(LevelSeeder::class);
    }

    public function test_editor_is_disabled_without_a_password(): void
    {
        config(['game.editor_password' => '']);
        $this->get('/editor')->assertForbidden();
        $this->get('/editor/login')->assertForbidden();
    }

    public function test_editor_requires_login(): void
    {
        $this->get('/editor')->assertRedirect('/editor/login');
        $this->post('/editor/login', ['password' => 'nope'])->assertSessionHasErrors('password');
        $this->post('/editor/login', ['password' => 'secret'])->assertRedirect('/editor');
        $this->withSession(['editor_ok' => true])->get('/editor')->assertOk()->assertSee('The Keep');
    }

    public function test_saving_a_room_validates_with_the_shared_rules(): void
    {
        $level = Level::where('number', 1)->firstOrFail();
        $payload = $this->payloadFor($level);

        // exit reachable without the key: replace the locked door with floor
        $broken = $payload;
        foreach ($broken['map'] as $y => $row) {
            foreach ($row as $x => $t) {
                if ($t === Level::TILE_DOOR) {
                    $broken['map'][$y][$x] = Level::TILE_FLOOR;
                }
            }
        }
        $this->withSession(['editor_ok' => true])->putJson("/editor/{$level->id}", $broken)
            ->assertStatus(422)
            ->assertJsonFragment(['Room: no locked door']);

        // a valid edit is stored, flagged custom, and survives a reseed
        $payload['name'] = 'The Wine Cellar';
        $this->withSession(['editor_ok' => true])->putJson("/editor/{$level->id}", $payload)
            ->assertOk()->assertJsonPath('number', 1);
        $this->assertTrue($level->fresh()->custom);
        $this->seed(LevelSeeder::class);
        $this->assertSame('The Wine Cellar', $level->fresh()->name);
    }

    public function test_new_room_portal_must_pair_with_another_room(): void
    {
        $level = Level::where('number', 1)->firstOrFail();
        $payload = $this->payloadFor($level);
        $payload['number'] = 9;
        $payload['name'] = 'Oubliette';
        $payload['map'][1][2] = Level::TILE_PORTAL;
        $payload['portals'] = [['id' => 'z', 'x' => 2, 'y' => 1]];

        $this->withSession(['editor_ok' => true])->postJson('/editor', $payload)
            ->assertStatus(422)
            ->assertJsonFragment(["Portal 'z' must appear in exactly two different rooms (found in: 9)"]);

        // pairing with the Keep's orphaned letter would need that room to have it; use letter 'a' which already pairs 2<->4
        $payload['portals'] = [['id' => 'a', 'x' => 2, 'y' => 1]];
        $this->withSession(['editor_ok' => true])->postJson('/editor', $payload)->assertStatus(422);

        $payload['map'][1][2] = Level::TILE_FLOOR;
        $payload['portals'] = [];
        $this->withSession(['editor_ok' => true])->postJson('/editor', $payload)->assertOk();
        $this->assertDatabaseHas('levels', ['number' => 9, 'name' => 'Oubliette', 'custom' => true]);
        $this->getJson('/api/levels')->assertJsonCount(6, 'data');
    }

    private function payloadFor(Level $level): array
    {
        return [
            'number' => $level->number, 'name' => $level->name, 'map' => $level->map, 'player_start' => $level->player_start,
            'guards' => $level->guards, 'traps' => $level->traps ?? [], 'portals' => array_map(fn ($p) => ['id' => $p['id'], 'x' => $p['x'], 'y' => $p['y']], $level->portals ?? []),
            'castle_x' => $level->castle_x, 'castle_y' => $level->castle_y,
        ];
    }
}
