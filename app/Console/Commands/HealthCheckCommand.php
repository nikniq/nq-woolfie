<?php

namespace App\Console\Commands;

use App\Services\HealthCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class HealthCheckCommand extends Command
{
    protected $signature = 'app:health {--quiet-ok : Print nothing when every check passes}';

    protected $description = 'Run the health checks; exits non-zero and logs a critical entry when any fail';

    public function handle(HealthCheck $health): int
    {
        $results = $health->run();
        $healthy = $health->healthy($results);

        if (! $healthy || ! $this->option('quiet-ok')) {
            $this->table(['Check', 'Status', 'Detail'], array_map(fn (array $r): array => [$r['name'], $r['ok'] ? 'OK' : 'FAIL', $r['detail']], $results));
        }
        if (! $healthy) {
            Log::critical('Health check failing', ['failed' => collect($results)->reject(fn (array $r): bool => $r['ok'])->values()->all()]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
