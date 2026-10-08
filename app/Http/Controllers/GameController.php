<?php

namespace App\Http\Controllers;

use App\Models\Level;
use Illuminate\View\View;

class GameController extends Controller
{
    public function index(): View
    {
        return view('game', [
            'levelCount' => Level::count(),
        ]);
    }
}
