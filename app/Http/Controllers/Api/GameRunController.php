<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGameRunRequest;
use App\Models\GameRun;
use Illuminate\Http\JsonResponse;

class GameRunController extends Controller
{
    public function store(StoreGameRunRequest $request): JsonResponse
    {
        $run = GameRun::create($request->validated());

        $best = GameRun::query()
            ->where('level_id', $run->level_id)
            ->where('outcome', GameRun::OUTCOME_COMPLETED)
            ->min('time_ms');

        return response()->json([
            'data' => $run,
            'best_time_ms' => $best,
            'is_personal_best' => $run->outcome === GameRun::OUTCOME_COMPLETED && $best === $run->time_ms,
        ], 201);
    }
}
