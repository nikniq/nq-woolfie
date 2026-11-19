<?php

namespace App\Services;

use App\Models\GameRun;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Activity figures for a period, shared by the admin pages and the weekly email.
 */
class AdminReport
{
    /**
     * @return array{from: CarbonInterface, to: CarbonInterface, totals: array<string, int>, topPlayers: list<array{player_name: string, runs: int, best: int}>, rooms: list<array{name: string, runs: int, completed: int, rate: int}>, sources: list<array{source: string, visits: int, signups: int}>}
     */
    public function forPeriod(CarbonInterface $from, CarbonInterface $to): array
    {
        $runs = GameRun::whereBetween('game_runs.created_at', [$from, $to]);

        return [
            'from' => $from,
            'to' => $to,
            'totals' => [
                'Visits' => Visit::whereBetween('created_at', [$from, $to])->count(),
                'New users' => User::whereBetween('created_at', [$from, $to])->count(),
                'Game runs' => (clone $runs)->count(),
                'Rooms cleared' => (clone $runs)->where('outcome', GameRun::OUTCOME_COMPLETED)->count(),
                'Active players' => (clone $runs)->distinct()->count('player_name'),
            ],
            'topPlayers' => (clone $runs)->select('player_name', DB::raw('count(*) as runs'), DB::raw('max(score) as best'))
                ->groupBy('player_name')->orderByDesc('runs')->limit(10)->get()->toArray(),
            'rooms' => (clone $runs)->join('levels', 'levels.id', '=', 'game_runs.level_id')
                ->select('levels.number', 'levels.name', DB::raw('count(*) as runs'), DB::raw("sum(case when outcome = 'completed' then 1 else 0 end) as completed"))
                ->groupBy('levels.number', 'levels.name')->orderBy('levels.number')->get()
                ->map(fn ($r): array => ['name' => "{$r->number}. {$r->name}", 'runs' => (int) $r->runs, 'completed' => (int) $r->completed, 'rate' => $r->runs ? (int) round($r->completed / $r->runs * 100) : 0])->all(),
            'sources' => $this->sources($from, $to),
        ];
    }

    /** @return list<array{source: string, visits: int, signups: int}> */
    public function sources(CarbonInterface $from, CarbonInterface $to): array
    {
        $visits = Visit::whereBetween('created_at', [$from, $to])->select('source', DB::raw('count(*) as n'))->groupBy('source')->pluck('n', 'source');
        $signups = User::whereBetween('created_at', [$from, $to])->select(DB::raw("coalesce(signup_source, 'unknown') as source"), DB::raw('count(*) as n'))->groupBy('source')->pluck('n', 'source');

        return $visits->keys()->merge($signups->keys())->unique()
            ->map(fn (string $s): array => ['source' => $s, 'visits' => (int) ($visits[$s] ?? 0), 'signups' => (int) ($signups[$s] ?? 0)])
            ->sortByDesc('visits')->values()->all();
    }

    /**
     * Daily counts for a model over the last N days, oldest first.
     *
     * @param  class-string<Model>  $model
     * @return array<string, int>
     */
    public function perDay(string $model, int $days): array
    {
        $rows = $model::where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->select(DB::raw('date(created_at) as day'), DB::raw('count(*) as n'))->groupBy('day')->pluck('n', 'day');

        return collect(range($days - 1, 0))->mapWithKeys(fn (int $d): array => [$k = now()->subDays($d)->toDateString() => (int) ($rows[$k] ?? 0)])->all();
    }
}
