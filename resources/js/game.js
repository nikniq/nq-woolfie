// Mini Castle Wolfenstein — the client for the Laravel game API.
// Levels (rooms of one castle) are fetched from /api/levels; finished runs
// are posted to /api/runs so the leaderboard can rank escapes.

const TILE = 32;
const FLOOR = 0, WALL = 1, DOOR = 2, KEY = 3, EXIT = 4, TREASURE = 5, HIDE = 6, PORTAL = 7;
const SPIKES = 8, TRIPWIRE = 9, TRAPDOOR = 10, GAS = 11, CRUMBLE = 12, PIT = 13;
const PLANS = 14, GUN = 15, AMMO = 16, PRISONER = 17;
const GUN_AMMO = 6, AMMO_BOX = 4, BULLET_SPEED = 10, SHOT_NOISE = 320, KILL_POINTS = 50;
const ALARM_TIME = 20, ALARM_CHASE = 2.5, MAX_REINFORCEMENTS = 3;
const SPIKE_PERIOD = 2.6, SPIKE_WARN = 1.7, SPIKE_UP = 2.0; // seconds within each cycle
const GAS_PERIOD = 4.5, GAS_ON = 1.6, GAS_RADIUS = TILE * 1.4, DIZZY_TIME = 3.5;
const TREASURE_POINTS = 100;
const LEVEL_BONUS = 250;
const MAX_LIVES = 3;
const VIEW_DIST = 160;          // px
const VIEW_FOV = Math.PI * 0.7; // ~126 degrees
const ALERT_MEMORY_MS = 3000;
const WALK_SPEED = 3.6, SPRINT_SPEED = 6.2;
const SPRINT_NOISE = 110;       // px: guards inside this radius hear you
const WAYPOINT_WAIT = 0.7;      // s guards pause at patrol corners

const hash = (x, y) => { let h = (x * 374761393 + y * 668265263) | 0; h = (h ^ (h >> 13)) * 1274126177; return ((h ^ (h >> 16)) >>> 0) / 4294967296; };

