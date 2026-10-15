<?php

namespace App\Http\Controllers;

use App\Models\Level;
use App\Services\LevelValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EditorController extends Controller
{
    public function login(): View|RedirectResponse
    {
        if (config('game.editor_password') === '') {
            abort(403, 'The room editor is disabled. Set EDITOR_PASSWORD in .env to enable it.');
        }

        return view('editor.login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);
        if (! hash_equals((string) config('game.editor_password'), (string) $request->input('password'))) {
            return back()->withErrors(['password' => 'Wrong password.']);
        }
        $request->session()->put('editor_ok', true);

        return redirect()->route('editor.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('editor_ok');

        return redirect()->route('game');
    }

    public function index(): View
    {
        return view('editor.index', ['levels' => Level::orderBy('number')->get()]);
    }

    public function create(): View
    {
        $number = (Level::max('number') ?? 0) + 1;
        $width = 20;
        $height = 10;
        $map = [];
        for ($y = 0; $y < $height; $y++) {
            $row = [];
            for ($x = 0; $x < $width; $x++) {
                $row[] = ($x === 0 || $y === 0 || $x === $width - 1 || $y === $height - 1) ? Level::TILE_WALL : Level::TILE_FLOOR;
            }
            $map[] = $row;
        }
        $level = new Level([
            'number' => $number, 'name' => "Room $number", 'map' => $map, 'player_start' => [1, 1],
            'guards' => [], 'portals' => [], 'traps' => [], 'castle_x' => 0, 'castle_y' => 0,
        ]);

        return view('editor.edit', ['level' => $level, 'isNew' => true, 'editorData' => $this->editorData($level), 'others' => Level::orderBy('number')->get(['id', 'number', 'name', 'castle_x', 'castle_y', 'portals'])]);
    }

    public function edit(Level $level): View
    {
        return view('editor.edit', ['level' => $level, 'isNew' => false, 'editorData' => $this->editorData($level), 'others' => Level::where('id', '!=', $level->id)->orderBy('number')->get(['id', 'number', 'name', 'castle_x', 'castle_y', 'portals'])]);
    }

    private function editorData(Level $level): array
    {
        return [
            'id' => $level->id, 'number' => $level->number, 'name' => $level->name, 'map' => $level->map,
            'player_start' => $level->player_start, 'guards' => $level->guards ?? [], 'traps' => $level->traps ?? [],
            'portals' => $level->portals ?? [], 'castle_x' => $level->castle_x, 'castle_y' => $level->castle_y,
        ];
    }

    public function store(Request $request): JsonResponse
    {
        return $this->save($request, new Level);
    }

    public function update(Request $request, Level $level): JsonResponse
    {
        return $this->save($request, $level);
    }

    public function destroy(Level $level): JsonResponse
    {
        $level->delete();
        LevelValidator::relinkPortals();

        return response()->json(['ok' => true]);
    }

    private function save(Request $request, Level $level): JsonResponse
    {
        $data = $request->validate([
            'number' => ['required', 'integer', 'min:1', 'max:99', Rule::unique('levels', 'number')->ignore($level->id)],
            'name' => ['required', 'string', 'max:40'],
            'map' => ['required', 'array', 'min:3', 'max:30'],
            'map.*' => ['required', 'array', 'min:3', 'max:40'],
            'map.*.*' => ['required', 'integer', 'min:0', 'max:17'],
            'player_start' => ['required', 'array', 'size:2'],
            'player_start.*' => ['integer', 'min:0'],
            'guards' => ['present', 'array', 'max:12'],
            'guards.*.spd' => ['required', 'numeric', 'min:0.3', 'max:4'],
            'guards.*.type' => ['nullable', Rule::in(['guard', 'officer', 'dog'])],
            'guards.*.patrol' => ['required', 'array', 'min:1', 'max:16'],
            'guards.*.patrol.*' => ['array', 'size:2'],
            'guards.*.patrol.*.*' => ['integer', 'min:0'],
            'traps' => ['present', 'array', 'max:12'],
            'traps.*.type' => ['required', Rule::in(['blade'])],
            'traps.*.spd' => ['required', 'numeric', 'min:0.3', 'max:6'],
            'traps.*.from' => ['required', 'array', 'size:2'],
            'traps.*.to' => ['required', 'array', 'size:2'],
            'portals' => ['present', 'array', 'max:8'],
            'portals.*.id' => ['required', 'string', 'regex:/^[a-z]$/'],
            'portals.*.x' => ['required', 'integer', 'min:0'],
            'portals.*.y' => ['required', 'integer', 'min:0'],
            'castle_x' => ['required', 'integer', 'min:0', 'max:9'],
            'castle_y' => ['required', 'integer', 'min:0', 'max:9'],
        ]);

        // portal tiles and the portal list must agree
        $map = $data['map'];
        $portalTiles = [];
        foreach ($map as $y => $row) {
            foreach ($row as $x => $t) {
                if ($t === Level::TILE_PORTAL) {
                    $portalTiles["$x,$y"] = true;
                }
            }
        }
        $listed = [];
        foreach ($data['portals'] as $p) {
            $listed["{$p['x']},{$p['y']}"] = true;
            if (! isset($portalTiles["{$p['x']},{$p['y']}"])) {
                return response()->json(['errors' => ["Portal '{$p['id']}' at ({$p['x']},{$p['y']}) is not on a portal tile"]], 422);
            }
        }
        foreach (array_keys($portalTiles) as $key) {
            if (! isset($listed[$key])) {
                return response()->json(['errors' => ["Portal tile at ($key) has no letter; click it with the portal tool to assign one"]], 422);
            }
        }

        $validator = new LevelValidator;
        $errors = $validator->validate($map, $data['player_start'], $data['guards'], $data['traps'], 'Room');
        $portalsByLevel = Level::where('id', '!=', $level->id)->get()->mapWithKeys(fn ($l) => [$l->number => array_column($l->portals ?? [], 'id')])->all();
        $portalsByLevel[$data['number']] = array_column($data['portals'], 'id');
        $errors = array_merge($errors, $validator->validatePortals($portalsByLevel));
        if ($errors) {
            return response()->json(['errors' => $errors], 422);
        }

        $level->fill([
            'number' => $data['number'],
            'name' => $data['name'],
            'map' => $map,
            'player_start' => $data['player_start'],
            'guards' => array_map(fn ($g) => ['patrol' => $g['patrol'], 'spd' => (float) $g['spd'], 'type' => $g['type'] ?? 'guard'], $data['guards']),
            'traps' => $data['traps'],
            'portals' => array_map(fn ($p) => ['id' => $p['id'], 'x' => $p['x'], 'y' => $p['y']], $data['portals']),
            'objectives' => LevelValidator::objectivesFor($map),
            'castle_x' => $data['castle_x'],
            'castle_y' => $data['castle_y'],
            'custom' => true,
        ])->save();
        LevelValidator::relinkPortals();

        return response()->json(['ok' => true, 'id' => $level->id, 'number' => $level->number, 'edit_url' => route('editor.edit', $level)]);
    }
}
