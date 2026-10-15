<?php

namespace Database\Seeders;

use App\Models\Level;
use App\Services\LevelValidator;
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
     *              W war plans (required to win)   U pistol   A ammo box
     *              R prisoner cell (free them for a bonus)
     *
     * Objectives are derived from the tiles: every room has "find all gold"
     * and "no alarm"; W adds a required "steal the war plans", R adds
     * "free the prisoner".
     *
     * Guards take an optional 'type': guard (default), officer (faster, sees
     * further, shoots sooner) or dog (fast, hears sprinting from afar, bites
     * on contact, cannot shoot).
     *
     * 'traps' lists moving hazards: ['type' => 'blade', 'from' => [x,y], 'to' => [x,y], 'spd' => px/frame]
     *
     * 'castle' => [column, row] places the map on the castle overview; the
     * maps together form one castle, exits lead to the next room in order.
     *
     * Every map is checked at seed time by App\Services\LevelValidator (the
     * same rules the in-browser editor applies). Rooms edited in the editor
     * are flagged custom and left untouched here.
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
        'W' => Level::TILE_PLANS,
        'U' => Level::TILE_GUN,
        'A' => Level::TILE_AMMO,
        'R' => Level::TILE_PRISONER,
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
                    '#.#..$#.#..#U..#..a#',
                    '#.#.###.#.##.###.#.#',
                    '#.#.....#.G..H...#$#',
                    '#.#####.#######.##D#',
                    '#.....$.......#.#..#',
                    '#.###########.#K#.E#',
                    '####################',
                ],
                'guards' => [
                    ['patrol' => [[7, 1], [12, 1], [12, 3], [12, 1]], 'spd' => 1.5, 'type' => 'officer'],
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
                    '#.#...#H...#.#R#$#.#',
                    '#.###.#.##.#...#.#.#',
                    '#...#.#..#.#####.#.#',
                    '###.#.##C#.#...#A#.#',
                    '#$..#....#.#.#######',
                    '#b#######H...DE$...#',
                    '####################',
                ],
                'guards' => [
                    ['patrol' => [[5, 1], [12, 1]], 'spd' => 1.7],
                    ['patrol' => [[10, 3], [10, 8]], 'spd' => 1.3],
                    ['patrol' => [[18, 1], [18, 6]], 'spd' => 1.4, 'type' => 'dog'],
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
                    '##A#.#####.###.###.#',
                    '#$...#..K$.X.#...#R#',
                    '####################',
                ],
                'guards' => [
                    ['patrol' => [[6, 1], [12, 1]], 'spd' => 1.5],
                    ['patrol' => [[4, 6], [10, 6]], 'spd' => 1.8, 'type' => 'dog'],
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
                    '#.#...#..#...#...#W#',
                    '#..b#.#.###.#####.S#',
                    '#.###.#..G..#..$...#',
                    '#.$.H...#...#.######',
                    '#.#####.#.###.#..$E#',
                    '#.......#.....D..H.#',
                    '####################',
                ],
                'guards' => [
                    ['patrol' => [[5, 1], [8, 1], [8, 3], [8, 1]], 'spd' => 1.6, 'type' => 'officer'],
                    ['patrol' => [[11, 3], [11, 5]], 'spd' => 1.4],
                    ['patrol' => [[16, 5], [18, 5], [18, 2], [18, 5]], 'spd' => 1.5],
                    ['patrol' => [[1, 8], [7, 8]], 'spd' => 1.2, 'type' => 'dog'],
                    ['patrol' => [[9, 8], [13, 8]], 'spd' => 1.3],
                ],
            ],
        ];

        $validator = new LevelValidator;
        $errors = [];
        $parsedLevels = [];
        $portalsByLevel = [];
        foreach ($levels as $level) {
            try {
                $parsed = $this->parse($level['ascii'], $level['number']);
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();

                continue;
            }
            $errors = array_merge($errors, $validator->validate($parsed['map'], $parsed['start'], $level['guards'], $level['traps'] ?? [], "Level {$level['number']}"));
            $portalsByLevel[$level['number']] = array_column($parsed['portals'], 'id');
            $parsedLevels[$level['number']] = $parsed + ['level' => $level];
        }
        $errors = array_merge($errors, $validator->validatePortals($portalsByLevel));

        if ($errors) {
            throw new RuntimeException(implode("\n", $errors));
        }

        foreach ($parsedLevels as $number => $parsed) {
            $existing = Level::where('number', $number)->first();
            if ($existing?->custom) {
                continue; // edited in the editor; keep the author's version
            }

            Level::updateOrCreate(['number' => $number], [
                'objectives' => LevelValidator::objectivesFor($parsed['map']),
                'name' => $parsed['level']['name'],
                'map' => $parsed['map'],
                'player_start' => $parsed['start'],
                'guards' => $parsed['level']['guards'],
                'portals' => $parsed['portals'],
                'traps' => $parsed['level']['traps'] ?? [],
                'castle_x' => $parsed['level']['castle'][0],
                'castle_y' => $parsed['level']['castle'][1],
            ]);
        }

        Level::whereNotIn('number', array_column($levels, 'number'))->where('custom', false)->delete();
        LevelValidator::relinkPortals();
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
}
