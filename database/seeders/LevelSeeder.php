<?php

namespace Database\Seeders;

use App\Models\Level;
use Illuminate\Database\Seeder;
use RuntimeException;

class LevelSeeder extends Seeder
{
    /**
     * Map legend:  # wall   . floor   D locked door   K key   E exit
     *              $ treasure   H hiding spot   P player start
     *              a-z portal: a doorway into another map. The same letter in
     *              two different levels links them in both directions.
     *              S spike plate (timed)   T tripwire (sprinting triggers alarm)
     *              X trapdoor (drops you to the room below)   G gas vent (reverses controls)
     *              C crumbling floor (becomes a pit once you step off)
     *
     * 'traps' lists moving hazards: ['type' => 'blade', 'from' => [x,y], 'to' => [x,y], 'spd' => px/frame]
     *
     * 'castle' => [column, row] places the map on the castle overview; the
     * maps together form one castle, exits lead to the next room in order.
     *
     * Every map is checked at seed time: the key must be reachable without
     * passing a door, the exit must NOT be reachable without the key, and
     * guard patrol points must be on walkable tiles.
     */
    private const CHARS = [
        '#' => Level::TILE_WALL,
        '.' => Level::TILE_FLOOR,
        'D' => Level::TILE_DOOR,
        'K' => Level::TILE_KEY,
        'E' => Level::TILE_EXIT,
        '$' => Level::TILE_TREASURE,
        'H' => Level::TILE_HIDE,
        'P' => Level::TILE_FLOOR,
        'S' => Level::TILE_SPIKES,
        'T' => Level::TILE_TRIPWIRE,
        'X' => Level::TILE_TRAPDOOR,
        'G' => Level::TILE_GAS,
        'C' => Level::TILE_CRUMBLE,
    ];

