<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CampaignResult;
use App\Models\GameRun;
use App\Models\Level;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'stats' => [
                'Users' => User::count(),
                'New users (7 days)' => User::where('created_at', '>=', now()->subDays(7))->count(),
                'Rooms' => Level::count(),
                'Game runs' => GameRun::count(),
                'Runs today' => GameRun::where('created_at', '>=', today())->count(),
                'Campaigns played' => CampaignResult::count(),
            ],
            'recentUsers' => User::latest()->limit(5)->get(),
            'recentRuns' => GameRun::with('level:id,number,name')->latest()->limit(10)->get(),
        ]);
    }
}
