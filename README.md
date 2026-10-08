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

- Find the key to unlock the doors, grab the gold, and reach the exit.
- Guards see in a cone in front of them. Stay behind walls and out of their sight;
  once alerted they chase you for a few seconds.
- You have three lives per campaign. Each treasure is 100 points, each level 250,
  and clearing a level with all its gold doubles that bonus.

Controls:

- Move: WASD or arrow keys, or the on-screen d-pad on touch devices
- Space / Enter / tap: start, continue, resume
- P or Esc: pause. R: restart the level
- Type a name in the HUD to appear on the leaderboard

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
