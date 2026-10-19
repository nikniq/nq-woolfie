<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use App\Models\CampaignResult;
use App\Models\GameRun;
use App\Models\Level;
use App\Services\DailyChallenge;
use Illuminate\View\View;

class LeaderboardController extends Controller
{
    public function index(DailyChallenge $daily): View
    {
        $challenge = $daily->forDate();
        $dailyBoard = CampaignResult::where('daily', $challenge['date'])
            ->orderByRaw("CASE WHEN outcome = 'escaped' THEN 0 ELSE 1 END")->orderByDesc('score')->orderBy('time_ms')->limit(10)->get();
        $levels = Level::orderBy('number')->get();

        $bestRuns = GameRun::query()
            ->where('outcome', GameRun::OUTCOME_COMPLETED)
            ->orderByDesc('score')
            ->orderBy('time_ms')
            ->get()
            ->groupBy('level_id')
            ->map(fn ($runs) => $runs->take(10));

        $campaigns = GameRun::query()
            ->selectRaw('campaign, MAX(player_name) as player_name, MAX(difficulty) as difficulty, SUM(score) as total_score, SUM(time_ms) as total_time, COUNT(DISTINCT level_id) as levels_done, MAX(created_at) as finished_at')
            ->where('outcome', GameRun::OUTCOME_COMPLETED)
            ->whereNotNull('campaign')
            ->groupBy('campaign')
            ->having('levels_done', '=', $levels->count())
            ->orderByDesc('total_score')
            ->orderBy('total_time')
            ->limit(10)
            ->get();

        $catalogue = Achievement::catalogue();
        $hallOfFame = Achievement::query()
            ->orderBy('earned_at')
            ->get()
            ->groupBy('player_name')
            ->map(fn ($rows) => $rows->pluck('key')->all())
            ->sortByDesc(fn ($keys) => count($keys))
            ->take(20);
        $escapes = CampaignResult::where('outcome', CampaignResult::OUTCOME_ESCAPED)->count();

        $stats = [
            'escapes' => $escapes,
            'completed' => GameRun::where('outcome', GameRun::OUTCOME_COMPLETED)->count(),
            'caught' => GameRun::where('outcome', GameRun::OUTCOME_CAUGHT)->count(),
        ];

        return view('leaderboard', compact('levels', 'bestRuns', 'campaigns', 'stats', 'catalogue', 'hallOfFame', 'challenge', 'dailyBoard'));
    }
}