export async function startGame() {
    const wrap = document.getElementById('gameWrap');
    const canvas = document.getElementById('game');
    const ctx = canvas.getContext('2d');
    const castleCanvas = document.getElementById('castle');
    const cctx = castleCanvas.getContext('2d');
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
    if (!levels.length) { info.textContent = 'No levels in the database.\nRun: php artisan db:seed'; return; }
    const levelByNumber = new Map(levels.map((l, i) => [l.number, i]));

    try { nameInput.value = localStorage.getItem('woolfie.name') ?? ''; } catch {}
    nameInput.addEventListener('change', () => { try { localStorage.setItem('woolfie.name', nameInput.value); } catch {} });

    // ---- state ---------------------------------------------------------
    // phase: title | playing | paused | map | levelclear | dead | gameover | won
    let phase = 'title';
    let levelIndex = 0;
    let map = [], guards = [], portals = [];
    let keysPressed = {};
    let message = '';
    let runStart = 0, pausedAt = 0, runReported = false;
    let score = 0, levelScore = 0, lives = MAX_LIVES;
    let treasureTotal = 0, treasureFound = 0;
    let completed = new Set(), visited = new Set();
    let levelStates = new Map(); // levelIndex -> saved room state (map, treasure, guards) for portal travel
    let campaignId = null;
    let flash = 0, shake = 0, time = 0;
    let particles = [], popups = [];
    let onPortal = false;
    let blades = [], bullets = [], bodies = [], reinforcements = [];
    let objectivesDone = new Map(); // levelIndex -> Set(objective ids)
    let levelAlarmed = false, alarmUntil = -1, sirenNext = 0, kills = 0, muzzle = 0;
    let dizzy = 0, lastTile = null, trippedAt = -1;
    const spikePhase = (x, y) => ((time + hash(x, y) * SPIKE_PERIOD) % SPIKE_PERIOD);
    const spikeState = (x, y) => { const ph = spikePhase(x, y); return ph >= SPIKE_UP ? 'up' : ph >= SPIKE_WARN ? 'warn' : 'down'; };
    const gasActive = (x, y) => ((time + hash(y, x) * GAS_PERIOD) % GAS_PERIOD) > GAS_PERIOD - GAS_ON;
    let gpPrev = {};

    const player = { x: 1, y: 1, px: TILE, py: TILE, hasKey: false, alive: true, dir: 'down', frameIdx: 0, frameTimer: 0, hidden: false, sprinting: false, gun: false, ammo: 0 };

    // ---- sprites -------------------------------------------------------
    function createSpriteSheet() {
        const c = document.createElement('canvas');
        c.width = TILE * 26; c.height = TILE * 4;
        const g = c.getContext('2d');
        const px = (x, y, w, h, col) => { g.fillStyle = col; g.fillRect(x, y, w, h); };
        const tile = (i, fn) => { g.save(); g.translate(i * TILE, 0); fn(); g.restore(); };

        // 0-3 floor variants: flagstones
        for (let v = 0; v < 4; v++) tile(v, () => {
            px(0, 0, TILE, TILE, '#24242a');
            const r = (a) => hash(v * 7 + a, v * 13 + a * 3);
            px(1, 1, 14, 14, r(1) > 0.5 ? '#2b2b32' : '#282830'); px(17, 1, 14, 14, r(2) > 0.5 ? '#2a2a31' : '#2d2d34');
            px(1, 17, 14, 14, r(3) > 0.5 ? '#2c2c33' : '#29292f'); px(17, 17, 14, 14, r(4) > 0.5 ? '#2b2b31' : '#2e2e35');
            if (r(5) > 0.6) px(4 + Math.floor(r(6) * 20), 4 + Math.floor(r(7) * 20), 3, 2, '#1d1d22');
            if (r(8) > 0.7) px(6 + Math.floor(r(9) * 18), 8 + Math.floor(r(10) * 16), 2, 2, '#35353d');
        });
        // 4 wall: stone blocks with lit top edge and dark base
        tile(4, () => {
            px(0, 0, TILE, TILE, '#4a4a52');
            for (let r = 0; r < 4; r++) { const off = (r % 2) * 8; for (let b = -1; b < 3; b++) { const bx = b * 16 + off + 1, by = r * 8 + 1; px(bx, by, 14, 6, '#5e5e68'); px(bx, by, 14, 1, '#74747f'); px(bx, by + 5, 14, 1, '#44444c'); } }
            px(0, 0, TILE, 3, '#8a8a96'); px(0, TILE - 3, TILE, 3, '#2e2e34');
            px(0, 0, 2, TILE, '#6c6c76');
        });
        // 5 door: iron-banded wood
        tile(5, () => { px(0, 0, TILE, TILE, '#2b1d10'); px(3, 1, 26, 30, '#6b4a24'); for (let i = 0; i < 4; i++) px(3 + i * 7, 1, 1, 30, '#4b3217'); px(3, 7, 26, 3, '#3a3a40'); px(3, 22, 26, 3, '#3a3a40'); px(21, 14, 4, 4, '#e8c33a'); px(22, 15, 2, 2, '#1a1a1a'); });
        // 6 key
        tile(6, () => { px(0, 0, TILE, TILE, '#24242a'); g.fillStyle = '#ffd23a'; g.beginPath(); g.arc(11, 14, 6, 0, Math.PI * 2); g.fill(); g.fillStyle = '#24242a'; g.beginPath(); g.arc(11, 14, 2.5, 0, Math.PI * 2); g.fill(); px(15, 13, 12, 3, '#ffd23a'); px(22, 16, 2, 4, '#ffd23a'); px(26, 16, 2, 3, '#ffd23a'); px(16, 13, 10, 1, '#fff3a8'); });
        // 7 exit: stone stairway going down with blue glow
        tile(7, () => { px(0, 0, TILE, TILE, '#101826'); for (let i = 0; i < 5; i++) { px(3 + i * 2, 4 + i * 5, 26 - i * 4, 5, i % 2 ? '#2d4f86' : '#3a64a8'); px(3 + i * 2, 4 + i * 5, 26 - i * 4, 1, '#6c9be0'); } px(0, 0, TILE, 2, '#4a4a52'); });
        // 8 treasure chest
        tile(8, () => { px(0, 0, TILE, TILE, '#24242a'); px(6, 12, 20, 14, '#7a4d1c'); px(6, 12, 20, 5, '#a8702a'); px(6, 17, 20, 1, '#3a2410'); px(14, 17, 4, 4, '#e8c33a'); px(8, 8, 16, 5, '#8c5a22'); px(9, 9, 14, 3, '#ffe066'); px(11, 6, 3, 3, '#fff3a8'); px(19, 5, 2, 2, '#fff3a8'); });
        // 9 hiding spot: stacked crates
        tile(9, () => { px(0, 0, TILE, TILE, '#24242a'); const crate = (x, y, s) => { px(x, y, s, s, '#6b4a24'); px(x + 1, y + 1, s - 2, s - 2, '#8a6230'); px(x + 1, y + 1, s - 2, 1, '#a87b3e'); px(x, y, 1, s, '#4b3217'); px(x + (s >> 1), y, 1, s, '#4b3217'); px(x, y + (s >> 1), s, 1, '#4b3217'); }; crate(2, 14, 16); crate(17, 16, 13); crate(8, 2, 14); });
        // 10 portal: stone archway into another room
        tile(10, () => { px(0, 0, TILE, TILE, '#1a1a1e'); g.fillStyle = '#5e5e68'; g.beginPath(); g.arc(16, 14, 14, Math.PI, 0); g.fill(); px(2, 14, 28, 18, '#5e5e68'); g.fillStyle = '#060608'; g.beginPath(); g.arc(16, 15, 10, Math.PI, 0); g.fill(); px(6, 15, 20, 17, '#060608'); px(13, 20, 6, 2, '#3a3a60'); px(6, 15, 20, 1, '#8a8a96'); });

        // 11 spikes retracted, 12 spikes warning, 13 spikes raised
        const spikeBase = () => { px(0, 0, TILE, TILE, '#24242a'); px(2, 2, 28, 28, '#1a1a1f'); for (let i = 0; i < 4; i++) for (let j = 0; j < 4; j++) px(5 + i * 7, 5 + j * 7, 3, 3, '#0d0d10'); };
        tile(11, spikeBase);
        tile(12, () => { spikeBase(); for (let i = 0; i < 4; i++) for (let j = 0; j < 4; j++) px(6 + i * 7, 5 + j * 7, 1, 3, '#b8b8c4'); });
        tile(13, () => { spikeBase(); g.fillStyle = '#d8d8e4'; for (let i = 0; i < 4; i++) for (let j = 0; j < 4; j++) { const sx = 6 + i * 7, sy = 2 + j * 7; g.beginPath(); g.moveTo(sx - 2, sy + 7); g.lineTo(sx + 0.5, sy); g.lineTo(sx + 3, sy + 7); g.closePath(); g.fill(); } px(0, 0, TILE, TILE, 'rgba(255,255,255,0.05)'); });
        // 14 tripwire
        tile(14, () => { px(0, 0, TILE, TILE, '#24242a'); px(0, 12, 3, 8, '#6b6b75'); px(29, 12, 3, 8, '#6b6b75'); px(3, 15, 26, 1, 'rgba(220,220,240,0.55)'); });
        // 15 trapdoor
        tile(15, () => { px(0, 0, TILE, TILE, '#24242a'); px(3, 3, 26, 26, '#3a2a16'); px(4, 4, 24, 24, '#5a4020'); for (let i = 0; i < 4; i++) px(4, 5 + i * 6, 24, 1, '#3a2a16'); px(6, 6, 20, 1, '#7a5a30'); px(14, 14, 4, 4, '#2a2a2e'); px(15, 15, 2, 2, '#111'); });
        // 16 gas vent
        tile(16, () => { px(0, 0, TILE, TILE, '#24242a'); g.fillStyle = '#3c4a3c'; g.beginPath(); g.arc(16, 16, 11, 0, Math.PI * 2); g.fill(); g.fillStyle = '#1c261c'; g.beginPath(); g.arc(16, 16, 8, 0, Math.PI * 2); g.fill(); px(10, 15, 12, 2, '#3c4a3c'); px(15, 10, 2, 12, '#3c4a3c'); px(9, 9, 2, 2, '#5c6a5c'); px(21, 21, 2, 2, '#5c6a5c'); });
        // 17 crumbling floor, 18 pit
        tile(17, () => { px(0, 0, TILE, TILE, '#2a2a30'); g.strokeStyle = '#111'; g.lineWidth = 1; g.beginPath(); g.moveTo(4, 2); g.lineTo(14, 12); g.lineTo(10, 20); g.lineTo(18, 30); g.moveTo(14, 12); g.lineTo(26, 8); g.moveTo(10, 20); g.lineTo(2, 24); g.moveTo(18, 22); g.lineTo(28, 26); g.stroke(); px(1, 1, 14, 14, 'rgba(0,0,0,0.12)'); });
        tile(18, () => { px(0, 0, TILE, TILE, '#050507'); px(0, 0, TILE, 3, '#1d1d22'); px(0, 0, 3, TILE, '#1a1a1f'); px(0, 3, TILE, 5, 'rgba(60,60,70,0.25)'); });

        // 19 war plans (a folder with a red seal), 20 pistol, 21 ammo box, 22 prisoner cell
        tile(19, () => { px(0, 0, TILE, TILE, '#24242a'); px(7, 6, 18, 22, '#d8cfae'); px(7, 6, 18, 3, '#b8a97a'); px(10, 12, 12, 1, '#6a6a6a'); px(10, 15, 12, 1, '#6a6a6a'); px(10, 18, 8, 1, '#6a6a6a'); g.fillStyle = '#c33'; g.beginPath(); g.arc(20, 23, 3, 0, Math.PI * 2); g.fill(); });
        tile(20, () => { px(0, 0, TILE, TILE, '#24242a'); px(6, 12, 20, 5, '#2a2a30'); px(7, 13, 18, 1, '#55555f'); px(8, 16, 7, 10, '#3a2a16'); px(9, 17, 5, 8, '#5a4020'); px(14, 17, 4, 4, '#2a2a30'); px(24, 12, 3, 3, '#1a1a1e'); });
        tile(21, () => { px(0, 0, TILE, TILE, '#24242a'); px(7, 11, 18, 14, '#4a5a3a'); px(7, 11, 18, 3, '#6a7a4a'); px(9, 16, 14, 6, '#2a2a30'); for (let i = 0; i < 4; i++) px(10 + i * 3.5, 17, 2, 4, '#d8b860'); });
        tile(22, () => { px(0, 0, TILE, TILE, '#1a1a1e'); px(10, 9, 12, 14, '#5a4a3a'); px(12, 5, 8, 6, '#e8b990'); px(12, 4, 8, 2, '#3a2a16'); for (let i = 0; i < 5; i++) px(3 + i * 6, 2, 2, 28, '#7a7a86'); px(2, 2, 28, 2, '#8a8a96'); px(2, 28, 28, 2, '#8a8a96'); });

        // rows 1-2: player (4 dirs x 4 frames); row 3: guards (4 dirs x 2 frames)
        const dirs = ['down', 'left', 'up', 'right'];
        const figure = (x, y, dir, frame, cfg) => {
            px(x, y, TILE, TILE, 'rgba(0,0,0,0)');
            g.clearRect(x, y, TILE, TILE);
            const bob = frame % 2 === 0 ? 0 : 1;
            const step = frame % 4; const l = step === 1 ? 2 : step === 3 ? -2 : 0;
            // shadow
            g.fillStyle = 'rgba(0,0,0,0.35)'; g.beginPath(); g.ellipse(x + 16, y + 29, 9, 3, 0, 0, Math.PI * 2); g.fill();
            // legs
            px(x + 10 + l, y + 22, 5, 7, cfg.trousers); px(x + 17 - l, y + 22, 5, 7, cfg.trousers);
            px(x + 10 + l, y + 27, 5, 2, '#111'); px(x + 17 - l, y + 27, 5, 2, '#111');
            // torso
            px(x + 9, y + 11 + bob, 14, 12, cfg.coat); px(x + 9, y + 11 + bob, 14, 1, cfg.coatLight);
            if (cfg.belt) px(x + 9, y + 18 + bob, 14, 2, '#222');
            // arms
            if (dir === 'left') px(x + 7, y + 12 + bob, 3, 9, cfg.coat); else if (dir === 'right') px(x + 22, y + 12 + bob, 3, 9, cfg.coat);
            else { px(x + 6, y + 12 + bob, 3, 9, cfg.coat); px(x + 23, y + 12 + bob, 3, 9, cfg.coat); }
            // rifle for guards
            if (cfg.rifle) { if (dir === 'left') px(x + 2, y + 13 + bob, 10, 2, '#2a2a2a'); else if (dir === 'right') px(x + 20, y + 13 + bob, 10, 2, '#2a2a2a'); else if (dir === 'up') px(x + 24, y + 4 + bob, 2, 14, '#2a2a2a'); else px(x + 24, y + 12 + bob, 2, 12, '#2a2a2a'); }
            // head
            px(x + 11, y + 3 + bob, 10, 9, cfg.skin);
            if (cfg.helmet) { px(x + 10, y + 1 + bob, 12, 5, cfg.helmet); px(x + 9, y + 5 + bob, 14, 2, cfg.helmet); px(x + 10, y + 1 + bob, 12, 1, cfg.helmetLight); }
            else { px(x + 11, y + 2 + bob, 10, 3, cfg.hair); px(x + 11, y + 4 + bob, 2, 3, cfg.hair); px(x + 19, y + 4 + bob, 2, 3, cfg.hair); }
            // eyes show facing
            g.fillStyle = '#111';
            if (dir === 'down') { g.fillRect(x + 13, y + 7 + bob, 2, 2); g.fillRect(x + 17, y + 7 + bob, 2, 2); }
            else if (dir === 'left') g.fillRect(x + 11, y + 7 + bob, 2, 2);
            else if (dir === 'right') g.fillRect(x + 19, y + 7 + bob, 2, 2);
        };
        const playerCfg = { coat: '#3f6bb5', coatLight: '#6a93d8', trousers: '#2c3e63', skin: '#f1c9a0', hair: '#4a2e14', belt: true };
        const guardCfg = { coat: '#5c6b4e', coatLight: '#7d8c6c', trousers: '#3e4a36', skin: '#e8b990', helmet: '#4d5050', helmetLight: '#7b8080', rifle: true, belt: true };
        dirs.forEach((d, di) => { for (let f = 0; f < 4; f++) figure((di % 2) * 4 * TILE + f * TILE, TILE + Math.floor(di / 2) * TILE, d, f, playerCfg); });
        dirs.forEach((d, di) => { for (let f = 0; f < 2; f++) figure(di * 2 * TILE + f * TILE, TILE * 3, d, f, guardCfg); });

        const img = new Image(); img.src = c.toDataURL();
        const pf = {}, gf = {};
        dirs.forEach((d, di) => {
            pf[d] = [0, 1, 2, 3].map((f) => ({ x: (di % 2) * 4 + f, y: 1 + Math.floor(di / 2) }));
            gf[d] = [0, 1].map((f) => ({ x: di * 2 + f, y: 3 }));
        });
        return { img, frames: { tiles: { [WALL]: 4, [DOOR]: 5, [KEY]: 6, [EXIT]: 7, [TREASURE]: 8, [HIDE]: 9, [PORTAL]: 10, [SPIKES]: 11, [TRIPWIRE]: 14, [TRAPDOOR]: 15, [GAS]: 16, [CRUMBLE]: 17, [PIT]: 18, [PLANS]: 19, [GUN]: 20, [AMMO]: 21, [PRISONER]: 22 }, spikes: [11, 12, 13], player: pf, guard: gf } };
    }
    const sheet = createSpriteSheet();
    const sprites = { sheet: sheet.img, frames: sheet.frames, ready: false };
    sprites.sheet.onload = () => { sprites.ready = true; };
    const light = document.createElement('canvas'); const lctx = light.getContext('2d');

    // ---- audio: Wolfenstein-style retro synth, overridable by files ---------
    // Drop your own clips in public_html/sounds/<name>.(mp3|ogg|wav) and they
    // replace the synthesised versions (the server lists them in data-sounds).
    // Names: alert, caught, pickup, treasure, door, win, gameover, hide, portal,
    // alarm, spikes, crumble, gas, fall, blade, step.
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    let audioCtx = null, noiseBuf = null;
    const sampleFiles = {}; let samplesLoaded = false;
    function ensureAudio() {
        if (!AudioCtx) return;
        if (!audioCtx) {
            audioCtx = new AudioCtx();
            noiseBuf = audioCtx.createBuffer(1, audioCtx.sampleRate, audioCtx.sampleRate);
            const d = noiseBuf.getChannelData(0); for (let i = 0; i < d.length; i++) d[i] = Math.random() * 2 - 1;
            loadSamples();
        }
        if (audioCtx.state === 'suspended') audioCtx.resume();
    }
    async function loadSamples() {
        if (samplesLoaded) return; samplesLoaded = true;
        let files = {};
        try { files = JSON.parse(wrap.dataset.sounds || '{}'); } catch { /* no custom clips */ }
        for (const [name, url] of Object.entries(files)) {
            try {
                const res = await fetch(url);
                if (res.ok) sampleFiles[name] = await audioCtx.decodeAudioData(await res.arrayBuffer());
            } catch { /* fall back to the synthesised effect */ }
        }
    }
    const master = () => { const g = audioCtx.createGain(); g.gain.value = 0.25; g.connect(audioCtx.destination); return g; };
    function tone({ freq = 440, to = null, dur = 0.1, type = 'square', vol = 1, delay = 0 }) {
        const t0 = audioCtx.currentTime + delay; const o = audioCtx.createOscillator(); const g = audioCtx.createGain();
        o.type = type; o.frequency.setValueAtTime(freq, t0); if (to) o.frequency.exponentialRampToValueAtTime(to, t0 + dur);
        g.gain.setValueAtTime(0.0001, t0); g.gain.exponentialRampToValueAtTime(vol, t0 + 0.01); g.gain.exponentialRampToValueAtTime(0.0001, t0 + dur);
        o.connect(g); g.connect(master()); o.start(t0); o.stop(t0 + dur + 0.05);
    }
    function noise({ dur = 0.2, vol = 1, delay = 0, filter = 1200, q = 0.7 }) {
        const t0 = audioCtx.currentTime + delay; const s = audioCtx.createBufferSource(); s.buffer = noiseBuf;
        const f = audioCtx.createBiquadFilter(); f.type = 'bandpass'; f.frequency.value = filter; f.Q.value = q;
        const g = audioCtx.createGain(); g.gain.setValueAtTime(vol, t0); g.gain.exponentialRampToValueAtTime(0.0001, t0 + dur);
        s.connect(f); f.connect(g); g.connect(master()); s.start(t0); s.stop(t0 + dur + 0.05);
    }
    const synth = {
        // guard shout: a barked two-syllable "Ach-tung!"
        alert: () => { noise({ dur: 0.12, vol: 0.5, filter: 900 }); tone({ freq: 330, to: 250, dur: 0.12, type: 'sawtooth' }); tone({ freq: 420, to: 300, dur: 0.18, type: 'sawtooth', delay: 0.14 }); noise({ dur: 0.18, vol: 0.4, filter: 1400, delay: 0.14 }); },
        // rifle shot: crack + low thump
        caught: () => { noise({ dur: 0.25, vol: 1, filter: 2500, q: 0.3 }); tone({ freq: 140, to: 40, dur: 0.35, type: 'triangle', vol: 1 }); noise({ dur: 0.5, vol: 0.5, filter: 300, delay: 0.05 }); },
        pickup: () => { tone({ freq: 660, dur: 0.07 }); tone({ freq: 880, dur: 0.07, delay: 0.08 }); tone({ freq: 1320, dur: 0.16, delay: 0.16 }); },
        treasure: () => { tone({ freq: 1047, dur: 0.06, type: 'triangle' }); tone({ freq: 1319, dur: 0.06, type: 'triangle', delay: 0.07 }); tone({ freq: 1568, dur: 0.14, type: 'triangle', delay: 0.14 }); },
        door: () => { tone({ freq: 90, to: 60, dur: 0.45, type: 'sawtooth', vol: 0.6 }); noise({ dur: 0.4, vol: 0.3, filter: 500 }); },
        win: () => { [523, 659, 784, 1047].forEach((f, i) => tone({ freq: f, dur: i === 3 ? 0.4 : 0.12, type: 'square', delay: i * 0.13 })); },
        gameover: () => { [392, 330, 262, 196].forEach((f, i) => tone({ freq: f, dur: i === 3 ? 0.7 : 0.25, type: 'sawtooth', delay: i * 0.28 })); },
        hide: () => tone({ freq: 220, to: 160, dur: 0.12, type: 'triangle', vol: 0.5 }),
        portal: () => { tone({ freq: 200, to: 600, dur: 0.3, type: 'sine' }); noise({ dur: 0.3, vol: 0.3, filter: 800 }); },
        step: () => noise({ dur: 0.04, vol: 0.12, filter: 600 }),
        // alarm bell: tripwire pulled
        alarm: () => { for (let i = 0; i < 4; i++) { tone({ freq: 1760, dur: 0.18, type: 'triangle', delay: i * 0.22 }); tone({ freq: 2217, dur: 0.14, type: 'sine', delay: i * 0.22 + 0.02, vol: 0.6 }); } },
        spikes: () => { noise({ dur: 0.12, vol: 0.7, filter: 3500, q: 0.4 }); tone({ freq: 2400, to: 1200, dur: 0.15, type: 'triangle', vol: 0.5 }); },
        crumble: () => { noise({ dur: 0.45, vol: 0.8, filter: 250, q: 0.5 }); tone({ freq: 90, to: 35, dur: 0.5, type: 'triangle', vol: 0.7 }); },
        gas: () => noise({ dur: 1.2, vol: 0.35, filter: 1800, q: 0.2 }),
        fall: () => { tone({ freq: 700, to: 90, dur: 0.6, type: 'sine' }); noise({ dur: 0.3, vol: 0.5, filter: 400, delay: 0.5 }); },
        blade: () => noise({ dur: 0.15, vol: 0.25, filter: 2000, q: 0.5 }),
        shoot: () => { noise({ dur: 0.18, vol: 1, filter: 3000, q: 0.3 }); tone({ freq: 220, to: 50, dur: 0.22, type: 'square', vol: 0.8 }); noise({ dur: 0.4, vol: 0.4, filter: 400, delay: 0.03 }); },
        empty: () => { noise({ dur: 0.03, vol: 0.5, filter: 4000, q: 1 }); tone({ freq: 1200, dur: 0.03, type: 'square', vol: 0.3 }); },
        hit: () => { tone({ freq: 180, to: 60, dur: 0.25, type: 'sawtooth', vol: 0.7 }); noise({ dur: 0.2, vol: 0.5, filter: 700 }); },
        siren: () => { tone({ freq: 520, dur: 0.35, type: 'square', vol: 0.35 }); tone({ freq: 660, dur: 0.35, type: 'square', vol: 0.35, delay: 0.38 }); },
        free: () => { [660, 880, 1100, 1320].forEach((f, i) => tone({ freq: f, dur: 0.1, type: 'triangle', delay: i * 0.09 })); },
        plans: () => { [392, 523, 659, 784, 1047].forEach((f, i) => tone({ freq: f, dur: i === 4 ? 0.5 : 0.11, type: 'square', delay: i * 0.12 })); },
        reinforce: () => { tone({ freq: 90, to: 60, dur: 0.3, type: 'sawtooth', vol: 0.7 }); noise({ dur: 0.25, vol: 0.5, filter: 600 }); tone({ freq: 330, to: 250, dur: 0.14, type: 'sawtooth', delay: 0.3 }); },
    };
    function playSound(name) {
        if (!soundCheckbox.checked) return;
        ensureAudio(); if (!audioCtx) return;
        if (sampleFiles[name]) { const s = audioCtx.createBufferSource(); s.buffer = sampleFiles[name]; s.connect(master()); s.start(); return; }
        synth[name]?.();
    }

    // ---- helpers -------------------------------------------------------
    const now = () => performance.now();
    const elapsedMs = () => ((phase === 'paused' || phase === 'map') ? pausedAt : now()) - runStart;
    function tileAt(x, y) { if (y < 0 || y >= map.length || x < 0 || x >= map[0].length) return WALL; return map[y][x]; }
    function walkable(x, y, ignoreDoors = false, forGuard = false) { const t = tileAt(x, y); if (t === WALL || t === PIT) return false; if (t === DOOR && !ignoreDoors) return false; if (forGuard && t === TRAPDOOR) return false; return true; }
    const DIR_ANGLE = { right: 0, down: Math.PI / 2, left: Math.PI, up: -Math.PI / 2 };
    const dirFromVec = (dx, dy) => (Math.abs(dx) > Math.abs(dy) ? (dx > 0 ? 'right' : 'left') : (dy > 0 ? 'down' : 'up'));
    const spawnParticles = (x, y, n, col, speed = 60) => { for (let i = 0; i < n; i++) { const a = Math.random() * Math.PI * 2, v = speed * (0.4 + Math.random()); particles.push({ x, y, vx: Math.cos(a) * v, vy: Math.sin(a) * v - 30, life: 0.5 + Math.random() * 0.5, col }); } };
    const popup = (text, x, y, col = '#ffe066') => popups.push({ text, x, y, life: 1.1, col });

    const freshGuard = (g) => ({ patrol: g.patrol.slice(), i: 0, spd: g.spd, state: 'calm', path: [], pathIdx: 0, lastSeen: null, wait: 0, px: g.patrol[0][0] * TILE, py: g.patrol[0][1] * TILE, dir: 'down', frameIdx: 0, frameTimer: 0 });
    function makeRoomState(i) {
        const lvl = levels[i];
        return { map: lvl.map.map((r) => r.slice()), hasKey: false, treasureFound: 0, levelScore: 0, alarmed: false, bodies: [],
            guards: lvl.guards.map(freshGuard),
            blades: (lvl.traps ?? []).filter((t) => t.type === 'blade').map((t) => ({ ...t, px: t.from[0] * TILE, py: t.from[1] * TILE, dir: 1, angle: 0 })) };
    }
    const roomState = (i) => { if (!levelStates.has(i)) levelStates.set(i, makeRoomState(i)); return levelStates.get(i); };
    function saveState() {
        levelStates.set(levelIndex, { map: map.map((r) => r.slice()), hasKey: player.hasKey, treasureFound, levelScore, alarmed: levelAlarmed, bodies: bodies.map((b) => ({ ...b })), guards: guards.map((g) => ({ ...g })), blades: blades.map((b) => ({ ...b })) });
    }
    function loadLevel(i, { fromPortal = null, resume = false } = {}) {
        levelIndex = i;
        const lvl = levels[i];
        const st = resume ? roomState(i) : makeRoomState(i);
        map = st.map.map((row) => row.slice());
        canvas.width = map[0].length * TILE; canvas.height = map.length * TILE; light.width = canvas.width; light.height = canvas.height;
        portals = lvl.portals ?? [];
        const start = fromPortal ? [fromPortal.x, fromPortal.y] : lvl.player_start;
        player.x = start[0]; player.y = start[1]; player.px = player.x * TILE; player.py = player.y * TILE;
        player.hasKey = st.hasKey; player.alive = true; player.dir = 'down'; player.hidden = false;
        treasureTotal = lvl.map.flat().filter((t) => t === TREASURE).length;
        treasureFound = st.treasureFound; levelScore = st.levelScore; levelAlarmed = st.alarmed;
        guards = st.guards.map((g) => ({ ...g, path: [...(g.path ?? [])] }));
        blades = st.blades.map((b) => ({ ...b })); bodies = st.bodies.map((b) => ({ ...b }));
        bullets = []; reinforcements = []; alarmUntil = -1; muzzle = 0;
        onPortal = !!fromPortal;
        dizzy = 0; lastTile = [player.x, player.y]; trippedAt = -1;
        if (!resume) { runStart = now(); runReported = false; message = ''; }
        visited.add(i);
        levelSpan.textContent = lvl.number;
        particles = []; popups = [];
        phase = 'playing';
    }

    function startCampaign() {
        score = 0; lives = MAX_LIVES; completed = new Set(); visited = new Set(); levelStates = new Map(); objectivesDone = new Map(); kills = 0; player.gun = false; player.ammo = 0;
        campaignId = (crypto.randomUUID?.() ?? `${Date.now()}-${Math.random()}`.slice(0, 36));
        loadLevel(0);
    }

    async function reportRun(outcome) {
        if (runReported) return;
        runReported = true;
        const payload = { level_id: levels[levelIndex].id, player_name: nameInput.value, outcome, time_ms: Math.round(elapsedMs()), score: levelScore, campaign: campaignId };
        try {
            const res = await fetch(api.runs, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': api.csrf }, body: JSON.stringify(payload) });
            if (!res.ok) return;
            const body = await res.json();
            if (outcome === 'completed') message += `\nTime ${(payload.time_ms / 1000).toFixed(2)}s` + (body.is_personal_best ? ' — fastest ever on this room!' : ` (record ${(body.best_time_ms / 1000).toFixed(2)}s)`);
        } catch { /* leaderboard is optional */ }
    }

    const doneSet = (i) => { if (!objectivesDone.has(i)) objectivesDone.set(i, new Set()); return objectivesDone.get(i); };
    const isDone = (i, id) => doneSet(i).has(id);
    function markObjective(id, { silent = false } = {}) {
        const obj = (levels[levelIndex].objectives ?? []).find((o) => o.id === id);
        if (!obj || isDone(levelIndex, id)) return;
        doneSet(levelIndex).add(id); levelScore += obj.points; score += obj.points;
        if (!silent) { popup(`${obj.label} +${obj.points}`, player.px + 16, player.py - 16, '#9fffa0'); }
    }
    function missingRequired() { const out = []; levels.forEach((l, i) => (l.objectives ?? []).forEach((o) => { if (o.required && !isDone(i, o.id)) out.push({ level: i, obj: o }); })); return out; }
    function nextTarget() {
        for (let k = 1; k <= levels.length; k++) { const j = (levelIndex + k) % levels.length; if (!completed.has(j)) return j; }
        const miss = missingRequired(); return miss.length ? miss[0].level : -1;
    }
    function completeLevel() {
        levelScore += LEVEL_BONUS; score += LEVEL_BONUS;
        if (treasureFound === treasureTotal && treasureTotal > 0) markObjective('all_gold', { silent: true });
        if (!levelAlarmed) markObjective('no_alarm', { silent: true });
        const earned = (levels[levelIndex].objectives ?? []).filter((o) => isDone(levelIndex, o.id)).map((o) => o.label);
        message = `Room clear! +${LEVEL_BONUS}` + (earned.length ? `\nObjectives: ${earned.join(', ')}` : '');
        player.alive = false; completed.add(levelIndex); saveState();
        playSound('win'); spawnParticles(player.px + 16, player.py + 16, 30, '#6c9be0', 90);
        reportRun('completed');
        const miss = missingRequired();
        if (nextTarget() === -1) phase = 'won';
        else { phase = 'levelclear'; if (completed.size === levels.length && miss.length) message += `\nStill needed: ${miss[0].obj.label} (${levels[miss[0].level].name})`; }
    }
    function caught() { die('Caught by a guard!'); }
    function die(reason) {
        message = reason;
        player.alive = false; flash = 0.4; shake = 0.4;
        lives -= 1; score -= levelScore; // gold respawns with the room, so give it back
        levelStates.delete(levelIndex);
        spawnParticles(player.px + 16, player.py + 16, 20, '#ff4040', 80);
        reportRun('caught');
        if (lives <= 0) { phase = 'gameover'; playSound('gameover'); } else { phase = 'dead'; playSound('caught'); }
    }
    function travel(portal) {
        saveState();
        const dest = levelByNumber.get(portal.to_level);
        const back = (levels[dest].portals ?? []).find((p) => p.id === portal.id) ?? null;
        playSound('portal');
        enterRoom(dest, back, `Entered ${levels[dest].name}`);
    }
    function enterRoom(dest, at, note) {
        loadLevel(dest, { fromPortal: at, resume: levelStates.has(dest) });
        runStart = now(); runReported = false; // each room's timer restarts on entry
        message = note;
        popup(levels[dest].name, player.px + 16, player.py - 6, '#9fd0ff');
    }
    function fallThroughTrapdoor() {
        const cur = levels[levelIndex];
        let dest = levels.findIndex((l) => l.castle_x === cur.castle_x && l.castle_y === cur.castle_y + 1);
        if (dest === -1) { let best = -1, by = -1; levels.forEach((l, i) => { if (i !== levelIndex && l.castle_y > by) { by = l.castle_y; best = i; } }); dest = best; }
        if (dest === -1) return;
        map[player.y][player.x] = PIT; // the trapdoor stays open behind you
        saveState(); playSound('fall'); shake = 0.5;
        const start = levels[dest].player_start;
        enterRoom(dest, { x: start[0], y: start[1] }, `Fell through a trapdoor into ${levels[dest].name}!`);
        onPortal = false;
    }
    function alertAllGuards() {
        for (const g of guards) { g.state = 'alert'; g.wait = 0; g.lastSeen = { x: player.x, y: player.y, time: now() }; g.alertAt = g.alertAt ?? time; const gx = Math.floor((g.px + TILE / 2) / TILE), gy = Math.floor((g.py + TILE / 2) / TILE); const path = astar(gx, gy, player.x, player.y); if (path && path.length > 1) { g.path = path; g.pathIdx = 1; } }
    }
    // The alarm: every guard in the room converges on you, and guards from
    // the rooms connected to this one (portals, and the stairs up and down)
    // come running through those entrances. They leave their own rooms.
    function raiseAlarm(reason) {
        alertAllGuards();
        if (alarmUntil > time) { alarmUntil = time + ALARM_TIME; return; }
        playSound('alarm'); message = reason; flash = 0.2; levelAlarmed = true;
        alarmUntil = time + ALARM_TIME; sirenNext = time + 1.2;
        const entrances = [];
        for (const p of portals) entrances.push({ room: levelByNumber.get(p.to_level), at: [p.x, p.y] });
        if (levelIndex > 0) entrances.push({ room: levelIndex - 1, at: levels[levelIndex].player_start });
        if (levelIndex + 1 < levels.length) { let exit = null; map.forEach((r, y) => r.forEach((t, x) => { if (t === EXIT) exit = [x, y]; })); if (exit) entrances.push({ room: levelIndex + 1, at: exit }); }
        let n = 0;
        for (const e of entrances) {
            if (n >= MAX_REINFORCEMENTS) break;
            const st = roomState(e.room);
            if (!st.guards.length) continue;
            const g = st.guards.shift(); // that guard is now gone from its own room
            reinforcements.push({ due: time + 1.5 + n * 1.4, at: e.at, spd: Math.max(1.4, g.spd), from: levels[e.room].name });
            n++;
        }
        if (n) popup('Reinforcements are coming!', player.px + 16, player.py - 28, '#ff6060');
    }
    function spawnReinforcements() {
        while (reinforcements.length && reinforcements[0].due <= time) {
            const r = reinforcements.shift();
            const g = { ...freshGuard({ patrol: [r.at, r.at], spd: r.spd }), state: 'alert', alertAt: time, lastSeen: { x: player.x, y: player.y, time: now() } };
            const path = astar(r.at[0], r.at[1], player.x, player.y); if (path && path.length > 1) { g.path = path; g.pathIdx = 1; }
            guards.push(g); playSound('reinforce');
            popup(`Guard from ${r.from}!`, r.at[0] * TILE + 16, r.at[1] * TILE - 6, '#ff6060');
            spawnParticles(r.at[0] * TILE + 16, r.at[1] * TILE + 16, 10, '#ff6060', 40);
        }
    }

    // ---- gun -----------------------------------------------------------
    function fire() {
        if (phase !== 'playing' || !player.gun) return;
        if (player.ammo <= 0) { playSound('empty'); message = 'Click. Out of ammo.'; return; }
        player.ammo--; muzzle = 0.08; playSound('shoot'); shake = 0.15;
        const a = DIR_ANGLE[player.dir];
        bullets.push({ x: player.px + 16 + Math.cos(a) * 12, y: player.py + 16 + Math.sin(a) * 12, vx: Math.cos(a) * BULLET_SPEED, vy: Math.sin(a) * BULLET_SPEED, life: 1.2 });
        player.hidden = false;
        for (const g of guards) if (Math.hypot(g.px - player.px, g.py - player.py) < SHOT_NOISE) { g.state = 'alert'; g.lastSeen = { x: player.x, y: player.y, time: now() }; }
        raiseAlarm('Gunshot! The whole castle heard that.');
    }
    function updateBullets(dt) {
        bullets = bullets.filter((b) => {
            b.life -= dt; if (b.life <= 0) return false;
            for (let i = 0; i < 2; i++) {
                b.x += b.vx / 2; b.y += b.vy / 2;
                const tx = Math.floor(b.x / TILE), ty = Math.floor(b.y / TILE), t = tileAt(tx, ty);
                if (t === WALL || t === DOOR) { spawnParticles(b.x, b.y, 5, '#ffd080', 40); return false; }
                for (let k = 0; k < guards.length; k++) {
                    const g = guards[k];
                    if (Math.hypot(g.px + 16 - b.x, g.py + 16 - b.y) < 13) {
                        guards.splice(k, 1); bodies.push({ px: g.px, py: g.py, dir: g.dir }); kills++;
                        score += KILL_POINTS; levelScore += KILL_POINTS; playSound('hit');
                        spawnParticles(g.px + 16, g.py + 16, 16, '#c02020', 70); popup(`+${KILL_POINTS}`, g.px + 16, g.py - 6, '#ff8080');
                        return false;
                    }
                }
            }
            return true;
        });
    }

    // ---- input ---------------------------------------------------------
    function advance() {
        ensureAudio();
        if (phase === 'title' || phase === 'gameover' || phase === 'won') startCampaign();
        else if (phase === 'levelclear') { const t = nextTarget(); loadLevel(t, { resume: completed.has(t) }); }
        else if (phase === 'dead') loadLevel(levelIndex);
        else if (phase === 'paused' || phase === 'map') togglePause();
    }
    function togglePause(mode = 'paused') {
        if (phase === 'playing') { phase = mode; pausedAt = now(); }
        else if (phase === 'paused' || phase === 'map') { runStart += now() - pausedAt; phase = 'playing'; }
    }
    function restartLevel() { if (phase === 'title') return; if (phase === 'playing' || phase === 'paused' || phase === 'map') score -= levelScore; levelStates.delete(levelIndex); loadLevel(levelIndex); }

    restartBtn.onclick = restartLevel;
    window.addEventListener('keydown', (e) => {
        if (e.target === nameInput) return;
        const k = e.key.toLowerCase();
        keysPressed[k] = true;
        if (k === ' ' || k === 'enter') { e.preventDefault(); advance(); }
        else if (k === 'p' || k === 'escape') togglePause();
        else if (k === 'm') { if (phase === 'playing') togglePause('map'); else if (phase === 'map') togglePause(); }
        else if (k === 'r') restartLevel();
        else if (k === 'f' || k === 'control') { e.preventDefault(); fire(); }
        if (e.key.startsWith('Arrow')) e.preventDefault();
    });
    window.addEventListener('keyup', (e) => { keysPressed[e.key.toLowerCase()] = false; });
    canvas.addEventListener('pointerdown', () => { if (phase !== 'playing') advance(); });
    const dirKey = { up: 'arrowup', down: 'arrowdown', left: 'arrowleft', right: 'arrowright' };
    document.querySelectorAll('#touch button').forEach((b) => {
        const k = dirKey[b.dataset.dir];
        const on = (e) => { e.preventDefault(); keysPressed[k] = true; if (phase !== 'playing') advance(); };
        const off = (e) => { e.preventDefault(); keysPressed[k] = false; };
        b.addEventListener('pointerdown', on); b.addEventListener('pointerup', off); b.addEventListener('pointercancel', off); b.addEventListener('pointerleave', off);
    });
    document.querySelector('#touch [data-action=fire]')?.addEventListener('pointerdown', (e) => { e.preventDefault(); if (phase !== 'playing') advance(); else fire(); });
    function pollGamepad() {
        const gp = navigator.getGamepads?.()?.[0]; if (!gp) return;
        const ax = gp.axes[0] ?? 0, ay = gp.axes[1] ?? 0;
        const b = (i) => !!gp.buttons[i]?.pressed;
        keysPressed.gpleft = ax < -0.5 || b(14); keysPressed.gpright = ax > 0.5 || b(15);
        keysPressed.gpup = ay < -0.5 || b(12); keysPressed.gpdown = ay > 0.5 || b(13);
        keysPressed.gpsprint = b(2) || b(5) || b(7);
        const edge = (name, pressed, fn) => { if (pressed && !gpPrev[name]) fn(); gpPrev[name] = pressed; };
        edge('a', b(0), () => { if (phase !== 'playing') advance(); else fire(); });
        edge('b', b(1), fire);
        edge('start', b(9), () => togglePause());
        edge('select', b(8), () => { if (phase === 'playing') togglePause('map'); else if (phase === 'map') togglePause(); });
    }

    // ---- A* & LOS ------------------------------------------------------
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
                if (nx < 0 || ny < 0 || nx >= cols || ny >= rows || !walkable(nx, ny, false, true) || closed.has(key(nx, ny))) continue;
                const ng = g + 1; const existing = open.get(key(nx, ny));
                if (!existing || ng < existing.g) open.set(key(nx, ny), { x: nx, y: ny, g: ng, f: ng + h(nx, ny), came: best });
            }
        }
        return null;
    }
    function rayBlocked(x0, y0, x1, y1) {
        let dx = Math.abs(x1 - x0), sx = x0 < x1 ? 1 : -1, dy = -Math.abs(y1 - y0), sy = y0 < y1 ? 1 : -1, err = dx + dy, x = x0, y = y0;
        while (true) {
            if (x === x1 && y === y1) return false;
            const t = tileAt(x, y); if (t === WALL || t === DOOR) return true;
            const e2 = 2 * err; if (e2 >= dy) { err += dy; x += sx; } if (e2 <= dx) { err += dx; y += sy; }
        }
    }
    function moveGuardToward(g, tx, ty) {
        const spd = g.spd * (g.state === 'alert' ? 1.35 : 1);
        const dxg = tx - g.px, dyg = ty - g.py, dist = Math.hypot(dxg, dyg);
        if (dist < 2) { g.px = tx; g.py = ty; return true; }
        g.px += (dxg / dist) * Math.min(spd, dist); g.py += (dyg / dist) * Math.min(spd, dist); g.dir = dirFromVec(dxg, dyg);
        return false;
    }

    // ---- update --------------------------------------------------------
    let stepTimer = 0;
    function update(dt) {
        time += dt;
        if (flash > 0) flash -= dt; if (shake > 0) shake -= dt;
        particles = particles.filter((p) => (p.life -= dt) > 0); particles.forEach((p) => { p.x += p.vx * dt; p.y += p.vy * dt; p.vy += 120 * dt; });
        popups = popups.filter((p) => (p.life -= dt) > 0); popups.forEach((p) => { p.y -= 28 * dt; });
        pollGamepad();
        if (phase !== 'playing') return;
        if (muzzle > 0) muzzle -= dt;
        updateBullets(dt); spawnReinforcements();
        if (alarmUntil > time && time >= sirenNext) { playSound('siren'); sirenNext = time + 1.5; }

        let dx = 0, dy = 0;
        if (keysPressed['w'] || keysPressed['arrowup'] || keysPressed.gpup) dy = -1;
        if (keysPressed['s'] || keysPressed['arrowdown'] || keysPressed.gpdown) dy = 1;
        if (keysPressed['a'] || keysPressed['arrowleft'] || keysPressed.gpleft) dx = -1;
        if (keysPressed['d'] || keysPressed['arrowright'] || keysPressed.gpright) dx = 1;
        if (alarmUntil > time) { ctx.fillStyle = `rgba(255,0,0,${0.06 + 0.07 * Math.abs(Math.sin(time * 6))})`; ctx.fillRect(-20, -20, canvas.width + 40, canvas.height + 40); ctx.fillStyle = '#ff4040'; ctx.font = 'bold 16px Georgia, serif'; ctx.textAlign = 'center'; ctx.fillText(`ALARM  ${Math.ceil(alarmUntil - time)}`, canvas.width / 2, 20); ctx.textAlign = 'left'; }
        if (dizzy > 0) { dizzy -= dt; dx = -dx; dy = -dy; }
        player.sprinting = !!(keysPressed['shift'] || keysPressed.gpsprint) && (dx || dy);
        const speed = player.sprinting ? SPRINT_SPEED : WALK_SPEED;

        let moving = false;
        if (dx || dy) {
            moving = true; player.dir = dirFromVec(dx, dy);
            const n = dx && dy ? Math.SQRT1_2 : 1;
            for (const [mx, my] of [[dx * n, dy * n], [dx, 0], [0, dy]]) {
                if (!mx && !my) continue;
                const nx = player.px + mx * speed, ny = player.py + my * speed;
                const tx = Math.floor((nx + TILE / 2) / TILE), ty = Math.floor((ny + TILE / 2) / TILE);
                if (walkable(tx, ty, true) && (tileAt(tx, ty) !== DOOR || player.hasKey)) { player.px = nx; player.py = ny; player.x = tx; player.y = ty; break; }
            }
        }
        if (moving) {
            player.frameTimer += dt * (player.sprinting ? 1.6 : 1);
            if (player.frameTimer > 0.12) { player.frameTimer = 0; player.frameIdx = (player.frameIdx + 1) % 4; }
            stepTimer += dt; if (stepTimer > (player.sprinting ? 0.18 : 0.3)) { stepTimer = 0; playSound('step'); }
        } else { player.frameIdx = 0; player.frameTimer = 0; }

        const pcx = player.px + TILE / 2, pcy = player.py + TILE / 2;
        const here = tileAt(player.x, player.y);
        const wasHidden = player.hidden; player.hidden = here === HIDE && !moving;
        if (player.hidden && !wasHidden) { playSound('hide'); message = 'Hidden in the crates.'; }
        if (here === KEY) { player.hasKey = true; map[player.y][player.x] = FLOOR; message = 'Got the key! Doors are open.'; playSound('pickup'); playSound('door'); popup('Key!', player.px + 16, player.py - 4); spawnParticles(player.px + 16, player.py + 16, 14, '#ffd23a'); for (let y = 0; y < map.length; y++) for (let x = 0; x < map[0].length; x++) if (map[y][x] === DOOR) map[y][x] = FLOOR; }
        else if (here === TREASURE) { map[player.y][player.x] = FLOOR; treasureFound++; levelScore += TREASURE_POINTS; score += TREASURE_POINTS; message = `Gold! (${treasureFound}/${treasureTotal})`; playSound('treasure'); popup(`+${TREASURE_POINTS}`, player.px + 16, player.py - 4); spawnParticles(player.px + 16, player.py + 16, 16, '#ffe066'); }
        else if (here === EXIT) { completeLevel(); return; }
        else if (here === PLANS) { map[player.y][player.x] = FLOOR; playSound('plans'); message = 'The war plans! Now get out of the castle.'; markObjective('plans'); spawnParticles(player.px + 16, player.py + 16, 24, '#fff3a8', 80); }
        else if (here === GUN) { map[player.y][player.x] = FLOOR; player.gun = true; player.ammo += GUN_AMMO; playSound('pickup'); message = `A pistol with ${GUN_AMMO} rounds. F or Ctrl fires. Shots are loud.`; popup('Pistol!', player.px + 16, player.py - 4, '#dddddd'); }
        else if (here === AMMO) { map[player.y][player.x] = FLOOR; player.ammo += AMMO_BOX; playSound('pickup'); message = `+${AMMO_BOX} rounds.`; popup(`+${AMMO_BOX} ammo`, player.px + 16, player.py - 4, '#dddddd'); }
        else if (here === PRISONER) { map[player.y][player.x] = FLOOR; playSound('free'); message = 'You freed the prisoner. They slip away into the dark.'; markObjective('prisoner'); spawnParticles(player.px + 16, player.py + 16, 18, '#9fffa0', 60); }
        if (here === PORTAL) { if (!onPortal) { const p = portals.find((q) => q.x === player.x && q.y === player.y); if (p) { travel(p); return; } } } else onPortal = false;

        // ---- traps ----
        if (lastTile && (lastTile[0] !== player.x || lastTile[1] !== player.y)) {
            if (tileAt(lastTile[0], lastTile[1]) === CRUMBLE) { map[lastTile[1]][lastTile[0]] = PIT; playSound('crumble'); shake = 0.25; spawnParticles(lastTile[0] * TILE + 16, lastTile[1] * TILE + 16, 14, '#55555f', 50); message = 'The floor gave way behind you!'; }
            lastTile = [player.x, player.y];
        }
        if (here === SPIKES && spikeState(player.x, player.y) === 'up') { playSound('spikes'); die('Impaled on the spikes!'); return; }
        if (here === TRIPWIRE && player.sprinting && trippedAt < time - 2) { trippedAt = time; raiseAlarm('You tripped the alarm wire!'); }
        if (here === TRAPDOOR) { fallThroughTrapdoor(); return; }
        for (let y = 0; y < map.length; y++) for (let x = 0; x < map[0].length; x++) if (map[y][x] === GAS && gasActive(x, y) && Math.hypot(x * TILE + 16 - pcx, y * TILE + 16 - pcy) < GAS_RADIUS) { if (dizzy <= 0) { playSound('gas'); message = 'Gas! Your head spins... controls reversed.'; } dizzy = DIZZY_TIME; }
        for (const b of blades) {
            const [fx, fy] = b.from, [tx, ty] = b.to; const target = b.dir > 0 ? [tx * TILE, ty * TILE] : [fx * TILE, fy * TILE];
            const ddx = target[0] - b.px, ddy = target[1] - b.py, dist = Math.hypot(ddx, ddy);
            if (dist < 1) { b.dir *= -1; playSound('blade'); } else { b.px += (ddx / dist) * Math.min(b.spd, dist); b.py += (ddy / dist) * Math.min(b.spd, dist); }
            b.angle += dt * 14;
            if (Math.hypot(b.px + 16 - pcx, b.py + 16 - pcy) < 15) { playSound('spikes'); die('Sliced by a swinging blade!'); return; }
        }


        for (const g of guards) {
            const gx = Math.floor((g.px + TILE / 2) / TILE), gy = Math.floor((g.py + TILE / 2) / TILE);
            const vx = pcx - (g.px + TILE / 2), vy = pcy - (g.py + TILE / 2), d = Math.hypot(vx, vy);
            let canSee = false;
            if (d < VIEW_DIST && !player.hidden) {
                let ang = Math.atan2(vy, vx) - DIR_ANGLE[g.dir]; ang = Math.atan2(Math.sin(ang), Math.cos(ang));
                const inCone = g.state === 'alert' || d < TILE || Math.abs(ang) < VIEW_FOV / 2;
                canSee = inCone && !rayBlocked(gx, gy, player.x, player.y);
            }
            const heard = player.sprinting && d < SPRINT_NOISE;
            if (canSee || heard) {
                if (g.state !== 'alert') { playSound('alert'); message = heard && !canSee ? 'A guard heard you!' : 'Spotted!'; g.alertAt = time; }
                if (canSee && time - (g.alertAt ?? time) > ALARM_CHASE && alarmUntil <= time) raiseAlarm('A guard raised the alarm!');
                g.state = 'alert'; g.wait = 0; g.lastSeen = { x: player.x, y: player.y, time: now() };
                const tail = g.path[g.path.length - 1];
                if (!tail || tail[0] !== player.x || tail[1] !== player.y) { const path = astar(gx, gy, player.x, player.y); if (path && path.length > 1) { g.path = path; g.pathIdx = 1; } }
            }
            if (g.state === 'alert') {
                if (g.path && g.pathIdx < g.path.length) { if (moveGuardToward(g, g.path[g.pathIdx][0] * TILE, g.path[g.pathIdx][1] * TILE)) g.pathIdx++; }
                else if (g.lastSeen && now() - g.lastSeen.time > ALERT_MEMORY_MS) {
                    g.state = 'calm'; g.path = []; g.pathIdx = 0; g.alertAt = null;
                    let best = 0, bd = Infinity; g.patrol.forEach((p, i) => { const dd = Math.hypot(p[0] * TILE - g.px, p[1] * TILE - g.py); if (dd < bd) { bd = dd; best = i; } }); g.i = best;
                }
            } else if (g.wait > 0) {
                g.wait -= dt;
                // look around while waiting: face the next leg
                const nxt = g.patrol[(g.i) % g.patrol.length]; g.dir = dirFromVec(nxt[0] * TILE - g.px || 0.001, nxt[1] * TILE - g.py);
            } else {
                const target = g.patrol[g.i];
                if (moveGuardToward(g, target[0] * TILE, target[1] * TILE)) { g.i = (g.i + 1) % g.patrol.length; g.wait = WAYPOINT_WAIT; }
            }
            g.frameTimer += dt;
            const walking = g.state === 'alert' || g.wait <= 0;
            if (walking && g.frameTimer > (g.state === 'alert' ? 0.12 : 0.2)) { g.frameTimer = 0; g.frameIdx = (g.frameIdx + 1) % 2; }
            if (!walking) g.frameIdx = 0;
            if (Math.hypot(pcx - (g.px + TILE / 2), pcy - (g.py + TILE / 2)) < 14) { caught(); return; }
        }
    }

    // ---- draw ----------------------------------------------------------
    function blit(f, x, y) { ctx.drawImage(sprites.sheet, f.x * TILE, f.y * TILE, TILE, TILE, x, y, TILE, TILE); }
    const fallbackColour = { [WALL]: '#444', [DOOR]: '#3a3', [KEY]: '#ff8800', [EXIT]: '#06f', [TREASURE]: '#dba51a', [HIDE]: '#8a6230', [PORTAL]: '#335', [SPIKES]: '#888', [TRIPWIRE]: '#666', [TRAPDOOR]: '#5a4020', [GAS]: '#3c4a3c', [CRUMBLE]: '#2a2a30', [PIT]: '#000' };
    function torches() { const out = []; for (let y = 0; y < map.length; y++) for (let x = 0; x < map[0].length; x++) if (map[y][x] === WALL && tileAt(x, y + 1) !== WALL && hash(x * 3 + levelIndex, y * 5) < 0.16) out.push([x, y]); return out; }
    let torchCache = null, torchLevel = -1;

    function drawWorld() {
        for (let y = 0; y < map.length; y++) for (let x = 0; x < map[0].length; x++) {
            const t = map[y][x], px = x * TILE, py = y * TILE;
            if (!sprites.ready) { ctx.fillStyle = fallbackColour[t] ?? '#222'; ctx.fillRect(px, py, TILE, TILE); continue; }
            blit({ x: Math.floor(hash(x, y) * 4), y: 0 }, px, py); // floor under everything
            if (t === SPIKES) { const st = spikeState(x, y); blit({ x: sprites.frames.spikes[st === 'up' ? 2 : st === 'warn' ? 1 : 0], y: 0 }, px, py); }
            else if (t !== FLOOR) blit({ x: sprites.frames.tiles[t], y: 0 }, px, py);
            if (t === TREASURE || t === KEY) { const pulse = 0.25 + 0.2 * Math.sin(time * 5 + x); ctx.fillStyle = `rgba(255,220,80,${pulse * 0.25})`; ctx.fillRect(px + 4, py + 4, TILE - 8, TILE - 8); }
        }
        // wall drop shadows onto floor below
        ctx.fillStyle = 'rgba(0,0,0,0.35)';
        for (let y = 0; y < map.length - 1; y++) for (let x = 0; x < map[0].length; x++) if (map[y][x] === WALL && map[y + 1][x] !== WALL) ctx.fillRect(x * TILE, (y + 1) * TILE, TILE, 6);
        if (torchLevel !== levelIndex) { torchCache = torches(); torchLevel = levelIndex; }
        for (const [x, y] of torchCache) { const fl = Math.sin(time * 9 + x) * 1.5; ctx.fillStyle = '#5a3a1a'; ctx.fillRect(x * TILE + 14, y * TILE + 18, 4, 10); ctx.fillStyle = '#ffb53a'; ctx.beginPath(); ctx.ellipse(x * TILE + 16, y * TILE + 15 + fl * 0.3, 4 + fl * 0.5, 7, 0, 0, Math.PI * 2); ctx.fill(); ctx.fillStyle = '#fff3a8'; ctx.beginPath(); ctx.ellipse(x * TILE + 16, y * TILE + 17, 2, 3.5, 0, 0, Math.PI * 2); ctx.fill(); }
    }
    function drawCone(g) {
        const cx = g.px + TILE / 2, cy = g.py + TILE / 2, a = DIR_ANGLE[g.dir];
        const grad = ctx.createRadialGradient(cx, cy, 8, cx, cy, VIEW_DIST);
        if (g.state === 'alert') { grad.addColorStop(0, 'rgba(255,50,50,0.30)'); grad.addColorStop(1, 'rgba(255,50,50,0.02)'); }
        else { grad.addColorStop(0, 'rgba(255,230,120,0.22)'); grad.addColorStop(1, 'rgba(255,230,120,0.0)'); }
        ctx.fillStyle = grad; ctx.beginPath(); ctx.moveTo(cx, cy); ctx.arc(cx, cy, VIEW_DIST, a - VIEW_FOV / 2, a + VIEW_FOV / 2); ctx.closePath(); ctx.fill();
    }
    function drawLighting() {
        lctx.globalCompositeOperation = 'source-over';
        lctx.fillStyle = 'rgba(4,4,10,0.78)'; lctx.fillRect(0, 0, light.width, light.height);
        lctx.globalCompositeOperation = 'destination-out';
        const hole = (x, y, r, inner = 0.9) => { const gr = lctx.createRadialGradient(x, y, 0, x, y, r); gr.addColorStop(0, `rgba(0,0,0,${inner})`); gr.addColorStop(1, 'rgba(0,0,0,0)'); lctx.fillStyle = gr; lctx.beginPath(); lctx.arc(x, y, r, 0, Math.PI * 2); lctx.fill(); };
        hole(player.px + 16, player.py + 16, player.hidden ? 90 : 150, 0.95);
        for (const [x, y] of torchCache ?? []) hole(x * TILE + 16, y * TILE + 20, 80 + Math.sin(time * 9 + x) * 4, 0.7);
        for (let y = 0; y < map.length; y++) for (let x = 0; x < map[0].length; x++) { const t = map[y][x]; if (t === EXIT) hole(x * TILE + 16, y * TILE + 16, 70, 0.8); else if (t === TREASURE || t === KEY) hole(x * TILE + 16, y * TILE + 16, 34, 0.5); else if (t === PORTAL) hole(x * TILE + 16, y * TILE + 16, 50, 0.6); else if (t === GAS && gasActive(x, y)) hole(x * TILE + 16, y * TILE + 16, 50, 0.5); }
        for (const b of blades) hole(b.px + 16, b.py + 16, 30, 0.5);
        if (muzzle > 0) hole(player.px + 16, player.py + 16, 220, 0.9);
        for (const b of bullets) hole(b.x, b.y, 24, 0.6);
        for (const g of guards) { const cx = g.px + 16, cy = g.py + 16, a = DIR_ANGLE[g.dir]; lctx.fillStyle = 'rgba(0,0,0,0.55)'; lctx.beginPath(); lctx.moveTo(cx, cy); lctx.arc(cx, cy, VIEW_DIST * 0.9, a - VIEW_FOV / 2, a + VIEW_FOV / 2); lctx.closePath(); lctx.fill(); hole(cx, cy, 40, 0.6); }
        ctx.drawImage(light, 0, 0);
    }
    function overlay(title, lines, sub = null) {
        ctx.fillStyle = 'rgba(0,0,0,0.7)'; ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.textAlign = 'center'; ctx.fillStyle = '#fff';
        ctx.font = 'bold 34px Georgia, serif'; ctx.fillText(title, canvas.width / 2, canvas.height / 2 - 34);
        ctx.font = '15px system-ui, sans-serif'; ctx.fillStyle = '#ddd';
        lines.forEach((l, i) => ctx.fillText(l, canvas.width / 2, canvas.height / 2 + 2 + i * 22));
        if (sub) { ctx.fillStyle = '#ffe066'; ctx.fillText(sub, canvas.width / 2, canvas.height - 18); }
        ctx.textAlign = 'left';
    }

    // Castle overview: every room drawn as a mini map at its castle position,
    // with exits (stairs to the next room) and portal links between rooms.
    function drawCastle(c, W, H, big) {
        const cols = Math.max(...levels.map((l) => l.castle_x)) + 1, rows = Math.max(...levels.map((l) => l.castle_y)) + 1;
        const mw = levels[0].map[0].length, mh = levels[0].map.length;
        const pad = big ? 36 : 10, gapX = big ? 48 : 14, gapY = big ? 40 : 16;
        const s = Math.min((W - pad * 2 - gapX * (cols - 1)) / (cols * mw), (H - pad * 2 - gapY * (rows - 1)) / (rows * mh));
        const rw = mw * s, rh = mh * s;
        const totalW = cols * rw + (cols - 1) * gapX, totalH = rows * rh + (rows - 1) * gapY;
        const ox = (W - totalW) / 2, oy = (H - totalH) / 2;
        const pos = (l) => [ox + l.castle_x * (rw + gapX), oy + l.castle_y * (rh + gapY)];
        c.clearRect(0, 0, W, H); c.fillStyle = '#0b0b0e'; c.fillRect(0, 0, W, H);
        // links
        c.lineWidth = big ? 3 : 1.5;
        levels.forEach((l, i) => {
            const [x, y] = pos(l);
            if (i + 1 < levels.length) { const [x2, y2] = pos(levels[i + 1]); c.strokeStyle = completed.has(i) ? '#6c9be0' : '#3a4a66'; c.setLineDash([]); c.beginPath(); c.moveTo(x + rw / 2, y + rh / 2); c.lineTo(x2 + rw / 2, y2 + rh / 2); c.stroke(); }
            for (const p of l.portals ?? []) { const j = levelByNumber.get(p.to_level); if (j < i) continue; const [x2, y2] = pos(levels[j]); c.strokeStyle = '#a070ff'; c.setLineDash([4, 4]); c.beginPath(); c.moveTo(x + p.x * s, y + p.y * s); c.lineTo(x2 + (levels[j].portals.find((q) => q.id === p.id)?.x ?? 0) * s, y2 + (levels[j].portals.find((q) => q.id === p.id)?.y ?? 0) * s); c.stroke(); c.setLineDash([]); }
        });
        // rooms
        levels.forEach((l, i) => {
            const [x, y] = pos(l); const known = visited.has(i) || i === levelIndex || phase === 'title';
            const m = i === levelIndex ? map : (levelStates.get(i)?.map ?? l.map);
            for (let ty = 0; ty < mh; ty++) for (let tx = 0; tx < mw; tx++) {
                const t = m[ty][tx]; let col = '#1c1c22';
                if (!known) col = t === WALL ? '#202026' : '#141418';
                else if (t === WALL) col = '#5e5e68'; else if (t === DOOR) col = '#8a6230'; else if (t === KEY) col = '#ffd23a'; else if (t === EXIT) col = '#3a64a8'; else if (t === TREASURE) col = '#e8c33a'; else if (t === HIDE) col = '#6b4a24'; else if (t === PORTAL) col = '#a070ff'; else if (t === SPIKES || t === TRIPWIRE || t === GAS) col = '#8a3a3a'; else if (t === TRAPDOOR || t === PIT) col = '#0a0a0c'; else if (t === CRUMBLE) col = '#33333a'; else if (t === PLANS) col = '#fff3a8'; else if (t === PRISONER) col = '#9fffa0'; else if (t === GUN || t === AMMO) col = '#bbbbcc';
                c.fillStyle = col; c.fillRect(x + tx * s, y + ty * s, Math.ceil(s), Math.ceil(s));
            }
            if (i === levelIndex && phase !== 'title') { c.fillStyle = '#3f6bb5'; c.fillRect(x + player.x * s, y + player.y * s, Math.ceil(s), Math.ceil(s)); for (const g of guards) { c.fillStyle = g.state === 'alert' ? '#ff4040' : '#c33'; c.fillRect(x + (g.px / TILE) * s, y + (g.py / TILE) * s, Math.ceil(s), Math.ceil(s)); } for (const b of blades) { c.fillStyle = '#c8c8d4'; c.fillRect(x + (b.px / TILE) * s, y + (b.py / TILE) * s, Math.ceil(s), Math.ceil(s)); } }
            c.strokeStyle = i === levelIndex ? '#ffe066' : completed.has(i) ? '#6c9be0' : '#333'; c.lineWidth = i === levelIndex ? 2 : 1; c.strokeRect(x - 1, y - 1, rw + 2, rh + 2);
            c.fillStyle = completed.has(i) ? '#6c9be0' : known ? '#ddd' : '#555'; c.font = `${big ? 'bold 14px' : '10px'} system-ui, sans-serif`; c.textAlign = 'center';
            c.fillText(`${l.number}. ${known ? l.name : '???'}${completed.has(i) ? ' ✓' : ''}`, x + rw / 2, y - (big ? 8 : 3));
        });
        c.textAlign = 'left';
    }

    function draw() {
        ctx.save();
        if (shake > 0) ctx.translate((Math.random() - 0.5) * shake * 20, (Math.random() - 0.5) * shake * 20);
        ctx.clearRect(-20, -20, canvas.width + 40, canvas.height + 40);
        drawWorld();
        // gas clouds
        for (let y = 0; y < map.length; y++) for (let x = 0; x < map[0].length; x++) if (map[y][x] === GAS && gasActive(x, y)) { const cx = x * TILE + 16, cy = y * TILE + 16; const gr = ctx.createRadialGradient(cx, cy, 4, cx, cy, GAS_RADIUS + Math.sin(time * 6) * 4); gr.addColorStop(0, 'rgba(120,220,90,0.45)'); gr.addColorStop(1, 'rgba(120,220,90,0)'); ctx.fillStyle = gr; ctx.beginPath(); ctx.arc(cx, cy, GAS_RADIUS + 6, 0, Math.PI * 2); ctx.fill(); }
        // swinging blades
        for (const b of blades) {
            ctx.save(); ctx.translate(b.px + 16, b.py + 16); ctx.rotate(b.angle);
            ctx.fillStyle = '#c8c8d4'; ctx.beginPath(); ctx.moveTo(-14, 0); ctx.lineTo(0, -5); ctx.lineTo(14, 0); ctx.lineTo(0, 5); ctx.closePath(); ctx.fill();
            ctx.rotate(Math.PI / 2); ctx.fillStyle = '#9a9aa8'; ctx.beginPath(); ctx.moveTo(-14, 0); ctx.lineTo(0, -5); ctx.lineTo(14, 0); ctx.lineTo(0, 5); ctx.closePath(); ctx.fill();
            ctx.fillStyle = '#444'; ctx.beginPath(); ctx.arc(0, 0, 3, 0, Math.PI * 2); ctx.fill(); ctx.restore();
        }
        guards.forEach(drawCone);
        for (const b of bodies) { ctx.save(); ctx.translate(b.px + 16, b.py + 16); ctx.rotate(Math.PI / 2); ctx.globalAlpha = 0.85; ctx.fillStyle = 'rgba(120,10,10,0.5)'; ctx.beginPath(); ctx.ellipse(0, 4, 14, 8, 0, 0, Math.PI * 2); ctx.fill(); if (sprites.ready) blit(sprites.frames.guard[b.dir][0], -16, -16); ctx.restore(); }
        if (sprites.ready) { ctx.globalAlpha = player.hidden ? 0.45 : 1; blit(sprites.frames.player[player.dir][player.frameIdx], player.px, player.py); ctx.globalAlpha = 1; }
        else { ctx.fillStyle = player.alive ? '#ffdd00' : '#777'; ctx.fillRect(player.px + 6, player.py + 6, TILE - 12, TILE - 12); }
        if (muzzle > 0) { const a = DIR_ANGLE[player.dir]; ctx.fillStyle = '#fff3a8'; ctx.beginPath(); ctx.arc(player.px + 16 + Math.cos(a) * 16, player.py + 16 + Math.sin(a) * 16, 6, 0, Math.PI * 2); ctx.fill(); }
        for (const b of bullets) { ctx.strokeStyle = '#ffe066'; ctx.lineWidth = 2; ctx.beginPath(); ctx.moveTo(b.x - b.vx, b.y - b.vy); ctx.lineTo(b.x, b.y); ctx.stroke(); }
        guards.forEach((g) => {
            if (sprites.ready) blit(sprites.frames.guard[g.dir][g.frameIdx], g.px, g.py);
            else { ctx.fillStyle = g.state === 'alert' ? '#ff5555' : '#c33'; ctx.fillRect(g.px + 6, g.py + 6, TILE - 12, TILE - 12); }
            if (g.state === 'alert') { const bob = Math.abs(Math.sin(time * 8)) * 4; ctx.fillStyle = '#ff3b3b'; ctx.font = 'bold 20px Georgia, serif'; ctx.fillText('!', g.px + 12, g.py - 4 - bob); }
        });
        particles.forEach((p) => { ctx.globalAlpha = Math.max(0, p.life); ctx.fillStyle = p.col; ctx.fillRect(p.x - 1.5, p.y - 1.5, 3, 3); }); ctx.globalAlpha = 1;
        drawLighting();
        popups.forEach((p) => { ctx.globalAlpha = Math.min(1, p.life); ctx.fillStyle = p.col; ctx.font = 'bold 14px system-ui, sans-serif'; ctx.textAlign = 'center'; ctx.fillText(p.text, p.x, p.y); }); ctx.globalAlpha = 1; ctx.textAlign = 'left';
        if (player.sprinting) { ctx.strokeStyle = 'rgba(255,255,255,0.15)'; ctx.beginPath(); ctx.arc(player.px + 16, player.py + 16, SPRINT_NOISE * (0.6 + 0.4 * ((time * 2) % 1)), 0, Math.PI * 2); ctx.stroke(); }
        if (dizzy > 0) { ctx.fillStyle = `rgba(120,220,90,${0.08 + 0.08 * Math.sin(time * 7)})`; ctx.fillRect(-20, -20, canvas.width + 40, canvas.height + 40); }
        if (flash > 0) { ctx.fillStyle = `rgba(255,0,0,${Math.min(0.5, flash)})`; ctx.fillRect(-20, -20, canvas.width + 40, canvas.height + 40); }
        ctx.restore();

        const lvl = levels[levelIndex];
        if (phase === 'title') overlay('Mini Castle Wolfenstein', ['Steal the war plans from the Keep and escape every room.', 'Stay out of the guards\' sight. Crates hide you, sprinting is loud.', 'Find a pistol if you must, but gunfire brings guards from other rooms.'], 'Press Space or tap to start');
        else if (phase === 'paused') overlay('Paused', ['Press P or Space to resume']);
        else if (phase === 'map') { ctx.fillStyle = 'rgba(0,0,0,0.85)'; ctx.fillRect(0, 0, canvas.width, canvas.height); drawCastle(ctx, canvas.width, canvas.height, true); ctx.fillStyle = '#ffe066'; ctx.font = '13px system-ui, sans-serif'; ctx.textAlign = 'center'; ctx.fillText('The castle — press M to return', canvas.width / 2, canvas.height - 8); ctx.textAlign = 'left'; }
        else if (phase === 'levelclear') overlay(`${lvl.name} cleared`, [`Score ${score}`, `${completed.size} of ${levels.length} rooms escaped`, ...(completed.size === levels.length && missingRequired().length ? [`Go back for the ${missingRequired()[0].obj.label.toLowerCase()}`] : [])], 'Press Space to continue');
        else if (phase === 'dead') overlay('Caught!', [`${lives} ${lives === 1 ? 'life' : 'lives'} left`], 'Press Space to retry');
        else if (phase === 'gameover') overlay('Game over', [`Final score ${score}`], 'Press Space to try again');
        else if (phase === 'won') overlay('You escaped with the plans!', [`Final score ${score}`, `${kills} guard${kills === 1 ? '' : 's'} shot, ${[...objectivesDone.values()].reduce((n, st) => n + st.size, 0)} objectives completed`], 'Press Space to play again');

        drawCastle(cctx, castleCanvas.width, castleCanvas.height, false);
        timerEl.textContent = phase === 'title' ? '0.0s' : `${(elapsedMs() / 1000).toFixed(1)}s`;
        scoreEl.textContent = score;
        livesEl.textContent = '♥'.repeat(Math.max(0, lives)) + '♡'.repeat(Math.max(0, MAX_LIVES - lives));
        const objEl = document.getElementById('objectives');
        if (objEl) {
            const rows = (lvl.objectives ?? []).map((o) => { const done = isDone(levelIndex, o.id); const failed = o.id === 'no_alarm' && levelAlarmed; return `<li class="${done ? 'done' : failed ? 'failed' : ''}">${done ? '✓' : failed ? '✗' : '○'} ${o.label}${o.required ? ' <em>(required)</em>' : ''}</li>`; });
            const miss = missingRequired();
            const main = miss.length ? `○ ${miss[0].obj.label} in ${levels[miss[0].level].name}, then escape` : completed.size === levels.length ? '✓ Castle escaped' : `✓ Plans secured — escape every room (${completed.size}/${levels.length})`;
            objEl.innerHTML = `<div class="main">${main}</div><ul>${rows.join('')}</ul>`;
        }
        const weaponEl = document.getElementById('weapon');
        if (weaponEl) weaponEl.textContent = player.gun ? `Pistol: ${player.ammo} rounds` : 'Unarmed';
        info.textContent = `${lvl.name}${player.hidden ? '  (hidden)' : dizzy > 0 ? '  (dizzy!)' : player.sprinting ? '  (sprinting!)' : ''}\nKey: ${player.hasKey ? 'yes' : 'no'}   Gold: ${treasureFound}/${treasureTotal}   Rooms: ${completed.size}/${levels.length}\n${message}`;
    }

    // Debug hook for automated checks: only when the page is opened with ?debug=1
    if (new URLSearchParams(location.search).has('debug')) {
        window.__woolfie = {
            state: () => ({ phase, levelIndex, guards: guards.length, bodies: bodies.length, alarm: alarmUntil > time, reinforcements: reinforcements.length, kills, ammo: player.ammo, gun: player.gun, score, objectives: [...objectivesDone.entries()].map(([k, v]) => [k, [...v]]), roomGuards: [...levelStates.entries()].map(([k, v]) => [k, v.guards.length]) }),
            givePistol: () => { player.gun = true; player.ammo = GUN_AMMO; }, fire, raiseAlarm, markObjective,
            teleport: (x, y) => { player.x = x; player.y = y; player.px = x * TILE; player.py = y * TILE; lastTile = [x, y]; },
            face: (d) => { player.dir = d; },
        };
    }

    let last = now();
    function loop(t) { const dt = Math.min(0.05, (t - last) / 1000); last = t; update(dt); draw(); requestAnimationFrame(loop); }
    loadLevel(0); phase = 'title';
    requestAnimationFrame(loop);
}
