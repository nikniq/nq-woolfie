<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use App\Models\CampaignResult;
use App\Models\GameRun;
use App\Models\Level;
use App\Models\User;
use Illuminate\View\View;

class PlayerController extends Controller
{
    public function show(string $name): View
    {
        $name = mb_substr(trim($name), 0, 24);
        abort_if($name === '', 404);

        $results = CampaignResult::where('player_name', $name)->latest()->get();
        $runs = GameRun::where('player_name', $name)->get();
        abort_if($results->isEmpty() && $runs->isEmpty(), 404);

        $catalogue = Achievement::catalogue();
        $held = Achievement::where('player_name', $name)->orderBy('earned_at')->get();
        $escapes = $results->where('outcome', CampaignResult::OUTCOME_ESCAPED);

        $bestRooms = Level::orderBy('number')->get()->map(function (Level $level) use ($runs) {
            $completed = $runs->where('level_id', $level->id)->where('outcome', GameRun::OUTCOME_COMPLETED);

            return [
                'level' => $level,
                'attempts' => $runs->where('level_id', $level->id)->count(),
                'clears' => $completed->count(),
                'best_time' => $completed->min('time_ms'),
                'best_score' => $completed->max('score'),
            ];
        });

        return view('player', [
            'name' => $name,
            'registered' => User::where('name', $name)->exists(),
            'results' => $results,
            'catalogue' => $catalogue,
            'held' => $held,
            'summary' => [
                'campaigns' => $results->count(),
                'escapes' => $escapes->count(),
                'best_score' => $escapes->max('score'),
                'best_time' => $escapes->min('time_ms'),
                'kills' => $results->sum('kills'),
                'alarms' => $results->sum('alarms'),
                'rooms_cleared' => $runs->where('outcome', GameRun::OUTCOME_COMPLETED)->count(),
                'caught' => $runs->where('outcome', GameRun::OUTCOME_CAUGHT)->count(),
            ],
            'bestRooms' => $bestRooms,
        ]);
    }
}