    public function run(): void
    {
        $levels = [
            [
                'number' => 1,
                'name' => 'The Cellar',
                'castle' => [0, 2],
                'ascii' => [
                    '####################',
                    '#P.......#.....$...#',
                    '#.##.###.#.###.##.##',
                    '#.#....#...#...#..K#',
                    '#.#.##.###.#.#.#.###',
                    '#..S.#...#...#...#.#',
                    '####.###.###.###.#.#',
                    '#$.#...#$..#.$.#.D.#',
                    '#....#...#...#...#E#',
                    '####################',
                ],
                'guards' => [
                    ['patrol' => [[10, 1], [10, 3], [8, 3], [8, 1], [8, 3], [10, 3]], 'spd' => 1.3],
                    ['patrol' => [[12, 7], [12, 5], [10, 5], [12, 5]], 'spd' => 1.1],
                ],
            ],
            [
                'number' => 2,
                'name' => 'The Barracks',
                'castle' => [1, 2],
                'traps' => [
                    ['type' => 'blade', 'from' => [8, 7], 'to' => [13, 7], 'spd' => 2.2],
                ],
                'ascii' => [
                    '####################',
                    '#P...H.......#.$...#',
                    '#.##.##T####.#.###.#',
                    '#.#..$#.#..#...#..a#',
                    '#.#.###.#.##.###.#.#',
                    '#.#.....#.G..H...#$#',
                    '#.#####.#######.##D#',
                    '#.....$.......#.#..#',
                    '#.###########.#K#.E#',
                    '####################',
                ],
                'guards' => [
                    ['patrol' => [[7, 1], [12, 1], [12, 3], [12, 1]], 'spd' => 1.5],
                    ['patrol' => [[4, 5], [7, 5], [7, 7], [1, 7], [7, 7], [7, 5]], 'spd' => 1.2],
                    ['patrol' => [[15, 5], [15, 7]], 'spd' => 1.0],
                ],
            ],
            [
                'number' => 3,
                'name' => 'The Armoury',
                'castle' => [0, 1],
                'ascii' => [
                    '####################',
                    '#P..#....$...#....K#',
                    '#.#.#.######.#.#.#.#',
                    '#.#...#H...#.#.#$#.#',
                    '#.###.#.##.#...#.#.#',
                    '#...#.#..#.#####.#.#',
                    '###.#.##C#.#...#.#.#',
                    '#$..#....#.#.#######',
                    '#b#######H...DE$...#',
                    '####################',
                ],
                'guards' => [
                    ['patrol' => [[5, 1], [12, 1]], 'spd' => 1.7],
                    ['patrol' => [[10, 3], [10, 8]], 'spd' => 1.3],
                    ['patrol' => [[18, 1], [18, 6]], 'spd' => 1.4],
                    ['patrol' => [[12, 6], [14, 6]], 'spd' => 1.1],
                ],
            ],
            [
                'number' => 4,
                'name' => 'The Chapel',
                'castle' => [1, 1],
                'ascii' => [
                    '####################',
                    '#...$#......H#...DE#',
                    '#.#..#.####.#..##.##',
                    '#.#.##.#$.#.##.#.###',
                    '#P#....#..#......$.#',
                    '#.##.###.##.#.######',
                    '#..#.......#.S.H..a#',
                    '##.#.#####.###.###.#',
                    '#$...#..K$.X.#...#.#',
                    '####################',
                ],
                'guards' => [
                    ['patrol' => [[6, 1], [12, 1]], 'spd' => 1.5],
                    ['patrol' => [[4, 6], [10, 6]], 'spd' => 1.8],
                    ['patrol' => [[13, 4], [16, 4], [14, 4], [14, 1], [14, 4]], 'spd' => 1.2],
                    ['patrol' => [[14, 8], [16, 8]], 'spd' => 1.0],
                ],
            ],
            [
                'number' => 5,
                'name' => 'The Keep',
                'castle' => [1, 0],
                'traps' => [
                    ['type' => 'blade', 'from' => [7, 5], 'to' => [11, 5], 'spd' => 2.6],
                    ['type' => 'blade', 'from' => [1, 8], 'to' => [7, 8], 'spd' => 1.8],
                ],
                'ascii' => [
                    '####################',
                    '#P.H.....#$....#..K#',
                    '#.###T##.#.###.#.#.#',
                    '#.#...#..#...#...#.#',
                    '#..b#.#.###.#####.S#',
                    '#.###.#..G..#..$...#',
                    '#.$.H...#...#.######',
                    '#.#####.#.###.#..$E#',
                    '#.......#.....D..H.#',
                    '####################',
                ],
                'guards' => [
                    ['patrol' => [[5, 1], [8, 1], [8, 3], [8, 1]], 'spd' => 1.6],
                    ['patrol' => [[11, 3], [11, 5]], 'spd' => 1.4],
                    ['patrol' => [[16, 5], [18, 5], [18, 2], [18, 5]], 'spd' => 1.5],
                    ['patrol' => [[1, 8], [7, 8]], 'spd' => 1.2],
                    ['patrol' => [[9, 8], [13, 8]], 'spd' => 1.3],
                ],
            ],
        ];

        $errors = [];
        $parsedLevels = [];
        $portalOwners = []; // letter => [level numbers]
        foreach ($levels as $level) {
            try {
                $parsed = $this->parse($level['ascii'], $level['number']);
                $this->validate($parsed['map'], $parsed['start'], $level['guards'], $level['number'], $level['traps'] ?? []);
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();

                continue;
            }
            foreach ($parsed['portals'] as $portal) {
                $portalOwners[$portal['id']][] = $level['number'];
            }
            $parsedLevels[$level['number']] = $parsed + ['level' => $level];
        }

        foreach ($portalOwners as $id => $owners) {
            if (count($owners) !== 2 || $owners[0] === $owners[1]) {
                $errors[] = "Portal '$id' must appear in exactly two different levels (found in: ".implode(', ', $owners).')';
            }
        }

        if ($errors) {
            throw new RuntimeException(implode("\n", $errors));
        }

        foreach ($parsedLevels as $number => $parsed) {
            $portals = array_map(function (array $p) use ($portalOwners, $number) {
                $p['to_level'] = $portalOwners[$p['id']][0] === $number ? $portalOwners[$p['id']][1] : $portalOwners[$p['id']][0];

                return $p;
            }, $parsed['portals']);

            Level::updateOrCreate(['number' => $number], [
                'name' => $parsed['level']['name'],
                'map' => $parsed['map'],
                'player_start' => $parsed['start'],
                'guards' => $parsed['level']['guards'],
                'portals' => $portals,
                'traps' => $parsed['level']['traps'] ?? [],
                'castle_x' => $parsed['level']['castle'][0],
                'castle_y' => $parsed['level']['castle'][1],
            ]);
        }

        Level::whereNotIn('number', array_column($levels, 'number'))->delete();
    }

    /** @return array{map: int[][], start: int[], portals: array<int, array{id: string, x: int, y: int}>} */
    private function parse(array $rows, int $n): array
    {
        $map = [];
        $portals = [];
        $start = null;
        $width = strlen($rows[0]);
        foreach ($rows as $y => $row) {
            if (strlen($row) !== $width) {
                throw new RuntimeException("Level $n: row $y is not $width wide");
            }
            foreach (str_split($row) as $x => $ch) {
                if (ctype_lower($ch)) {
                    $map[$y][$x] = Level::TILE_PORTAL;
                    $portals[] = ['id' => $ch, 'x' => $x, 'y' => $y];

                    continue;
                }
                if (! array_key_exists($ch, self::CHARS)) {
                    throw new RuntimeException("Level $n: unknown tile '$ch' at ($x,$y)");
                }
                $map[$y][$x] = self::CHARS[$ch];
                if ($ch === 'P') {
                    $start = [$x, $y];
                }
            }
        }
        if ($start === null) {
            throw new RuntimeException("Level $n: no player start");
        }

        return ['map' => $map, 'start' => $start, 'portals' => $portals];
    }

