<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\HealthCheck;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HealthController extends Controller
{
    public function index(HealthCheck $health): View
    {
        $results = $health->run();

        return view('admin.health', [
            'results' => $results,
            'healthy' => $health->healthy($results),
            'commit' => trim((string) @shell_exec('git -C '.escapeshellarg(base_path()).' log -1 --format="%h %s (%cr)" 2>/dev/null')),
        ]);
    }

    /**
     * Machine-readable status for an external uptime monitor; needs HEALTH_TOKEN.
     */
    public function json(Request $request, HealthCheck $health): JsonResponse
    {
        $token = (string) config('game.health_token');
        abort_if($token === '' || ! hash_equals($token, (string) $request->query('token', $request->bearerToken())), 404);

        $results = $health->run();
        $healthy = $health->healthy($results);

        return response()->json(['status' => $healthy ? 'ok' : 'failing', 'checks' => $results, 'at' => now()->toIso8601String()], $healthy ? 200 : 503);
    }
}
