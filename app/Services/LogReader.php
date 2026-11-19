<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Reads recent entries from the Laravel log files (single or daily channel).
 */
class LogReader
{
    public const LEVELS = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

    private const ERROR_LEVELS = ['emergency', 'alert', 'critical', 'error'];

    /** How much of the end of each file to read, so huge logs stay fast. */
    private const TAIL_BYTES = 2 * 1024 * 1024;

    /**
     * Newest entries first.
     *
     * @return list<array{at: Carbon, level: string, message: string, context: string}>
     */
    public function entries(?string $level = null, ?string $search = null, int $limit = 200): array
    {
        $out = [];
        foreach ($this->files() as $file) {
            foreach (array_reverse($this->parse($file)) as $entry) {
                if ($level && $entry['level'] !== $level) {
                    continue;
                }
                if ($search && ! str_contains(strtolower($entry['message'].$entry['context']), strtolower($search))) {
                    continue;
                }
                $out[] = $entry;
                if (count($out) >= $limit) {
                    return $out;
                }
            }
        }

        return $out;
    }

    public function errorCountSince(CarbonInterface $since): int
    {
        return collect($this->entries(limit: 10000))
            ->filter(fn (array $e): bool => $e['at']->gte($since) && in_array($e['level'], self::ERROR_LEVELS, true))
            ->count();
    }

    /**
     * Error count per day for the last N days, oldest first.
     *
     * @return array<string, int>
     */
    public function errorsPerDay(int $days): array
    {
        $counts = array_fill_keys(collect(range($days - 1, 0))->map(fn (int $d): string => now()->subDays($d)->toDateString())->all(), 0);
        foreach ($this->entries(limit: 20000) as $e) {
            $day = $e['at']->toDateString();
            if (isset($counts[$day]) && in_array($e['level'], self::ERROR_LEVELS, true)) {
                $counts[$day]++;
            }
        }

        return $counts;
    }

    /** @return list<string> newest file first */
    private function files(): array
    {
        $files = glob(storage_path('logs/laravel*.log')) ?: [];
        usort($files, fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return $files;
    }

    /** @return list<array{at: Carbon, level: string, message: string, context: string}> oldest first */
    private function parse(string $file): array
    {
        $size = filesize($file);
        $handle = fopen($file, 'r');
        if (! $handle) {
            return [];
        }
        if ($size > self::TAIL_BYTES) {
            fseek($handle, -self::TAIL_BYTES, SEEK_END);
        }
        $raw = stream_get_contents($handle) ?: '';
        fclose($handle);

        $parts = preg_split('/^(?=\[\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})/m', $raw) ?: [];
        $entries = [];
        foreach ($parts as $part) {
            if (! preg_match('/^\[([^\]]+)\] \w+\.(\w+): (.*)/s', $part, $m)) {
                continue;
            }
            [$first, $rest] = array_pad(explode("\n", trim($m[3]), 2), 2, '');
            $entries[] = ['at' => Carbon::parse($m[1]), 'level' => strtolower($m[2]), 'message' => $first, 'context' => $rest];
        }

        return $entries;
    }
}
