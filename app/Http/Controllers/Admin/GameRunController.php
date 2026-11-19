<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GameRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GameRunController extends Controller
{
    public function index(Request $request): View
    {
        $player = trim((string) $request->query('player', ''));

        $runs = GameRun::with('level:id,number,name')
            ->when($player !== '', fn ($query) => $query->where('player_name', 'like', "%{$player}%"))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('admin.runs', ['runs' => $runs, 'player' => $player]);
    }

    public function destroy(GameRun $run): RedirectResponse
    {
        $run->delete();

        return back()->with('status', 'Run deleted.');
    }
}
