<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Visit;
use App\Services\AdminReport;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MarketingController extends Controller
{
    public function index(AdminReport $report): View
    {
        $from = now()->subDays(30)->startOfDay();

        return view('admin.marketing', [
            'sources' => $report->sources($from, now()),
            'campaigns' => Visit::where('created_at', '>=', $from)->whereNotNull('campaign')
                ->select('campaign', 'source', DB::raw('count(*) as visits'))->groupBy('campaign', 'source')->orderByDesc('visits')->limit(20)->get(),
            'landings' => Visit::where('created_at', '>=', $from)->select('landing', DB::raw('count(*) as visits'))->groupBy('landing')->orderByDesc('visits')->limit(10)->get(),
            'totals' => ['visits' => Visit::where('created_at', '>=', $from)->count(), 'signups' => User::where('created_at', '>=', $from)->count()],
            'baseUrl' => rtrim(config('app.url'), '/'),
            'shareText' => 'Sneak past the guards, steal the war plans, escape the castle. Free stealth game in your browser:',
        ]);
    }
}
