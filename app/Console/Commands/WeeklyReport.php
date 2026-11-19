<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AdminReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class WeeklyReport extends Command
{
    protected $signature = 'app:weekly-report {--days=7 : How many days to cover}';

    protected $description = 'Email the activity report to every admin';

    public function handle(AdminReport $reports): int
    {
        $days = max(1, (int) $this->option('days'));
        $report = $reports->forPeriod(now()->subDays($days)->startOfDay(), now());
        $lines = ["Woolfie report: {$report['from']->toFormattedDateString()} to {$report['to']->toFormattedDateString()}", ''];
        foreach ($report['totals'] as $label => $value) {
            $lines[] = str_pad($label, 16).number_format($value);
        }
        $lines[] = '';
        $lines[] = 'Top sources (visits / signups):';
        foreach (array_slice($report['sources'], 0, 5) as $s) {
            $lines[] = "  {$s['source']}: {$s['visits']} / {$s['signups']}";
        }
        $lines[] = '';
        $lines[] = 'Full report: '.route('admin.reports.index', ['days' => $days]);
        $body = implode("\n", $lines);

        $admins = User::where('is_admin', true)->pluck('email');
        if ($admins->isEmpty()) {
            $this->warn('No admins to email. Promote one with app:make-admin.');
            $this->line($body);

            return self::SUCCESS;
        }
        Mail::raw($body, fn ($message) => $message->to($admins->all())->subject('Woolfie weekly report'));
        $this->info("Report sent to {$admins->count()} admin(s).");

        return self::SUCCESS;
    }
}
