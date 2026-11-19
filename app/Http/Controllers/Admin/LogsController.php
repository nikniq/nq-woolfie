<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LogReader;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LogsController extends Controller
{
    public function index(Request $request, LogReader $logs): View
    {
        $filters = $request->validate([
            'level' => ['nullable', Rule::in(LogReader::LEVELS)],
            'q' => ['nullable', 'string', 'max:200'],
        ]);

        return view('admin.logs', [
            'entries' => $logs->entries($filters['level'] ?? null, $filters['q'] ?? null),
            'levels' => LogReader::LEVELS,
            'level' => $filters['level'] ?? '',
            'search' => $filters['q'] ?? '',
        ]);
    }
}
