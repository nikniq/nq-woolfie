<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Level;
use Illuminate\Http\JsonResponse;

class LevelController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Level::orderBy('number')->get(['id', 'number', 'name', 'map', 'player_start', 'guards', 'portals', 'traps', 'castle_x', 'castle_y']),
        ]);
    }

    public function show(Level $level): JsonResponse
    {
        return response()->json([
            'data' => $level->only(['id', 'number', 'name', 'map', 'player_start', 'guards', 'portals', 'traps', 'castle_x', 'castle_y']),
        ]);
    }
}
