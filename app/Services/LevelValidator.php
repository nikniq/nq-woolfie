<?php

namespace App\Services;

use App\Models\Level;

/**
 * Rules every room must satisfy, shared by the seeder and the editor:
 * the key is reachable without passing a door, the exit is locked behind
 * one, every pickup is reachable, and guard / blade paths are straight
 * lines over walkable tiles.
 */
class LevelValidator
{
    public const PICKUPS = [Level::TILE_TREASURE, Level::TILE_PLANS, Level::TILE_PRISONER, Level::TILE_GUN, Level::TILE_AMMO];

    /**
     * @param  int[][]  $map
     * @param  int[]  $start
     * @return string[] problems; empty when the room is valid
     */
    public function validate(array $map, array $start, array $guards, array $traps = [], string $label = 'Room'): array
    {
        $problems = [];
        $rows = count($map);
        $cols = $rows ? count($map[0]) : 0;
        if ($rows < 3 || $cols < 3) {
            return ["$label: map must be at least 3x3"];
        }
        foreach ($map as $y => $row) {
            if (count($row) !== $cols) {
                return ["$label: row $y is not $cols wide"];
            }
        }
        [$sx, $sy] = $start + [0, 0];
        if (($map[$sy][$sx] ?? Level::TILE_WALL) === Level::TILE_WALL) {
            $problems[] = "$label: player start ($sx,$sy) is inside a wall";
        }

        $key = $this->find($map, Level::TILE_KEY);
        $exit = $this->find($map, Level::TILE_EXIT);
        $door = $this->find($map, Level::TILE_DOOR);
        if (! $key) {
            $problems[] = "$label: no key";
        }
        if (! $exit) {
            $problems[] = "$label: no exit";
        }
        if (! $door) {
            $problems[] = "$label: no locked door";
        }
        if ($problems) {
            return $problems;
        }

        $withoutDoors = $this->reachable($map, $start, false);
        $withDoors = $this->reachable($map, $start, true);

        if (! isset($withoutDoors["{$key[0]},{$key[1]}"])) {
            $problems[] = "$label: key is not reachable from the start";
        }
        if (isset($withoutDoors["{$exit[0]},{$exit[1]}"])) {
            $problems[] = "$label: exit is reachable without the key";
        }
        if (! isset($withDoors["{$exit[0]},{$exit[1]}"])) {
            $problems[] = "$label: exit is not reachable even with the key";
        }
        foreach ($map as $y => $row) {
            foreach ($row as $x => $t) {
                if (in_array($t, self::PICKUPS, true) && ! isset($withDoors["$x,$y"])) {
                    $problems[] = "$label: pickup at ($x,$y) is unreachable";
                }
                if ($t === Level::TILE_PORTAL && ! isset($withDoors["$x,$y"])) {
                    $problems[] = "$label: portal at ($x,$y) is unreachable";
                }
            }
        }
        foreach ($guards as $i => $g) {
            $points = $g['patrol'] ?? [];
            if (count($points) < 1) {
                $problems[] = "$label: guard $i has no patrol points";

                continue;
            }
            $problems = array_merge($problems, $this->checkPath($map, $points, true, "$label: guard $i patrol"));
        }
        foreach ($traps as $i => $trap) {
            if (($trap['type'] ?? '') !== 'blade') {
                $problems[] = "$label: trap $i has unknown type";

                continue;
            }
            $problems = array_merge($problems, $this->checkPath($map, [$trap['from'], $trap['to']], false, "$label: blade $i path"));
        }

        return $problems;
    }

    /**
     * Portal letters must pair up: each appears in exactly two different rooms.
     *
     * @param  array<int, string[]>  $portalsByLevel  level number => portal ids
     * @return string[]
     */
    public function validatePortals(array $portalsByLevel): array
    {
        $owners = [];
        foreach ($portalsByLevel as $number => $ids) {
            foreach ($ids as $id) {
                $owners[$id][] = $number;
            }
        }
        $problems = [];
        foreach ($owners as $id => $numbers) {
            if (count($numbers) !== 2 || $numbers[0] === $numbers[1]) {
                $problems[] = "Portal '$id' must appear in exactly two different rooms (found in: ".implode(', ', $numbers).')';
            }
        }

        return $problems;
    }

    /** @return string[] */
    private function checkPath(array $map, array $points, bool $loop, string $label): array
    {
        $problems = [];
        $count = count($points);
        $legs = $loop ? $count : $count - 1;
        for ($k = 0; $k < $legs; $k++) {
            [$x, $y] = $points[$k];
            [$nx, $ny] = $points[($k + 1) % $count];
            if ($x !== $nx && $y !== $ny) {
                $problems[] = "$label leg ($x,$y)->($nx,$ny) is not a straight line";

                continue;
            }
            $steps = max(abs($nx - $x), abs($ny - $y));
            for ($st = 0; $st <= $steps; $st++) {
                $cx = $x + ($steps ? intdiv(($nx - $x) * $st, $steps) : 0);
                $cy = $y + ($steps ? intdiv(($ny - $y) * $st, $steps) : 0);
                $t = $map[$cy][$cx] ?? Level::TILE_WALL;
                if ($t === Level::TILE_WALL || $t === Level::TILE_DOOR || $t === Level::TILE_TRAPDOOR) {
                    $problems[] = "$label crosses a wall, door or trapdoor at ($cx,$cy)";
                    break;
                }
            }
        }

        return $problems;
    }

    private function find(array $map, int $tile): ?array
    {
        foreach ($map as $y => $row) {
            foreach ($row as $x => $t) {
                if ($t === $tile) {
                    return [$x, $y];
                }
            }
        }

        return null;
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

    /**
     * Objectives implied by a map's tiles.
     *
     * @return array<int, array{id: string, label: string, points: int, required: bool}>
     */
    public static function objectivesFor(array $map): array
    {
        $flat = array_merge(...$map);
        $objectives = [];
        if (in_array(Level::TILE_PLANS, $flat, true)) {
            $objectives[] = ['id' => 'plans', 'label' => 'Steal the war plans', 'points' => 500, 'required' => true];
        }
        if (in_array(Level::TILE_PRISONER, $flat, true)) {
            $objectives[] = ['id' => 'prisoner', 'label' => 'Free the prisoner', 'points' => 300, 'required' => false];
        }
        if (in_array(Level::TILE_TREASURE, $flat, true)) {
            $objectives[] = ['id' => 'all_gold', 'label' => 'Find all the gold', 'points' => 250, 'required' => false];
        }
        $objectives[] = ['id' => 'no_alarm', 'label' => 'Leave without raising the alarm', 'points' => 200, 'required' => false];

        return $objectives;
    }

    /**
     * Resolve every portal's destination room from the letters shared
     * between rooms, and persist it.
     */
    public static function relinkPortals(): void
    {
        $levels = Level::all();
        $owners = [];
        foreach ($levels as $level) {
            foreach ($level->portals ?? [] as $p) {
                $owners[$p['id']][] = $level->number;
            }
        }
        foreach ($levels as $level) {
            $portals = array_map(function (array $p) use ($owners, $level) {
                $others = array_values(array_filter($owners[$p['id']] ?? [], fn ($n) => $n !== $level->number));
                $p['to_level'] = $others[0] ?? null;

                return $p;
            }, $level->portals ?? []);
            if ($portals !== ($level->portals ?? [])) {
                $level->forceFill(['portals' => $portals])->save();
            }
        }
    }
}
