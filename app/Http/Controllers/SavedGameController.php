<?php

namespace App\Http\Controllers;

use App\Models\SavedGame;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in player's campaign save. The browser keeps its own copy in
 * localStorage; this is the copy that follows them between devices.
 */
class SavedGameController extends Controller
{
    private const MAX_STATE_BYTES = 512 * 1024;

    public function show(Request $request): JsonResponse
    {
        $save = $request->user()->savedGame;

        return response()->json([
            'data' => $save?->state,
            'updated_at' => $save?->updated_at?->toIso8601String(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['state' => ['required', 'array']]);
        if (strlen(json_encode($data['state'])) > self::MAX_STATE_BYTES) {
            return response()->json(['message' => 'Save is too large.'], 422);
        }

        $save = SavedGame::updateOrCreate(['user_id' => $request->user()->id], ['state' => $data['state']]);

        return response()->json(['ok' => true, 'updated_at' => $save->updated_at->toIso8601String()]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->user()->savedGame?->delete();

        return response()->json(['ok' => true]);
    }
}