    private function validate(array $map, array $start, array $guards, int $n, array $traps = []): void
    {
        $find = function (int $tile) use ($map) {
            foreach ($map as $y => $row) {
                foreach ($row as $x => $t) {
                    if ($t === $tile) {
                        return [$x, $y];
                    }
                }
            }

            return null;
        };
        $key = $find(Level::TILE_KEY) ?? throw new RuntimeException("Level $n: no key");
        $exit = $find(Level::TILE_EXIT) ?? throw new RuntimeException("Level $n: no exit");
        $find(Level::TILE_DOOR) ?? throw new RuntimeException("Level $n: no door");

        $problems = [];
        $withoutDoors = $this->reachable($map, $start, false);
        $withDoors = $this->reachable($map, $start, true);

        if (! isset($withoutDoors["{$key[0]},{$key[1]}"])) {
            $problems[] = "Level $n: key is not reachable from the start";
        }
        if (isset($withoutDoors["{$exit[0]},{$exit[1]}"])) {
            $problems[] = "Level $n: exit is reachable without the key";
        }
        if (! isset($withDoors["{$exit[0]},{$exit[1]}"])) {
            $problems[] = "Level $n: exit is not reachable even with the key";
        }
        foreach ($map as $y => $row) {
            foreach ($row as $x => $t) {
                if ($t === Level::TILE_TREASURE && ! isset($withDoors["$x,$y"])) {
                    $problems[] = "Level $n: treasure at ($x,$y) is unreachable";
                }
            }
        }
        foreach ($guards as $i => $g) {
            $points = $g['patrol'];
            $count = count($points);
            foreach ($points as $k => [$x, $y]) {
                [$nx, $ny] = $points[($k + 1) % $count];
                if ($x !== $nx && $y !== $ny) {
                    $problems[] = "Level $n: guard $i patrol leg ($x,$y)->($nx,$ny) is not a straight line";

                    continue;
                }
                $steps = max(abs($nx - $x), abs($ny - $y));
                for ($st = 0; $st <= $steps; $st++) {
                    $cx = $x + ($steps ? intdiv(($nx - $x) * $st, $steps) : 0);
                    $cy = $y + ($steps ? intdiv(($ny - $y) * $st, $steps) : 0);
                    $t = $map[$cy][$cx] ?? Level::TILE_WALL;
                    if ($t === Level::TILE_WALL || $t === Level::TILE_DOOR) {
                        $problems[] = "Level $n: guard $i patrol crosses a wall/door at ($cx,$cy)";
                        break;
                    }
                }
            }
        }

        foreach ($traps as $i => $trap) {
            [$x, $y] = $trap['from'];
            [$nx, $ny] = $trap['to'];
            if ($x !== $nx && $y !== $ny) {
                $problems[] = "Level $n: trap $i must move in a straight line";

                continue;
            }
            $steps = max(abs($nx - $x), abs($ny - $y));
            for ($st = 0; $st <= $steps; $st++) {
                $cx = $x + ($steps ? intdiv(($nx - $x) * $st, $steps) : 0);
                $cy = $y + ($steps ? intdiv(($ny - $y) * $st, $steps) : 0);
                $t = $map[$cy][$cx] ?? Level::TILE_WALL;
                if ($t === Level::TILE_WALL || $t === Level::TILE_DOOR) {
                    $problems[] = "Level $n: trap $i path crosses a wall/door at ($cx,$cy)";
                    break;
                }
            }
        }

        if ($problems) {
            throw new RuntimeException(implode("\n", $problems));
        }
    }

    /** @return array<string, true> */
    private function reachable(array $map, array $from, bool $openDoors): array
    {
        $seen = ["{$from[0]},{$from[1]}" => true];
        $queue = [$from];
        while ($queue) {
            [$x, $y] = array_shift($queue);
            foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                $nx = $x + $dx;
                $ny = $y + $dy;
                $t = $map[$ny][$nx] ?? Level::TILE_WALL;
                if ($t === Level::TILE_WALL || $t === Level::TILE_TRAPDOOR || ($t === Level::TILE_DOOR && ! $openDoors) || isset($seen["$nx,$ny"])) {
                    continue;
                }
                $seen["$nx,$ny"] = true;
                $queue[] = [$nx, $ny];
            }
        }

        return $seen;
    }
}
