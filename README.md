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
# optional: EDITOR_PASSWORD=... in .env enables the room editor at /editor
touch database/database.sqlite
php artisan migrate --seed
npm run build               # or `npm run dev` while developing
php artisan serve
```

The web root is `public_html/` (not Laravel's default `public/`); point your web server's document root there.

Then open http://127.0.0.1:8000.

## How to play

The five maps are rooms of one castle. Your mission: steal the war plans
from the Keep, then get back out through the gate you came in by. Each room
has a key that unlocks its doors, gold to loot, and stairs down to the next
room. Stairs work both ways: the tile you arrive on leads back up. Stone
archways are portals into other rooms, and the castle map in the HUD (press
M for the full view) shows how the rooms connect.

Taking the plans puts the castle on **lockdown**: sirens, faster and
sharper guards, and the gate in the Cellar lights up. Reach it to escape.

- **Objectives.** Every room has side objectives: find all the gold and
  leave without raising the alarm. Some rooms hold a prisoner to free. The
  war plans are the one required objective.
- **Achievements** are awarded per player name when a campaign ends (Ghost,
  Pacifist, Master Thief, Speedrunner, Chameleon and more) and shown in the
  hall of fame on the leaderboard.
- **Guards** see in a cone in front of them and pause to look around at
  patrol corners. A guard that hears something walks over to investigate,
  looks around, then returns to its patrol. Once a guard sees you it chases
  and, on normal and hard, shoots when it has a clear line of sight. Guards
  that find a body raise the alarm.
- **Stones.** Press Q to throw a stone up to four tiles ahead; nearby guards
  go to look where it landed. You start with three and find one in each new
  room.
- **Disguise.** Stand over a guard or officer you have shot and press E to
  take the uniform. Ordinary guards then only notice you within about a
  tile, officers from a little further, and dogs are not fooled at all. The
  alarm or a gunshot blows your cover. Officers are faster, see further and
  fire sooner. Dogs hear sprinting from far away and run you down, but cannot
  shoot. A guard who keeps you in sight for a few seconds raises the alarm.
- **The alarm** (tripwires, gunshots, or a persistent chase) sends every
  guard in the room after you and pulls reinforcements from the rooms
  connected by portals and stairs. Those guards arrive through the matching
  entrance and are gone from their own rooms for the rest of the campaign.
- **The pistol** is found in the Barracks, with ammo boxes elsewhere. F or
  Ctrl fires in the direction you face. Bodies stay where they fall, each
  kill is 50 points, and every shot raises the alarm.
- Stand still on crates to hide. Hold Shift to sprint, but sprinting is loud.
- Traps: timed spike plates, tripwires that ring the alarm if you sprint
  across them, trapdoors that drop you into the room below, gas vents that
  reverse your controls, crumbling floor that becomes a pit behind you, and
  swinging blades patrolling corridors.
- **Difficulty** is picked on the title screen with 1, 2 or 3: it sets your
  lives (5, 3 or 2), guard speed and sight, whether guards shoot, and how many
  reinforcements an alarm brings. Runs record it on the leaderboard.
- **Your campaign is saved** in the browser whenever you clear a room, travel
  or get caught. Press C on the title screen to continue.
- Each treasure is 100 points, each room 250, and objectives award 200 to 500
  on top.

Controls:

- Move: WASD or arrow keys, the on-screen d-pad on touch devices, or a gamepad
- Shift (or a gamepad shoulder button): sprint. F, Ctrl, gamepad B or the red touch button: fire
- Q or gamepad Y: throw a stone. E or gamepad X: take a uniform
- Space / Enter / tap / gamepad A: start, continue, resume
- P or Esc: pause. M: castle map. R: restart the room
- Type a name in the HUD to appear on the leaderboard

## Designing rooms

### In the browser

Set `EDITOR_PASSWORD` in `.env` and open `/editor`. The editor paints tile
grids, places the player start, guards (with type, speed and patrol route),
swinging blades and lettered portals, sets the room's castle position, and
saves to the database. Every save runs the same validation as the seeder and
reports problems inline. "Save & play" opens the game in that room. Rooms
saved from the editor are flagged custom and survive reseeds; delete one to
fall back to the seeded version. "Copy JSON" / "Paste JSON" move rooms
between installs.

### In the seeder

The built-in rooms live in `database/seeders/LevelSeeder.php` as ASCII art:

```
#  wall        .  floor       D  locked door   K  key   E  exit (stairs)
$  treasure    H  hiding spot (crates)         P  player start
a-z portal: the same letter in two rooms links them both ways
S  spike plate   T  tripwire   X  trapdoor   G  gas vent   C  crumbling floor
W  war plans (required objective)   U  pistol   A  ammo box   R  prisoner cell
```

Objectives are derived from the tiles, so a room with an `R` automatically
gets a "free the prisoner" objective. Moving blades are listed in the room's
`traps` array, and `castle` gives the room's column and row on the castle
map. The rules in `app/Services/LevelValidator.php` apply to both the seeder
and the editor: the key must be reachable without passing a door, the exit
must be locked behind one, pickups must be reachable, portals must pair up,
and guard and blade paths must be straight lines across walkable tiles. Run
`php artisan db:seed` after editing.

## Sounds

Effects and the ambient music are synthesised in the browser; a heartbeat
sounds when a guard is close. Drop your own clips into
`public_html/sounds/` (see the README there) to replace any of them, including
a looping `music` track.

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
