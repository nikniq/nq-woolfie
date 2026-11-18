<?php

namespace App\Http\Controllers;

use App\Models\CampaignResult;
use App\Models\GameRun;
use App\Models\Level;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        return view('home', [
            'levels' => Level::orderBy('number')->pluck('name'),
            'stats' => [
                'runs' => GameRun::count(),
                'escapes' => CampaignResult::where('outcome', CampaignResult::OUTCOME_ESCAPED)->count(),
            ],
        ]);
    }
}
