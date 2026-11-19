<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GameRun;
use App\Models\User;
use App\Models\Visit;
use App\Services\AdminReport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    private const PERIODS = [7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days'];

    public function index(Request $request, AdminReport $report): View
    {
        $days = (int) ($request->validate(['days' => ['nullable', Rule::in(array_keys(self::PERIODS))]])['days'] ?? 7);

        return view('admin.reports', [
            'report' => $report->forPeriod(now()->subDays($days)->startOfDay(), now()),
            'days' => $days,
            'periods' => self::PERIODS,
        ]);
    }

    public function export(string $dataset): StreamedResponse
    {
        [$query, $columns] = match ($dataset) {
            'users' => [User::query(), ['id', 'name', 'email', 'is_admin', 'signup_source', 'signup_medium', 'signup_campaign', 'signup_landing', 'created_at']],
            'runs' => [GameRun::query(), ['id', 'user_id', 'level_id', 'player_name', 'outcome', 'score', 'stars', 'time_ms', 'difficulty', 'campaign', 'created_at']],
            'visits' => [Visit::query(), ['id', 'source', 'medium', 'campaign', 'landing', 'created_at']],
            default => abort(404),
        };

        return response()->streamDownload(function () use ($query, $columns): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);
            $query->select($columns)->orderBy('id')->chunk(1000, function ($rows) use ($out, $columns): void {
                foreach ($rows as $row) {
                    fputcsv($out, array_map(fn (string $c) => $this->safeCell($row->getRawOriginal($c)), $columns));
                }
            });
            fclose($out);
        }, "woolfie-{$dataset}-".now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** Stop spreadsheet apps treating a player-chosen name as a formula. */
    private function safeCell(mixed $value): mixed
    {
        return is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
