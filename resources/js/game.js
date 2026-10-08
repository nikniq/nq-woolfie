// Mini Castle Wolfenstein — levels come from the Laravel API, runs are
// reported back to it so the leaderboard can rank escapes.

const TILE = 32;
const FLOOR = 0, WALL = 1, DOOR = 2, KEY = 3, EXIT = 4;

export async function startGame() {
    const wrap = document.getElementById('gameWrap');
    const canvas = document.getElementById('game');
    const ctx = canvas.getContext('2d');
    const info = document.getElementById('info');
    const restartBtn = document.getElementById('restart');
    const levelSpan = document.getElementById('level');
    const timerEl = document.getElementById('timer');
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

    let levelIndex = 0;
    let map = [];
    let guards = [];
    let keysPressed = {};
    let message = '';
    let runStart = 0;
    let runReported = false;

    const player = { x: 1, y: 1, px: TILE, py: TILE, speed: 4, hasKey: false, alive: true, frameIdx: 0, frameTimer: 0 };

    // ---- sprites -------------------------------------------------------
    function createSpriteSheet() {
        const cols = 8, rows = 3;
        const c = document.createElement('canvas');
        c.width = TILE * cols; c.height = TILE * rows;
        const g = c.getContext('2d');
        g.strokeStyle = '#111';
        g.fillStyle = '#111'; g.fillRect(0, 0, TILE, TILE); g.strokeRect(0, 0, TILE, TILE);
        g.fillStyle = '#666'; g.fillRect(TILE, 0, TILE, TILE); g.strokeRect(TILE, 0, TILE, TILE);
        g.fillStyle = '#3a3'; g.fillRect(2 * TILE, 0, TILE, TILE); g.fillStyle = '#6b4'; g.fillRect(2 * TILE + 8, 8, TILE - 16, TILE - 16); g.strokeRect(2 * TILE, 0, TILE, TILE);
        g.fillStyle = '#222'; g.fillRect(3 * TILE, 0, TILE, TILE); g.fillStyle = '#ff8800'; g.fillRect(3 * TILE + 8, 8, TILE - 16, TILE - 16); g.strokeRect(3 * TILE, 0, TILE, TILE);
        g.fillStyle = '#024'; g.fillRect(4 * TILE, 0, TILE, TILE); g.fillStyle = '#06f'; g.fillRect(4 * TILE + 6, 6, TILE - 12, TILE - 12); g.strokeRect(4 * TILE, 0, TILE, TILE);
        for (let i = 0; i < 4; i++) {
            const x = i * TILE, y = TILE;
            g.fillStyle = '#222'; g.fillRect(x, y, TILE, TILE);
            g.fillStyle = '#ffdd00'; g.fillRect(x + (i % 2 === 0 ? 6 : 8), y + 6, TILE - 12, TILE - 12);
            g.strokeStyle = '#111'; g.strokeRect(x, y, TILE, TILE);
        }
        for (let i = 0; i < 2; i++) {
            const x = i * TILE, y = TILE * 2;
            g.fillStyle = '#222'; g.fillRect(x, y, TILE, TILE);
            g.fillStyle = '#c33'; g.fillRect(x + (i % 2 === 0 ? 6 : 8), y + 6, TILE - 12, TILE - 12);
            g.strokeStyle = '#111'; g.strokeRect(x, y, TILE, TILE);
        }
        const img = new Image(); img.src = c.toDataURL();
        return {
            img,
            frames: {
                floor: { x: 0, y: 0 }, wall: { x: 1, y: 0 }, door: { x: 2, y: 0 }, key: { x: 3, y: 0 }, exit: { x: 4, y: 0 },
                playerFrames: [{ x: 0, y: 1 }, { x: 1, y: 1 }, { x: 2, y: 1 }, { x: 3, y: 1 }],
                guardFrames: [{ x: 0, y: 2 }, { x: 1, y: 2 }],
            },
        };
    }
    const sheet = createSpriteSheet();
    const sprites = { sheet: sheet.img, frames: sheet.frames, ready: false };
    sprites.sheet.onload = () => { sprites.ready = true; };

    // ---- audio ---------------------------------------------------------
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    let audioCtx = null;
    function ensureAudio() { if (!audioCtx && AudioCtx) audioCtx = new AudioCtx(); }
    function playBeep(freq = 440, duration = 0.1, decay = 0.05) {
        if (!soundCheckbox.checked) return;
        ensureAudio(); if (!audioCtx) return;
        const o = audioCtx.createOscillator(); const g = audioCtx.createGain();
        o.type = 'sine'; o.frequency.value = freq; g.gain.value = 0.0001;
        o.connect(g); g.connect(audioCtx.destination); o.start();
        g.gain.exponentialRampToValueAtTime(0.2, audioCtx.currentTime + 0.01);
        g.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + duration + decay);
        o.stop(audioCtx.currentTime + duration + decay + 0.02);
    }
    function playSound(name) {
        if (name === 'pickup') playBeep(880, 0.08);
        else if (name === 'alert') playBeep(220, 0.18);
        else if (name === 'win') playBeep(1200, 0.25);
        else if (name === 'caught') playBeep(160, 0.18);
    }

    // ---- map helpers ---------------------------------------------------
    function tileAt(x, y) { if (y < 0 || y >= map.length || x < 0 || x >= map[0].length) return WALL; return map[y][x]; }
    function canWalkTile(x, y, ignoreDoors = false) { const t = tileAt(x, y); if (t === WALL) return false; if (t === DOOR && !ignoreDoors) return false; return true; }

    function loadLevel(i) {
        levelIndex = i;
        const lvl = levels[i];
        map = lvl.map.map((row) => row.slice());
        player.x = lvl.player_start[0]; player.y = lvl.player_start[1];
        player.px = player.x * TILE; player.py = player.y * TILE;
        player.hasKey = false; player.alive = true; message = '';
        guards = lvl.guards.map((g) => ({
            patrol: g.patrol.slice(), i: 0, spd: g.spd, state: 'calm', path: [], pathIdx: 0, lastSeen: null,
            px: g.patrol[0][0] * TILE, py: g.patrol[0][1] * TILE, frameIdx: 0, frameTimer: 0,
        }));
        levelSpan.textContent = lvl.number;
        runStart = performance.now();
        runReported = false;
    }

    async function reportRun(outcome) {
        if (runReported) return;
        runReported = true;
        const payload = {
            level_id: levels[levelIndex].id,
            player_name: nameInput.value,
            outcome,
            time_ms: Math.round(performance.now() - runStart),
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
                message += `\nTime: ${secs}s` + (body.is_personal_best ? ' — new best!' : ` (best ${(body.best_time_ms / 1000).toFixed(2)}s)`);
            }
        } catch {
            // Leaderboard is optional; the game keeps running without it.
        }
    }

    function nextLevel() {
        if (levelIndex + 1 < levels.length) { loadLevel(levelIndex + 1); message = 'Next level!'; playSound('pickup'); }
        else { message = 'All levels complete!'; playSound('win'); player.alive = false; }
    }

    restartBtn.onclick = () => loadLevel(levelIndex);
    window.addEventListener('keydown', (e) => {
        if (e.target === nameInput) return;
        keysPressed[e.key.toLowerCase()] = true;
        if (e.key === ' ') { ensureAudio(); e.preventDefault(); }
        if (e.key.startsWith('Arrow')) e.preventDefault();
    });
    window.addEventListener('keyup', (e) => { keysPressed[e.key.toLowerCase()] = false; });

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

    // ---- update --------------------------------------------------------
    function update(dt) {
        if (!player.alive) return;
        let dx = 0, dy = 0;
        if (keysPressed['w'] || keysPressed['arrowup']) dy = -1;
        if (keysPressed['s'] || keysPressed['arrowdown']) dy = 1;
        if (keysPressed['a'] || keysPressed['arrowleft']) dx = -1;
        if (keysPressed['d'] || keysPressed['arrowright']) dx = 1;

        let moving = false;
        if (dx || dy) {
            moving = true;
            const nx = player.px + dx * player.speed, ny = player.py + dy * player.speed;
            const tx = Math.floor((nx + TILE / 2) / TILE), ty = Math.floor((ny + TILE / 2) / TILE);
            if (canWalkTile(tx, ty, true) && (tileAt(tx, ty) !== DOOR || player.hasKey)) {
                player.px = nx; player.py = ny; player.x = tx; player.y = ty;
            }
        }

        if (moving) {
            player.frameTimer += dt;
            if (player.frameTimer > 0.12) { player.frameTimer = 0; player.frameIdx = (player.frameIdx + 1) % sprites.frames.playerFrames.length; }
        } else { player.frameIdx = 0; player.frameTimer = 0; }

        if (tileAt(player.x, player.y) === KEY) { player.hasKey = true; map[player.y][player.x] = FLOOR; message = 'Got the key! Doors will open.'; playSound('pickup'); }

        if (tileAt(player.x, player.y) === EXIT) {
            playSound('win');
            if (levelIndex + 1 < levels.length) { message = 'Level complete!'; player.alive = false; reportRun('completed'); setTimeout(nextLevel, 400); }
            else { message = 'You escaped — All levels complete!'; player.alive = false; reportRun('completed'); }
            return;
        }

        if (player.hasKey) { for (let y = 0; y < map.length; y++) for (let x = 0; x < map[0].length; x++) if (map[y][x] === DOOR) map[y][x] = FLOOR; }

        guards.forEach((g) => {
            const gx = Math.floor((g.px + TILE / 2) / TILE), gy = Math.floor((g.py + TILE / 2) / TILE);
            const vx = (player.px + TILE / 2) - (g.px + TILE / 2), vy = (player.py + TILE / 2) - (g.py + TILE / 2);
            const d = Math.hypot(vx, vy);
            const canSee = d < 160 && !rayBlocked(gx, gy, player.x, player.y);
            if (canSee) {
                if (g.state !== 'alert') playSound('alert');
                g.state = 'alert'; g.lastSeen = { x: player.x, y: player.y, time: performance.now() };
                const path = astar(gx, gy, player.x, player.y);
                if (path && path.length > 1) { g.path = path; g.pathIdx = 1; }
            }

            if (g.state === 'alert') {
                if (g.path && g.pathIdx < g.path.length) {
                    const [tx, ty] = [g.path[g.pathIdx][0] * TILE, g.path[g.pathIdx][1] * TILE];
                    const dxg = tx - g.px, dyg = ty - g.py, dist = Math.hypot(dxg, dyg);
                    if (dist < 2) g.pathIdx++; else { g.px += (dxg / dist) * g.spd; g.py += (dyg / dist) * g.spd; }
                } else if (g.lastSeen && performance.now() - g.lastSeen.time > 3000) { g.state = 'calm'; g.path = []; g.pathIdx = 0; }
            } else {
                const target = g.patrol[g.i];
                const tx = target[0] * TILE, ty = target[1] * TILE;
                const dxg = tx - g.px, dyg = ty - g.py, dist = Math.hypot(dxg, dyg);
                if (dist < 2) g.i = (g.i + 1) % g.patrol.length; else { g.px += (dxg / dist) * g.spd; g.py += (dyg / dist) * g.spd; }
            }

            g.frameTimer += dt;
            if (g.frameTimer > 0.18) { g.frameTimer = 0; g.frameIdx = (g.frameIdx + 1) % sprites.frames.guardFrames.length; }

            const pdx = (player.px + TILE / 2) - (g.px + TILE / 2), pdy = (player.py + TILE / 2) - (g.py + TILE / 2);
            if (player.alive && Math.hypot(pdx, pdy) < 12) { message = 'Caught by a guard! Game over.'; player.alive = false; playSound('caught'); reportRun('caught'); }
        });
    }

    // ---- draw ----------------------------------------------------------
    function drawTileFrame(name, x, y) { const f = sprites.frames[name]; ctx.drawImage(sprites.sheet, f.x * TILE, f.y * TILE, TILE, TILE, x, y, TILE, TILE); }

    function draw() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        for (let y = 0; y < map.length; y++) {
            for (let x = 0; x < map[0].length; x++) {
                const t = map[y][x], px = x * TILE, py = y * TILE;
                if (sprites.ready) {
                    drawTileFrame(t === WALL ? 'wall' : t === DOOR ? 'door' : t === KEY ? 'key' : t === EXIT ? 'exit' : 'floor', px, py);
                } else {
                    ctx.fillStyle = t === WALL ? '#444' : t === DOOR ? '#3a3' : t === KEY ? '#ff8800' : t === EXIT ? '#06f' : '#222';
                    ctx.fillRect(px, py, TILE, TILE);
                }
                ctx.strokeStyle = '#111'; ctx.strokeRect(px, py, TILE, TILE);
            }
        }

        if (sprites.ready) {
            const pf = sprites.frames.playerFrames[player.frameIdx % sprites.frames.playerFrames.length];
            ctx.drawImage(sprites.sheet, pf.x * TILE, pf.y * TILE, TILE, TILE, player.px, player.py, TILE, TILE);
        } else {
            ctx.fillStyle = player.alive ? '#ffdd00' : '#777'; ctx.fillRect(player.px + 6, player.py + 6, TILE - 12, TILE - 12);
        }

        guards.forEach((g) => {
            if (sprites.ready) {
                const gf = sprites.frames.guardFrames[g.frameIdx % sprites.frames.guardFrames.length];
                ctx.drawImage(sprites.sheet, gf.x * TILE, gf.y * TILE, TILE, TILE, g.px, g.py, TILE, TILE);
            } else {
                ctx.fillStyle = g.state === 'alert' ? '#ff5555' : '#c33'; ctx.fillRect(g.px + 6, g.py + 6, TILE - 12, TILE - 12);
            }
            if (g.state === 'alert') {
                ctx.beginPath(); ctx.ellipse(g.px + TILE / 2, g.py + TILE / 2, 120, 60, 0, -Math.PI / 2, Math.PI / 2);
                ctx.fillStyle = 'rgba(255,0,0,0.06)'; ctx.fill();
            }
            if (g.path && g.path.length) {
                ctx.strokeStyle = 'rgba(0,255,0,0.25)'; ctx.beginPath();
                g.path.forEach((p, i) => { const cx = p[0] * TILE + TILE / 2, cy = p[1] * TILE + TILE / 2; i === 0 ? ctx.moveTo(cx, cy) : ctx.lineTo(cx, cy); });
                ctx.stroke();
            }
        });

        const elapsed = player.alive ? (performance.now() - runStart) / 1000 : null;
        if (elapsed !== null) timerEl.textContent = `${elapsed.toFixed(1)}s`;
        info.textContent = `Level: ${levels[levelIndex].name}\nPlayer: (${player.x},${player.y})\nKey: ${player.hasKey ? 'Yes' : 'No'}\n${message}`;
    }

    let last = performance.now();
    function loop(t) { const dt = (t - last) / 1000; last = t; update(dt); draw(); requestAnimationFrame(loop); }
    loadLevel(0);
    requestAnimationFrame(loop);
}
