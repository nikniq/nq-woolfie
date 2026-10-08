<?php

namespace Database\Seeders;

use App\Models\Level;
use Illuminate\Database\Seeder;

class LevelSeeder extends Seeder
{
    /**
     * The three original levels from the static demo. Each map is 20x10;
     * 0 floor, 1 wall, 2 locked door, 3 key, 4 exit.
     */
    public function run(): void
    {
        $baseMap = [
            [1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1],
            [1, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 0, 0, 3, 0, 1],
            [1, 0, 1, 1, 0, 1, 1, 1, 0, 1, 0, 1, 1, 1, 0, 1, 1, 1, 0, 1],
            [1, 0, 1, 0, 0, 0, 0, 1, 0, 0, 0, 1, 0, 0, 0, 1, 0, 0, 0, 1],
            [1, 0, 1, 0, 1, 1, 0, 1, 1, 1, 0, 1, 0, 1, 0, 1, 0, 1, 0, 1],
            [1, 0, 0, 0, 0, 1, 0, 0, 0, 1, 0, 0, 0, 1, 0, 0, 0, 1, 0, 1],
            [1, 1, 1, 1, 0, 1, 1, 1, 0, 1, 1, 1, 0, 1, 1, 1, 0, 1, 0, 1],
            [1, 0, 0, 1, 0, 0, 0, 1, 0, 0, 0, 1, 0, 0, 0, 1, 0, 0, 0, 1],
            [1, 0, 0, 0, 0, 1, 0, 0, 0, 1, 0, 0, 0, 1, 0, 0, 0, 0, 4, 1],
            [1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1],
        ];

        $level2Map = $baseMap;
        $level2Map[1] = [1, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 3, 0, 0, 0, 1];

        $levels = [
            [
                'number' => 1,
                'name' => 'The Cellar',
                'map' => $baseMap,
                'player_start' => [1, 1],
                'treasures' => [[4, 3], [13, 3], [9, 7], [16, 5]],
                'guards' => [
                    ['patrol' => [[9, 1], [9, 3], [9, 6], [9, 1]], 'spd' => 1.4],
                    ['patrol' => [[3, 7], [6, 7], [6, 3], [3, 7]], 'spd' => 1.0],
                ],
            ],
            [
                'number' => 2,
                'name' => 'The Barracks',
                'map' => $level2Map,
                'player_start' => [1, 1],
                'treasures' => [[6, 3], [2, 5], [14, 7], [18, 3], [10, 5]],
                'guards' => [
                    ['patrol' => [[5, 1], [9, 1], [9, 4], [5, 4]], 'spd' => 1.6],
                    ['patrol' => [[2, 7], [2, 3], [7, 3], [7, 7]], 'spd' => 1.0],
                ],
            ],
            [
                'number' => 3,
                'name' => 'The Keep',
                'map' => $baseMap,
                'player_start' => [1, 1],
                'treasures' => [[3, 3], [8, 3], [12, 7], [16, 3], [6, 7], [18, 5]],
                'guards' => [
                    ['patrol' => [[9, 1], [9, 6]], 'spd' => 1.8],
                    ['patrol' => [[3, 7], [6, 7]], 'spd' => 1.2],
                    ['patrol' => [[12, 4], [15, 4]], 'spd' => 1.0],
                ],
            ],
        ];

        foreach ($levels as $level) {
            $level['map'] = $this->placeExtras($level['map']);
            foreach ($level['treasures'] as [$x, $y]) {
                if ($level['map'][$y][$x] !== Level::TILE_FLOOR) {
                    throw new \RuntimeException("Level {$level['number']}: treasure at ($x,$y) is not on floor");
                }
                $level['map'][$y][$x] = Level::TILE_TREASURE;
            }
            unset($level['treasures']);

            Level::updateOrCreate(['number' => $level['number']], $level);
        }
    }

    /**
     * Guarantee a key and a locked door on every map, mirroring the
     * placeExtras() helper from the original demo.
     */
    private function placeExtras(array $map): array
    {
        $rows = count($map);
        $cols = count($map[0]);

        $hasKey = false;
        foreach ($map as $row) {
            if (in_array(Level::TILE_KEY, $row, true)) {
                $hasKey = true;
                break;
            }
        }
        if (! $hasKey) {
            $map[1][$cols - 3] = Level::TILE_KEY;
        }

        for ($y = $rows - 2; $y > 0; $y--) {
            for ($x = $cols - 2; $x > 0; $x--) {
                if ($map[$y][$x] === Level::TILE_FLOOR && $map[$y][$x + 1] === Level::TILE_WALL) {
                    $map[$y][$x + 1] = Level::TILE_DOOR;

                    return $map;
                }
            }
        }

        return $map;
    }
}
