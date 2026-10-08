# Mini Castle Wolfenstein — Laravel edition

A tiny top-down stealth game (find the key, open the doors, reach the exit,
avoid the guards) rebuilt on the Laravel framework.

What Laravel adds over the original static demo:

- **Levels live in the database** (`levels` table, seeded by `LevelSeeder`) and are
  served to the game through `GET /api/levels`. Add a row, get a level.
- **Runs are recorded** (`POST /api/runs`) with player name, outcome and time, and
  a **leaderboard** at `/leaderboard` ranks the fastest escapes per level.
- Assets are bundled with Vite from `resources/js/game.js` and `resources/css/app.css`.

## Setup

```bash
composer install
npm install
cp .env.example .env        # already done on a fresh checkout
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm run build               # or `npm run dev` while developing
php artisan serve
```

The web root is `public_html/` (not Laravel's default `public/`); point your web server's document root there.

Then open http://127.0.0.1:8000.

## How to play

The five maps are rooms of one castle. Each room has a key that unlocks its
doors, gold to loot, and stairs (the exit) down to the next room. Stone
archways are portals into other rooms, and the castle map in the HUD (press
M for the full view) shows how the rooms connect. You win when every room's
exit has been reached, in whatever order you explore them.

- Guards see in a cone in front of them and pause to look around at patrol
  corners. Once alerted they chase you for a few seconds.
- Stand still on crates to hide. Hold Shift to sprint, but sprinting is loud
  and guards within earshot will come looking.
- Traps: timed spike plates (watch the tips rise before they fire), tripwires
  that ring the alarm if you sprint across them, trapdoors that drop you into
  the room below, gas vents that reverse your controls, crumbling floor that
  becomes a pit behind you, and swinging blades patrolling corridors.
- Three lives per campaign. Each treasure is 100 points, each room 250, and
  clearing a room with all its gold doubles that bonus.

Controls:

- Move: WASD or arrow keys, the on-screen d-pad on touch devices, or a gamepad
- Shift (or a gamepad shoulder button): sprint
- Space / Enter / tap / gamepad A: start, continue, resume
- P or Esc: pause. M: castle map. R: restart the room
- Type a name in the HUD to appear on the leaderboard

## Designing rooms

Rooms live in `database/seeders/LevelSeeder.php` as ASCII art:

```
#  wall        .  floor       D  locked door   K  key   E  exit (stairs)
$  treasure    H  hiding spot (crates)         P  player start
a-z portal: the same letter in two rooms links them both ways
S  spike plate   T  tripwire   X  trapdoor   G  gas vent   C  crumbling floor
```

Moving blades are listed in the room's `traps` array, and `castle` gives the
room's column and row on the castle map. The seeder validates every room:
the key must be reachable without passing a door, the exit must be locked
behind one, treasure must be reachable, portals must pair up, and guard and
blade paths must be straight lines across walkable tiles. Run
`php artisan db:seed` after editing.

## Sounds

Effects are synthesised in the browser. Drop your own clips into
`public_html/sounds/` (see the README there) to replace any of them.

## Deploying

`./deploy.sh` on the server pulls the latest commit, installs production
dependencies, migrates, reseeds the rooms, and rebuilds Laravel's caches
inside maintenance mode. Built assets are committed, so no Node is needed.

## Tests

```bash
php artisan test
```

## Layout

| Path | Purpose |
| --- | --- |
| `app/Http/Controllers/GameController.php` | Renders the game page |
| `app/Http/Controllers/LeaderboardController.php` | Fastest escapes per level |
| `app/Http/Controllers/Api/LevelController.php` | JSON level data for the client |
| `app/Http/Controllers/Api/GameRunController.php` | Stores finished runs |
| `app/Models/Level.php`, `app/Models/GameRun.php` | Eloquent models |
| `database/seeders/LevelSeeder.php` | The three original maps |
| `resources/js/game.js` | Game loop, A* guards, sprites, audio |
| `resources/views/game.blade.php` | Canvas + HUD |
