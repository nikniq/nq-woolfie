// Mini Castle Wolfenstein — levels come from the Laravel API, runs are
// reported back to it so the leaderboard can rank escapes.

const TILE = 32;
const FLOOR = 0, WALL = 1, DOOR = 2, KEY = 3, EXIT = 4, TREASURE = 5;
const TREASURE_POINTS = 100;
const LEVEL_BONUS = 250;
const MAX_LIVES = 3;
const VIEW_DIST = 160;          // px
const VIEW_FOV = Math.PI * 0.7; // ~126 degrees
const ALERT_MEMORY_MS = 3000;

export async function startGame() {
    const wrap = document.getElementById('gameWrap');
    const canvas = document.getElementById('game');
    const ctx = canvas.getContext('2d');
    const info = document.getElementById('info');
    const restartBtn = document.getElementById('restart');
    const levelSpan = document.getElementById('level');
    const timerEl = document.getElementById('timer');
    const scoreEl = document.getElementById('score');
    const livesEl = document.getElementById('lives');
    const soundCheckbox = document.getElementById('sound');
    const nameInput = document.getElementById('playerName');

    const api = {
        levels: wrap.dataset.levelsUrl,
        runs: wrap.dataset.runsUrl,
        csrf: document.querySelector('meta[name="csrf-token"]')?.content ?? '',
    };

    let levels = [];
    try {
        const res = await fetch(api.levels, { headers: { Accept: 'application/json' } });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        levels = (await res.json()).data;
    } catch (err) {
        info.textContent = `Could not load levels: ${err.message}\nRun: php artisan migrate --seed`;
        return;
    }
    if (!levels.length) {
        info.textContent = 'No levels in the database.\nRun: php artisan db:seed';
        return;
    }

    try { nameInput.value = localStorage.getItem('woolfie.name') ?? ''; } catch {}
    nameInput.addEventListener('change', () => {
        try { localStorage.setItem('woolfie.name', nameInput.value); } catch {}
    });

    // ---- state ---------------------------------------------------------
    // phase: 'title' | 'playing' | 'paused' | 'levelclear' | 'dead' | 'gameover' | 'won'
    let phase = 'title';
    let levelIndex = 0;
    let map = [];
    let guards = [];
    let keysPressed = {};
    let message = '';
    let runStart = 0;
    let pausedAt = 0;
    let runReported = false;
    let score = 0;
    let levelScore = 0;
    let lives = MAX_LIVES;
    let treasureTotal = 0, treasureFound = 0;
    let flash = 0; // screen flash timer on catch

    const player = { x: 1, y: 1, px: TILE, py: TILE, speed: 4, hasKey: false, alive: true, dir: 'down', frameIdx: 0, frameTimer: 0 };

    // ---- sprites -------------------------------------------------------
    function createSpriteSheet() {
        const cols = 8, rows = 4;
        const c = document.createElement('canvas');
        c.width = TILE * cols; c.height = TILE * rows;
        const g = c.getContext('2d');

        const tile = (i, fn) => { g.save(); g.translate(i * TILE, 0); fn(); g.restore(); };
        // floor: dark stone with faint grid
        tile(0, () => { g.fillStyle = '#1a1a1e'; g.fillRect(0, 0, TILE, TILE); g.fillStyle = '#202026'; g.fillRect(2, 2, 12, 12); g.fillRect(18, 18, 12, 12); });
        // wall: brick
        tile(1, () => {
            g.fillStyle = '#5a5a60'; g.fillRect(0, 0, TILE, TILE);
            g.fillStyle = '#6e6e76';
            for (let r = 0; r < 4; r++) { const off = (r % 2) * 8; for (let b = -1; b < 3; b++) g.fillRect(b * 16 + off + 1, r * 8 + 1, 14, 6); }
            g.fillStyle = '#8a8a92'; g.fillRect(0, 0, TILE, 2); g.fillRect(0, 0, 2, TILE);
        });
        // door
        tile(2, () => { g.fillStyle = '#5a3a1a'; g.fillRect(0, 0, TILE, TILE); g.fillStyle = '#7a5428'; g.fillRect(4, 2, 24, 28); g.fillStyle = '#ffcc33'; g.fillRect(21, 15, 4, 4); g.fillStyle = '#3a2410'; g.fillRect(15, 2, 2, 28); });
        // key
        tile(3, () => { g.fillStyle = '#1a1a1e'; g.fillRect(0, 0, TILE, TILE); g.fillStyle = '#ffcc33'; g.beginPath(); g.arc(11, 13, 6, 0, Math.PI * 2); g.fill(); g.fillStyle = '#1a1a1e'; g.beginPath(); g.arc(11, 13, 2.5, 0, Math.PI * 2); g.fill(); g.fillStyle = '#ffcc33'; g.fillRect(15, 12, 12, 3); g.fillRect(22, 15, 2, 4); g.fillRect(26, 15, 2, 3); });
        // exit
        tile(4, () => { g.fillStyle = '#0a2a4a'; g.fillRect(0, 0, TILE, TILE); g.fillStyle = '#1f7fdf'; g.fillRect(6, 4, 20, 26); g.fillStyle = '#9fd0ff'; g.fillRect(10, 8, 12, 4); g.fillRect(10, 14, 12, 4); g.fillRect(10, 20, 12, 4); });
        // treasure
        tile(5, () => { g.fillStyle = '#1a1a1e'; g.fillRect(0, 0, TILE, TILE); g.fillStyle = '#8a5a1a'; g.fillRect(6, 10, 20, 16); g.fillStyle = '#b07a2a'; g.fillRect(6, 10, 20, 5); g.fillStyle = '#ffd94a'; g.fillRect(14, 16, 4, 4); g.fillRect(9, 7, 4, 3); g.fillRect(19, 6, 4, 3); });

        // rows 1-2: player (row1 = down/left frames, row2 = up/right frames); 4 walk frames each.
        const body = (x, y, col, dir, frame) => {
            g.fillStyle = '#1a1a1e'; g.fillRect(x, y, TILE, TILE);
            const bob = frame % 2 === 0 ? 0 : 1;
            g.fillStyle = col; g.fillRect(x + 9, y + 8 + bob, 14, 16);
            g.fillStyle = '#ffd9b3'; g.fillRect(x + 11, y + 3 + bob, 10, 7); // head
            // eyes show facing
            g.fillStyle = '#111';
            if (dir === 'down') { g.fillRect(x + 13, y + 6 + bob, 2, 2); g.fillRect(x + 17, y + 6 + bob, 2, 2); }
            else if (dir === 'left') { g.fillRect(x + 11, y + 6 + bob, 2, 2); }
            else if (dir === 'right') { g.fillRect(x + 19, y + 6 + bob, 2, 2); }
            // legs
            const step = frame % 4; const l = step === 1 ? 2 : step === 3 ? -2 : 0;
            g.fillStyle = '#333'; g.fillRect(x + 10 + l, y + 24, 5, 5); g.fillRect(x + 17 - l, y + 24, 5, 5);
        };
        const dirs = ['down', 'left', 'up', 'right'];
        dirs.forEach((d, di) => { for (let f = 0; f < 4; f++) body((di % 2) * 4 * TILE + f * TILE, TILE + Math.floor(di / 2) * TILE, '#3a7bd5', d, f); });
        // row 3: guards, 4 dirs x 2 frames
        dirs.forEach((d, di) => { for (let f = 0; f < 2; f++) body(di * 2 * TILE + f * TILE, TILE * 3, '#c33', d, f); });

        const img = new Image(); img.src = c.toDataURL();
        const pf = {}; const gf = {};
        dirs.forEach((d, di) => {
            pf[d] = [0, 1, 2, 3].map((f) => ({ x: (di % 2) * 4 + f, y: 1 + Math.floor(di / 2) }));
            gf[d] = [0, 1].map((f) => ({ x: di * 2 + f, y: 3 }));
        });
        return { img, frames: { tiles: { [FLOOR]: 0, [WALL]: 1, [DOOR]: 2, [KEY]: 3, [EXIT]: 4, [TREASURE]: 5 }, player: pf, guard: gf } };
    }
    const sheet = createSpriteSheet();
    const sprites = { sheet: sheet.img, frames: sheet.frames, ready: false };
    sprites.sheet.onload = () => { sprites.ready = true; };

    // ---- audio ---------------------------------------------------------
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    let audioCtx = null;
    function ensureAudio() { if (!audioCtx && AudioCtx) audioCtx = new AudioCtx(); if (audioCtx?.state === 'suspended') audioCtx.resume(); }
    function playBeep(freq = 440, duration = 0.1, decay = 0.05, type = 'sine') {
        if (!soundCheckbox.checked) return;
        ensureAudio(); if (!audioCtx) return;
        const o = audioCtx.createOscillator(); const g = audioCtx.createGain();
        o.type = type; o.frequency.value = freq; g.gain.value = 0.0001;
        o.connect(g); g.connect(audioCtx.destination); o.start();
        g.gain.exponentialRampToValueAtTime(0.2, audioCtx.currentTime + 0.01);
        g.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + duration + decay);
        o.stop(audioCtx.currentTime + duration + decay + 0.02);
    }
    const sounds = {
        pickup: () => playBeep(880, 0.08),
        treasure: () => { playBeep(660, 0.06); setTimeout(() => playBeep(990, 0.08), 70); },
        alert: () => playBeep(220, 0.18, 0.05, 'square'),
        win: () => { playBeep(880, 0.1); setTimeout(() => playBeep(1100, 0.1), 110); setTimeout(() => playBeep(1320, 0.25), 220); },
        caught: () => playBeep(160, 0.3, 0.1, 'sawtooth'),
        gameover: () => { playBeep(300, 0.2); setTimeout(() => playBeep(220, 0.2), 220); setTimeout(() => playBeep(150, 0.5), 440); },
    };
    const playSound = (n) => sounds[n]?.();

    // ---- map helpers ---------------------------------------------------
    function tileAt(x, y) { if (y < 0 || y >= map.length || x < 0 || x >= map[0].length) return WALL; return map[y][x]; }
    function canWalkTile(x, y, ignoreDoors = false) { const t = tileAt(x, y); if (t === WALL) return false; if (t === DOOR && !ignoreDoors) return false; return true; }
    const now = () => performance.now();
    const elapsedMs = () => (phase === 'paused' ? pausedAt : now()) - runStart;

    function loadLevel(i) {
        levelIndex = i;
        const lvl = levels[i];
        map = lvl.map.map((row) => row.slice());
        canvas.width = map[0].length * TILE; canvas.height = map.length * TILE;
        player.x = lvl.player_start[0]; player.y = lvl.player_start[1];
        player.px = player.x * TILE; player.py = player.y * TILE;
        player.hasKey = false; player.alive = true; player.dir = 'down'; message = '';
        treasureTotal = map.flat().filter((t) => t === TREASURE).length; treasureFound = 0; levelScore = 0;
        guards = lvl.guards.map((g) => ({
            patrol: g.patrol.slice(), i: 0, spd: g.spd, state: 'calm', path: [], pathIdx: 0, lastSeen: null,
            px: g.patrol[0][0] * TILE, py: g.patrol[0][1] * TILE, dir: 'down', frameIdx: 0, frameTimer: 0,
        }));
        levelSpan.textContent = lvl.number;
        runStart = now();
        runReported = false;
        phase = 'playing';
    }

    function startCampaign() { score = 0; lives = MAX_LIVES; loadLevel(0); }

    async function reportRun(outcome) {
        if (runReported) return;
        runReported = true;
        const payload = {
            level_id: levels[levelIndex].id,
            player_name: nameInput.value,
            outcome,
            time_ms: Math.round(elapsedMs()),
            score: levelScore,
        };
        try {
            const res = await fetch(api.runs, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': api.csrf },
                body: JSON.stringify(payload),
            });
            if (!res.ok) return;
            const body = await res.json();
            if (outcome === 'completed') {
                const secs = (payload.time_ms / 1000).toFixed(2);
                message += `\nTime ${secs}s` + (body.is_personal_best ? ' — fastest ever on this level!' : ` (record ${(body.best_time_ms / 1000).toFixed(2)}s)`);
            }
        } catch {
            // Leaderboard is optional; the game keeps running without it.
        }
    }

    function completeLevel() {
        const bonus = LEVEL_BONUS + (treasureFound === treasureTotal && treasureTotal > 0 ? LEVEL_BONUS : 0);
        levelScore += bonus; score += bonus;
        message = `Level clear! +${bonus}` + (treasureFound === treasureTotal && treasureTotal > 0 ? ' (all loot found)' : '');
        player.alive = false;
        playSound('win');
        reportRun('completed');
        phase = levelIndex + 1 < levels.length ? 'levelclear' : 'won';
    }

    function caught() {
        message = 'Caught by a guard!';
        player.alive = false; flash = 0.35;
        lives -= 1;
        score -= levelScore; // gold respawns with the level, so give it back
        reportRun('caught');
        if (lives <= 0) { phase = 'gameover'; playSound('gameover'); }
        else { phase = 'dead'; playSound('caught'); }
    }

    // ---- input ---------------------------------------------------------
    function advance() {
        ensureAudio();
        if (phase === 'title' || phase === 'gameover' || phase === 'won') startCampaign();
        else if (phase === 'levelclear') loadLevel(levelIndex + 1);
        else if (phase === 'dead') loadLevel(levelIndex);
        else if (phase === 'paused') togglePause();
    }
    function togglePause() {
        if (phase === 'playing') { phase = 'paused'; pausedAt = now(); }
        else if (phase === 'paused') { runStart += now() - pausedAt; phase = 'playing'; }
    }
    function restartLevel() { if (phase !== 'title') { if (phase === 'playing' || phase === 'paused') score -= levelScore; loadLevel(levelIndex); } }

    restartBtn.onclick = restartLevel;
    window.addEventListener('keydown', (e) => {
        if (e.target === nameInput) return;
        const k = e.key.toLowerCase();
        keysPressed[k] = true;
        if (k === ' ' || k === 'enter') { e.preventDefault(); advance(); }
        else if (k === 'p' || k === 'escape') togglePause();
        else if (k === 'r') restartLevel();
        if (e.key.startsWith('Arrow')) e.preventDefault();
    });
    window.addEventListener('keyup', (e) => { keysPressed[e.key.toLowerCase()] = false; });
    canvas.addEventListener('pointerdown', () => { if (phase !== 'playing') advance(); });

    // touch d-pad
    const dirKey = { up: 'arrowup', down: 'arrowdown', left: 'arrowleft', right: 'arrowright' };
    document.querySelectorAll('#touch button').forEach((b) => {
        const k = dirKey[b.dataset.dir];
        const on = (e) => { e.preventDefault(); keysPressed[k] = true; if (phase !== 'playing') advance(); };
        const off = (e) => { e.preventDefault(); keysPressed[k] = false; };
        b.addEventListener('pointerdown', on); b.addEventListener('pointerup', off);
        b.addEventListener('pointercancel', off); b.addEventListener('pointerleave', off);
    });

    // ---- A* ------------------------------------------------------------
    function astar(sx, sy, ex, ey) {
        const cols = map[0].length, rows = map.length;
        const h = (x, y) => Math.abs(x - ex) + Math.abs(y - ey);
        const key = (x, y) => `${x},${y}`;
        const open = new Map(); const closed = new Set();
        open.set(key(sx, sy), { x: sx, y: sy, g: 0, f: h(sx, sy), came: null });
        while (open.size) {
            let best = null, bk = null;
            for (const [k, v] of open) { if (!best || v.f < best.f) { best = v; bk = k; } }
            open.delete(bk);
            const { x, y, g } = best;
            if (x === ex && y === ey) { const path = []; let cur = best; while (cur) { path.push([cur.x, cur.y]); cur = cur.came; } return path.reverse(); }
            closed.add(key(x, y));
            for (const [dx, dy] of [[1, 0], [-1, 0], [0, 1], [0, -1]]) {
                const nx = x + dx, ny = y + dy;
                if (nx < 0 || ny < 0 || nx >= cols || ny >= rows) continue;
                if (!canWalkTile(nx, ny)) continue;
                if (closed.has(key(nx, ny))) continue;
                const ng = g + 1; const existing = open.get(key(nx, ny));
                if (!existing || ng < existing.g) open.set(key(nx, ny), { x: nx, y: ny, g: ng, f: ng + h(nx, ny), came: best });
            }
        }
        return null;
    }

    function rayBlocked(x0, y0, x1, y1) {
        let dx = Math.abs(x1 - x0), sx = x0 < x1 ? 1 : -1;
        let dy = -Math.abs(y1 - y0), sy = y0 < y1 ? 1 : -1;
        let err = dx + dy, x = x0, y = y0;
        while (true) {
            if (x === x1 && y === y1) return false;
            const t = tileAt(x, y); if (t === WALL || t === DOOR) return true;
            const e2 = 2 * err;
            if (e2 >= dy) { err += dy; x += sx; }
            if (e2 <= dx) { err += dx; y += sy; }
        }
    }

    const DIR_ANGLE = { right: 0, down: Math.PI / 2, left: Math.PI, up: -Math.PI / 2 };
    const dirFromVec = (dx, dy) => (Math.abs(dx) > Math.abs(dy) ? (dx > 0 ? 'right' : 'left') : (dy > 0 ? 'down' : 'up'));

    function moveGuardToward(g, tx, ty) {
        const dxg = tx - g.px, dyg = ty - g.py, dist = Math.hypot(dxg, dyg);
        if (dist < 2) return true;
        g.px += (dxg / dist) * g.spd; g.py += (dyg / dist) * g.spd; g.dir = dirFromVec(dxg, dyg);
        return false;
    }

    // ---- update --------------------------------------------------------
    function update(dt) {
        if (flash > 0) flash -= dt;
        if (phase !== 'playing') return;

        let dx = 0, dy = 0;
        if (keysPressed['w'] || keysPressed['arrowup']) dy = -1;
        if (keysPressed['s'] || keysPressed['arrowdown']) dy = 1;
        if (keysPressed['a'] || keysPressed['arrowleft']) dx = -1;
        if (keysPressed['d'] || keysPressed['arrowright']) dx = 1;

        let moving = false;
        if (dx || dy) {
            moving = true;
            player.dir = dirFromVec(dx, dy);
            const n = dx && dy ? Math.SQRT1_2 : 1;
            // try full move, then each axis separately so you can slide along walls
            const tries = [[dx * n, dy * n], [dx, 0], [0, dy]];
            for (const [mx, my] of tries) {
                if (!mx && !my) continue;
                const nx = player.px + mx * player.speed, ny = player.py + my * player.speed;
                const tx = Math.floor((nx + TILE / 2) / TILE), ty = Math.floor((ny + TILE / 2) / TILE);
                if (canWalkTile(tx, ty, true) && (tileAt(tx, ty) !== DOOR || player.hasKey)) {
                    player.px = nx; player.py = ny; player.x = tx; player.y = ty; break;
                }
            }
        }

        if (moving) {
            player.frameTimer += dt;
            if (player.frameTimer > 0.12) { player.frameTimer = 0; player.frameIdx = (player.frameIdx + 1) % 4; }
        } else { player.frameIdx = 0; player.frameTimer = 0; }

        const here = tileAt(player.x, player.y);
        if (here === KEY) { player.hasKey = true; map[player.y][player.x] = FLOOR; message = 'Got the key! Doors are open.'; playSound('pickup'); for (let y = 0; y < map.length; y++) for (let x = 0; x < map[0].length; x++) if (map[y][x] === DOOR) map[y][x] = FLOOR; }
        else if (here === TREASURE) { map[player.y][player.x] = FLOOR; treasureFound++; levelScore += TREASURE_POINTS; score += TREASURE_POINTS; message = `Gold! +${TREASURE_POINTS} (${treasureFound}/${treasureTotal})`; playSound('treasure'); }
        else if (here === EXIT) { completeLevel(); return; }

        const pcx = player.px + TILE / 2, pcy = player.py + TILE / 2;
        for (const g of guards) {
            const gx = Math.floor((g.px + TILE / 2) / TILE), gy = Math.floor((g.py + TILE / 2) / TILE);
            const vx = pcx - (g.px + TILE / 2), vy = pcy - (g.py + TILE / 2);
            const d = Math.hypot(vx, vy);
            let canSee = false;
            if (d < VIEW_DIST) {
                let ang = Math.atan2(vy, vx) - DIR_ANGLE[g.dir];
                ang = Math.atan2(Math.sin(ang), Math.cos(ang));
                const inCone = g.state === 'alert' || d < TILE || Math.abs(ang) < VIEW_FOV / 2;
                canSee = inCone && !rayBlocked(gx, gy, player.x, player.y);
            }
            if (canSee) {
                if (g.state !== 'alert') { playSound('alert'); message = 'Spotted!'; }
                g.state = 'alert'; g.lastSeen = { x: player.x, y: player.y, time: now() };
                if (!g.path.length || g.path[g.path.length - 1][0] !== player.x || g.path[g.path.length - 1][1] !== player.y) {
                    const path = astar(gx, gy, player.x, player.y);
                    if (path && path.length > 1) { g.path = path; g.pathIdx = 1; }
                }
            }

            if (g.state === 'alert') {
                if (g.path && g.pathIdx < g.path.length) {
                    if (moveGuardToward(g, g.path[g.pathIdx][0] * TILE, g.path[g.pathIdx][1] * TILE)) g.pathIdx++;
                } else if (g.lastSeen && now() - g.lastSeen.time > ALERT_MEMORY_MS) {
                    g.state = 'calm'; g.path = []; g.pathIdx = 0;
                    // walk back to nearest patrol point
                    let best = 0, bd = Infinity;
                    g.patrol.forEach((p, i) => { const dd = Math.hypot(p[0] * TILE - g.px, p[1] * TILE - g.py); if (dd < bd) { bd = dd; best = i; } });
                    g.i = best;
                }
            } else {
                const target = g.patrol[g.i];
                if (moveGuardToward(g, target[0] * TILE, target[1] * TILE)) g.i = (g.i + 1) % g.patrol.length;
            }

            g.frameTimer += dt;
            if (g.frameTimer > (g.state === 'alert' ? 0.12 : 0.2)) { g.frameTimer = 0; g.frameIdx = (g.frameIdx + 1) % 2; }

            if (Math.hypot(pcx - (g.px + TILE / 2), pcy - (g.py + TILE / 2)) < 14) { caught(); return; }
        }
    }

    // ---- draw ----------------------------------------------------------
    function blit(f, x, y) { ctx.drawImage(sprites.sheet, f.x * TILE, f.y * TILE, TILE, TILE, x, y, TILE, TILE); }
    const fallbackColour = { [WALL]: '#444', [DOOR]: '#3a3', [KEY]: '#ff8800', [EXIT]: '#06f', [TREASURE]: '#dba51a' };

    function drawCone(g) {
        const cx = g.px + TILE / 2, cy = g.py + TILE / 2, a = DIR_ANGLE[g.dir];
        ctx.beginPath(); ctx.moveTo(cx, cy); ctx.arc(cx, cy, VIEW_DIST, a - VIEW_FOV / 2, a + VIEW_FOV / 2); ctx.closePath();
        ctx.fillStyle = g.state === 'alert' ? 'rgba(255,40,40,0.16)' : 'rgba(255,220,80,0.08)'; ctx.fill();
    }

    function overlay(title, lines) {
        ctx.fillStyle = 'rgba(0,0,0,0.65)'; ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.textAlign = 'center'; ctx.fillStyle = '#fff';
        ctx.font = 'bold 36px system-ui, sans-serif'; ctx.fillText(title, canvas.width / 2, canvas.height / 2 - 30);
        ctx.font = '16px system-ui, sans-serif'; ctx.fillStyle = '#ddd';
        lines.forEach((l, i) => ctx.fillText(l, canvas.width / 2, canvas.height / 2 + 8 + i * 24));
        ctx.textAlign = 'left';
    }

    function draw() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        for (let y = 0; y < map.length; y++) {
            for (let x = 0; x < map[0].length; x++) {
                const t = map[y][x], px = x * TILE, py = y * TILE;
                if (sprites.ready) blit({ x: sprites.frames.tiles[t] ?? 0, y: 0 }, px, py);
                else { ctx.fillStyle = fallbackColour[t] ?? '#222'; ctx.fillRect(px, py, TILE, TILE); }
            }
        }

        guards.forEach(drawCone);

        if (sprites.ready) blit(sprites.frames.player[player.dir][player.frameIdx], player.px, player.py);
        else { ctx.fillStyle = player.alive ? '#ffdd00' : '#777'; ctx.fillRect(player.px + 6, player.py + 6, TILE - 12, TILE - 12); }

        guards.forEach((g) => {
            if (sprites.ready) blit(sprites.frames.guard[g.dir][g.frameIdx], g.px, g.py);
            else { ctx.fillStyle = g.state === 'alert' ? '#ff5555' : '#c33'; ctx.fillRect(g.px + 6, g.py + 6, TILE - 12, TILE - 12); }
            if (g.state === 'alert') { ctx.fillStyle = '#ff3b3b'; ctx.font = 'bold 18px system-ui, sans-serif'; ctx.fillText('!', g.px + 13, g.py - 2); }
        });

        if (flash > 0) { ctx.fillStyle = `rgba(255,0,0,${Math.min(0.5, flash)})`; ctx.fillRect(0, 0, canvas.width, canvas.height); }

        const lvl = levels[levelIndex];
        if (phase === 'title') overlay('Mini Castle Wolfenstein', ['Find the key, loot the gold, reach the exit.', 'Stay out of the guards\' sight.', '', 'Press Space or tap to start']);
        else if (phase === 'paused') overlay('Paused', ['Press P or Space to resume']);
        else if (phase === 'levelclear') overlay(`Level ${lvl.number} clear`, [`Score ${score}`, '', 'Press Space for the next level']);
        else if (phase === 'dead') overlay('Caught!', [`${lives} ${lives === 1 ? 'life' : 'lives'} left`, '', 'Press Space to retry']);
        else if (phase === 'gameover') overlay('Game over', [`Final score ${score}`, '', 'Press Space to try again']);
        else if (phase === 'won') overlay('You escaped the castle!', [`Final score ${score}`, '', 'Press Space to play again']);

        timerEl.textContent = phase === 'title' ? '0.0s' : `${(elapsedMs() / 1000).toFixed(1)}s`;
        scoreEl.textContent = score;
        livesEl.textContent = '♥'.repeat(Math.max(0, lives)) + '♡'.repeat(Math.max(0, MAX_LIVES - lives));
        info.textContent = `${lvl.name}\nKey: ${player.hasKey ? 'yes' : 'no'}   Gold: ${treasureFound}/${treasureTotal}\n${message}`;
    }

    let last = now();
    function loop(t) { const dt = Math.min(0.05, (t - last) / 1000); last = t; update(dt); draw(); requestAnimationFrame(loop); }
    // show level 1 behind the title screen
    loadLevel(0); phase = 'title';
    requestAnimationFrame(loop);
}
