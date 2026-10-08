<?php

namespace App\Http\Controllers;

use App\Models\GameRun;
use App\Models\Level;
use Illuminate\View\View;

class LeaderboardController extends Controller
{
    public function index(): View
    {
        $levels = Level::orderBy('number')->get();

        $bestRuns = GameRun::query()
            ->where('outcome', GameRun::OUTCOME_COMPLETED)
            ->orderByDesc('score')
            ->orderBy('time_ms')
            ->get()
            ->groupBy('level_id')
            ->map(fn ($runs) => $runs->take(10));

        $stats = [
            'completed' => GameRun::where('outcome', GameRun::OUTCOME_COMPLETED)->count(),
            'caught' => GameRun::where('outcome', GameRun::OUTCOME_CAUGHT)->count(),
        ];

        return view('leaderboard', compact('levels', 'bestRuns', 'stats'));
    }
}
