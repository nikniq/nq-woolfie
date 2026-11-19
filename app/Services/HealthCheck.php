<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class HealthCheck
{
    public function __construct(private LogReader $logs) {}

    /**
     * Run every check.
     *
     * @return list<array{name: string, ok: bool, detail: string}>
     */
    public function run(): array
    {
        return [
            $this->check('Database', function (): string {
                DB::select('select 1');

                return DB::connection()->getDriverName().' reachable';
            }),
            $this->check('Cache', function (): string {
                Cache::put('health:ping', $token = (string) microtime(true), 10);
                if (Cache::get('health:ping') !== $token) {
                    throw new \RuntimeException('read back a different value');
                }

                return config('cache.default').' read/write OK';
            }),
            $this->check('Storage', function (): string {
                foreach ([storage_path('logs'), storage_path('framework/cache'), base_path('bootstrap/cache')] as $dir) {
                    if (! is_writable($dir)) {
                        throw new \RuntimeException("{$dir} is not writable");
                    }
                }

                return 'writable';
            }),
            $this->check('Disk space', function (): string {
                $free = @disk_free_space(base_path());
                $total = @disk_total_space(base_path());
                if (! $free || ! $total) {
                    return 'unknown';
                }
                $pct = $free / $total * 100;
                if ($free < 500 * 1024 * 1024) {
                    throw new \RuntimeException(sprintf('only %s free (%.0f%%)', $this->bytes($free), $pct));
                }

                return sprintf('%s free (%.0f%%)', $this->bytes($free), $pct);
            }),
            $this->check('Queue', function (): string {
                if (! Schema::hasTable('jobs')) {
                    return 'not using the database queue';
                }
                $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
                $stale = DB::table('jobs')->where('created_at', '<', now()->subMinutes(15)->timestamp)->count();
                if ($stale > 0) {
                    throw new \RuntimeException("{$stale} jobs waiting over 15 minutes; is a worker running?");
                }

                return DB::table('jobs')->count()." pending, {$failed} failed";
            }),
            $this->check('Errors (24h)', function (): string {
                $errors = $this->logs->errorCountSince(now()->subDay());
                if ($errors >= 50) {
                    throw new \RuntimeException("{$errors} errors logged");
                }

                return "{$errors} logged";
            }),
            $this->check('App config', function (): string {
                if (app()->isProduction() && config('app.debug')) {
                    throw new \RuntimeException('APP_DEBUG is on in production');
                }

                return app()->environment().', PHP '.PHP_VERSION.', Laravel '.app()->version();
            }),
        ];
    }

    /** @param  list<array{ok: bool}>  $results */
    public function healthy(array $results): bool
    {
        return collect($results)->every(fn (array $r): bool => $r['ok']);
    }

    /**
     * @param  callable(): string  $probe
     * @return array{name: string, ok: bool, detail: string}
     */
    private function check(string $name, callable $probe): array
    {
        try {
            return ['name' => $name, 'ok' => true, 'detail' => $probe()];
        } catch (Throwable $e) {
            return ['name' => $name, 'ok' => false, 'detail' => $e->getMessage()];
        }
    }

    private function bytes(float $bytes): string
    {
        return $bytes >= 1024 ** 3 ? round($bytes / 1024 ** 3, 1).' GB' : round($bytes / 1024 ** 2).' MB';
    }
}
