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
            'sounds' => $this->customSounds(),
        ]);
    }

    /**
     * Optional audio clips in public/sounds/<name>.(mp3|ogg|wav) that replace
     * the synthesised effects. Listed here so the client never has to probe.
     *
     * @return array<string, string>
     */
    private function customSounds(): array
    {
        $found = [];
        foreach (glob(public_path('sounds').'/*.{mp3,ogg,wav}', GLOB_BRACE) ?: [] as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $found[$name] ??= '/sounds/'.basename($file);
        }

        return $found;
    }
}
