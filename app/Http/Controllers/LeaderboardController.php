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

        $campaigns = GameRun::query()
            ->selectRaw('campaign, MAX(player_name) as player_name, SUM(score) as total_score, SUM(time_ms) as total_time, COUNT(DISTINCT level_id) as levels_done, MAX(created_at) as finished_at')
            ->where('outcome', GameRun::OUTCOME_COMPLETED)
            ->whereNotNull('campaign')
            ->groupBy('campaign')
            ->having('levels_done', '=', $levels->count())
            ->orderByDesc('total_score')
            ->orderBy('total_time')
            ->limit(10)
            ->get();

        $stats = [
            'completed' => GameRun::where('outcome', GameRun::OUTCOME_COMPLETED)->count(),
            'caught' => GameRun::where('outcome', GameRun::OUTCOME_CAUGHT)->count(),
        ];

        return view('leaderboard', compact('levels', 'bestRuns', 'campaigns', 'stats'));
    }
}
