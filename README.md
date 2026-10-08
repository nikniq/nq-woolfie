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

## Controls

- Move: WASD or arrow keys
- Space initialises audio in browsers that require a user gesture
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
