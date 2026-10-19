<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CampaignResult;
use App\Services\DailyChallenge;
use Illuminate\Http\JsonResponse;

class DailyController extends Controller
{
    public function show(DailyChallenge $daily): JsonResponse
    {
        $challenge = $daily->forDate();
        $board = CampaignResult::where('daily', $challenge['date'])
            ->orderByRaw("CASE WHEN outcome = 'escaped' THEN 0 ELSE 1 END")
            ->orderByDesc('score')
            ->orderBy('time_ms')
            ->limit(10)
            ->get(['player_name', 'outcome', 'score', 'time_ms']);

        return response()->json(['data' => $challenge + ['board' => $board]]);
    }
}
