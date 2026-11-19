<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GameRun;
use App\Models\User;
use App\Models\Visit;
use App\Services\AdminReport;
use App\Services\LogReader;
use Illuminate\View\View;

class MonitoringController extends Controller
{
    public function index(AdminReport $report, LogReader $logs): View
    {
        return view('admin.monitoring', [
            'series' => [
                'Visits' => $report->perDay(Visit::class, 30),
                'Signups' => $report->perDay(User::class, 30),
                'Game runs' => $report->perDay(GameRun::class, 30),
                'Errors' => $logs->errorsPerDay(30),
            ],
            'recentErrors' => array_slice(array_merge(...array_map(fn (string $l): array => $logs->entries($l, limit: 5), ['emergency', 'alert', 'critical', 'error'])), 0, 5),
        ]);
    }
}
