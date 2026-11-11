<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameRun;
use App\Models\Level;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LevelController extends Controller
{
    private const FIELDS = ['id', 'number', 'name', 'map', 'player_start', 'guards', 'portals', 'traps', 'objectives', 'castle_x', 'castle_y'];

    public function index(Request $request): JsonResponse
    {
        $records = $this->bestTimes();
        $mine = $request->user() ? $this->bestTimes($request->user()->id) : [];

        return response()->json([
            'data' => Level::orderBy('number')->get(self::FIELDS)->map(fn (Level $level): array => $level->only(self::FIELDS) + [
                'records' => $records[$level->id] ?? [],
                'my_best' => $mine[$level->id] ?? [],
            ]),
        ]);
    }

    public function show(Level $level): JsonResponse
    {
        return response()->json([
            'data' => $level->only(self::FIELDS),
        ]);
    }

    /**
     * Fastest completed run per room and difficulty, for everyone or for one player.
     *
     * @return array<int, array<string, int>> level id => difficulty => milliseconds
     */
    private function bestTimes(?int $userId = null): array
    {
        $rows = GameRun::query()
            ->where('outcome', 'completed')
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->selectRaw('level_id, difficulty, MIN(time_ms) as best_ms')
            ->groupBy('level_id', 'difficulty')
            ->get();

        $best = [];
        foreach ($rows as $row) {
            $best[$row->level_id][$row->difficulty ?? 'normal'] = (int) $row->best_ms;
        }

        return $best;
    }
}
