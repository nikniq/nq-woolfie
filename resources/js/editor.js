// Room editor: paints tile grids, guards, blades and portals, and saves them
// through the editor endpoints. Validation errors come back from the server.
const TILES = [
    [0, 'Floor', '#24242a'], [1, 'Wall', '#5e5e68'], [2, 'Door', '#8a6230'], [3, 'Key', '#ffd23a'], [4, 'Exit', '#3a64a8'],
    [5, 'Gold', '#e8c33a'], [6, 'Crates (hide)', '#6b4a24'], [7, 'Portal', '#a070ff'], [8, 'Spikes', '#b8b8c4'], [9, 'Tripwire', '#8a8a96'],
    [10, 'Trapdoor', '#3a2a16'], [11, 'Gas vent', '#3c4a3c'], [12, 'Crumbling floor', '#33333a'], [14, 'War plans', '#fff3a8'],
    [15, 'Pistol', '#bbbbcc'], [16, 'Ammo', '#4a5a3a'], [17, 'Prisoner', '#9fffa0'], [18, 'Supply chest', '#8a6a30'], [19, 'Cracked wall', '#4a4a3a'],
];
const LABEL = { 0: '', 1: '', 2: 'D', 3: 'K', 4: 'E', 5: '$', 6: 'H', 7: '', 8: 'S', 9: 'T', 10: 'X', 11: 'G', 12: 'C', 14: 'W', 15: 'U', 16: 'A', 17: 'R', 18: 'B', 19: 'Z' };
const COLOR = Object.fromEntries(TILES.map(([id, , c]) => [id, c]));
const CELL = 28;

export function startEditor() {
    const root = document.getElementById('editor');
    const level = JSON.parse(root.dataset.level);
    const others = JSON.parse(root.dataset.others);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const canvas = document.getElementById('edCanvas');
    const ctx = canvas.getContext('2d');
    const $ = (id) => document.getElementById(id);

    let map = level.map.map((r) => r.slice());
    let start = level.player_start.slice();
    let guards = (level.guards ?? []).map((g) => ({ patrol: g.patrol.map((p) => p.slice()), spd: g.spd, type: g.type ?? 'guard' }));
    let blades = (level.traps ?? []).filter((t) => t.type === 'blade').map((t) => ({ type: 'blade', from: t.from.slice(), to: t.to.slice(), spd: t.spd }));
    let portals = (level.portals ?? []).map((p) => ({ id: p.id, x: p.x, y: p.y }));
    let tool = { kind: 'tile', tile: 1 };
    let selectedGuard = -1, selectedBlade = -1, bladeEnd = 0;
    let painting = false, paintTile = 1;

    const rows = () => map.length, cols = () => map[0].length;
    const usedLetters = () => { const u = {}; for (const o of others) for (const p of o.portals ?? []) u[p.id] = (u[p.id] ?? 0) + 1; return u; };

    // ---- palette ---------------------------------------------------------
    const tilesEl = $('edTiles');
    for (const [id, name, col] of TILES) {
        const b = document.createElement('button'); b.type = 'button'; b.className = 'pal'; b.dataset.tile = id;
        b.innerHTML = `<span class="sw" style="background:${col}"></span>${name}`;
        b.onclick = () => { tool = { kind: 'tile', tile: id }; selectedGuard = -1; selectedBlade = -1; refresh(); };
        tilesEl.appendChild(b);
    }
    const toolsEl = $('edTools');
    for (const [kind, name] of [['start', 'Player start'], ['erase', 'Erase (floor)']]) {
        const b = document.createElement('button'); b.type = 'button'; b.className = 'pal'; b.dataset.kind = kind; b.textContent = name;
        b.onclick = () => { tool = { kind }; selectedGuard = -1; selectedBlade = -1; refresh(); };
        toolsEl.appendChild(b);
    }

    function refreshLists() {
        const gEl = $('edGuards'); gEl.innerHTML = '';
        guards.forEach((g, i) => {
            const row = document.createElement('div'); row.className = 'row' + (selectedGuard === i ? ' sel' : '');
            row.innerHTML = `<button type="button" class="pick">#${i + 1}</button>
                <select><option value="guard">guard</option><option value="officer">officer</option><option value="dog">dog</option></select>
                <input type="number" step="0.1" min="0.3" max="4" value="${g.spd}" title="speed">
                <span class="pts">${g.patrol.map((p) => `(${p[0]},${p[1]})`).join(' ') || 'no points'}</span>
                <button type="button" class="undo" title="remove last point">↶</button>
                <button type="button" class="del" title="delete guard">✕</button>`;
            row.querySelector('select').value = g.type;
            row.querySelector('.pick').onclick = () => { selectedGuard = i; selectedBlade = -1; tool = { kind: 'guard' }; refresh(); };
            row.querySelector('select').onchange = (e) => { g.type = e.target.value; };
            row.querySelector('input').onchange = (e) => { g.spd = parseFloat(e.target.value) || 1; };
            row.querySelector('.undo').onclick = () => { g.patrol.pop(); refresh(); };
            row.querySelector('.del').onclick = () => { guards.splice(i, 1); selectedGuard = -1; refresh(); };
            gEl.appendChild(row);
        });
        const bEl = $('edBlades'); bEl.innerHTML = '';
        blades.forEach((b, i) => {
            const row = document.createElement('div'); row.className = 'row' + (selectedBlade === i ? ' sel' : '');
            row.innerHTML = `<button type="button" class="pick">#${i + 1}</button>
                <input type="number" step="0.1" min="0.3" max="6" value="${b.spd}" title="speed">
                <span class="pts">(${b.from}) → (${b.to})</span>
                <button type="button" class="del" title="delete blade">✕</button>`;
            row.querySelector('.pick').onclick = () => { selectedBlade = i; selectedGuard = -1; bladeEnd = 0; tool = { kind: 'blade' }; refresh(); };
            row.querySelector('input').onchange = (e) => { b.spd = parseFloat(e.target.value) || 2; };
            row.querySelector('.del').onclick = () => { blades.splice(i, 1); selectedBlade = -1; refresh(); };
            bEl.appendChild(row);
        });
        document.querySelectorAll('#edTiles .pal').forEach((b) => b.classList.toggle('sel', tool.kind === 'tile' && +b.dataset.tile === tool.tile));
        document.querySelectorAll('#edTools .pal').forEach((b) => b.classList.toggle('sel', tool.kind === b.dataset.kind));
        $('edHint').textContent = tool.kind === 'guard' ? `Guard #${selectedGuard + 1}: click tiles to append patrol points. Legs must be straight and walkable.`
            : tool.kind === 'blade' ? `Blade #${selectedBlade + 1}: click the ${bladeEnd === 0 ? 'start' : 'end'} tile.`
            : tool.kind === 'start' ? 'Click a floor tile to place the player start.'
            : 'Left-click or drag to paint the selected tile. Right-click paints floor. Portal tiles ask for a letter shared with the other room.';
    }
    $('edAddGuard').onclick = () => { guards.push({ patrol: [], spd: 1.2, type: 'guard' }); selectedGuard = guards.length - 1; selectedBlade = -1; tool = { kind: 'guard' }; refresh(); };
    $('edAddBlade').onclick = () => { blades.push({ type: 'blade', from: [1, 1], to: [1, 1], spd: 2 }); selectedBlade = blades.length - 1; selectedGuard = -1; bladeEnd = 0; tool = { kind: 'blade' }; refresh(); };

    // ---- canvas ----------------------------------------------------------
    function draw() {
        canvas.width = cols() * CELL; canvas.height = rows() * CELL;
        for (let y = 0; y < rows(); y++) for (let x = 0; x < cols(); x++) {
            const t = map[y][x];
            ctx.fillStyle = COLOR[t] ?? '#f0f'; ctx.fillRect(x * CELL, y * CELL, CELL, CELL);
            ctx.strokeStyle = 'rgba(0,0,0,0.35)'; ctx.strokeRect(x * CELL + 0.5, y * CELL + 0.5, CELL - 1, CELL - 1);
            const label = t === 7 ? (portals.find((p) => p.x === x && p.y === y)?.id ?? '?') : LABEL[t];
            if (label) { ctx.fillStyle = t === 1 ? '#222' : '#111'; ctx.font = 'bold 14px system-ui, sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText(label, x * CELL + CELL / 2, y * CELL + CELL / 2 + 1); }
        }
        // player start
        ctx.fillStyle = '#3f6bb5'; ctx.beginPath(); ctx.arc(start[0] * CELL + CELL / 2, start[1] * CELL + CELL / 2, 8, 0, Math.PI * 2); ctx.fill(); ctx.fillStyle = '#fff'; ctx.font = 'bold 11px system-ui'; ctx.fillText('P', start[0] * CELL + CELL / 2, start[1] * CELL + CELL / 2 + 1);
        // guards
        guards.forEach((g, i) => {
            const col = i === selectedGuard ? '#ff4040' : g.type === 'dog' ? '#c08040' : g.type === 'officer' ? '#d0d0ff' : '#ff8080';
            ctx.strokeStyle = col; ctx.lineWidth = i === selectedGuard ? 3 : 1.5; ctx.beginPath();
            g.patrol.forEach((p, k) => { const cx = p[0] * CELL + CELL / 2, cy = p[1] * CELL + CELL / 2; k === 0 ? ctx.moveTo(cx, cy) : ctx.lineTo(cx, cy); });
            if (g.patrol.length > 1) { ctx.lineTo(g.patrol[0][0] * CELL + CELL / 2, g.patrol[0][1] * CELL + CELL / 2); }
            ctx.stroke();
            g.patrol.forEach((p, k) => { ctx.fillStyle = col; ctx.beginPath(); ctx.arc(p[0] * CELL + CELL / 2, p[1] * CELL + CELL / 2, k === 0 ? 6 : 4, 0, Math.PI * 2); ctx.fill(); if (k === 0) { ctx.fillStyle = '#000'; ctx.font = 'bold 9px system-ui'; ctx.fillText(String(i + 1), p[0] * CELL + CELL / 2, p[1] * CELL + CELL / 2 + 1); } });
        });
        blades.forEach((b, i) => { ctx.strokeStyle = i === selectedBlade ? '#fff' : '#c8c8d4'; ctx.lineWidth = 2; ctx.setLineDash([4, 3]); ctx.beginPath(); ctx.moveTo(b.from[0] * CELL + CELL / 2, b.from[1] * CELL + CELL / 2); ctx.lineTo(b.to[0] * CELL + CELL / 2, b.to[1] * CELL + CELL / 2); ctx.stroke(); ctx.setLineDash([]); });
        ctx.lineWidth = 1;
    }
    function refresh() { refreshLists(); draw(); }

    const cellAt = (e) => { const r = canvas.getBoundingClientRect(); const x = Math.floor((e.clientX - r.left) / r.width * cols()), y = Math.floor((e.clientY - r.top) / r.height * rows()); return x >= 0 && y >= 0 && x < cols() && y < rows() ? [x, y] : null; };
    function paint(x, y, t) {
        if (map[y][x] === 7 && t !== 7) portals = portals.filter((p) => !(p.x === x && p.y === y));
        if (t === 7) {
            const used = usedLetters(); const mine = portals.filter((p) => !(p.x === x && p.y === y)).map((p) => p.id);
            const free = 'abcdefghijklmnopqrstuvwxyz'.split('').filter((l) => (used[l] ?? 0) === 1 && !mine.includes(l));
            const id = prompt(`Portal letter (same letter in the other room links them).\nLetters waiting for a partner: ${free.join(' ') || 'none'}`, free[0] ?? 'a');
            if (!id || !/^[a-z]$/.test(id)) return;
            portals = portals.filter((p) => !(p.x === x && p.y === y)); portals.push({ id, x, y });
        }
        map[y][x] = t;
    }
    function click(e, right) {
        const c = cellAt(e); if (!c) return; const [x, y] = c;
        if (right) { paint(x, y, 0); return; }
        if (tool.kind === 'tile') paint(x, y, tool.tile);
        else if (tool.kind === 'erase') paint(x, y, 0);
        else if (tool.kind === 'start') { if (map[y][x] !== 1 && map[y][x] !== 19) start = [x, y]; }
        else if (tool.kind === 'guard' && selectedGuard >= 0) guards[selectedGuard].patrol.push([x, y]);
        else if (tool.kind === 'blade' && selectedBlade >= 0) { if (bladeEnd === 0) { blades[selectedBlade].from = [x, y]; bladeEnd = 1; } else { blades[selectedBlade].to = [x, y]; bladeEnd = 0; } }
        refresh();
    }
    canvas.addEventListener('contextmenu', (e) => e.preventDefault());
    canvas.addEventListener('pointerdown', (e) => { const right = e.button === 2; if (tool.kind === 'tile' || tool.kind === 'erase' || right) { painting = true; paintTile = right ? 0 : tool.kind === 'erase' ? 0 : tool.tile; if (paintTile === 7) { painting = false; click(e, right); return; } const c = cellAt(e); if (c) { paint(c[0], c[1], paintTile); refresh(); } } else click(e, right); });
    canvas.addEventListener('pointermove', (e) => { if (!painting) return; const c = cellAt(e); if (c && map[c[1]][c[0]] !== paintTile) { paint(c[0], c[1], paintTile); refresh(); } });
    window.addEventListener('pointerup', () => { painting = false; });

    $('edResize').onclick = () => {
        const w = Math.max(5, Math.min(40, +$('edWidth').value)), h = Math.max(5, Math.min(30, +$('edHeight').value));
        const next = []; for (let y = 0; y < h; y++) { const row = []; for (let x = 0; x < w; x++) row.push(map[y]?.[x] ?? ((x === 0 || y === 0 || x === w - 1 || y === h - 1) ? 1 : 0)); next.push(row); }
        map = next; portals = portals.filter((p) => p.x < w && p.y < h); guards.forEach((g) => { g.patrol = g.patrol.filter((p) => p[0] < w && p[1] < h); });
        if (start[0] >= w || start[1] >= h) start = [1, 1];
        refresh();
    };

    // ---- save / play / export --------------------------------------------
    const payload = () => ({ number: +$('edNumber').value, name: $('edName').value, map, player_start: start, guards, traps: blades, portals, castle_x: +$('edCastleX').value, castle_y: +$('edCastleY').value });
    const showErrors = (list) => { const el = $('edErrors'); el.hidden = !list.length; el.innerHTML = list.map((e) => `<div>✗ ${e}</div>`).join(''); };
    async function save() {
        $('edStatus').textContent = 'Saving…'; showErrors([]);
        const res = await fetch(root.dataset.saveUrl, { method: root.dataset.method, headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(payload()) });
        const body = await res.json().catch(() => ({}));
        if (!res.ok) { const errs = body.errors ? (Array.isArray(body.errors) ? body.errors : Object.values(body.errors).flat()) : [body.message ?? `HTTP ${res.status}`]; showErrors(errs); $('edStatus').textContent = 'Not saved.'; return null; }
        $('edStatus').textContent = `Saved room ${body.number}.`;
        if (root.dataset.method === 'POST') { location.href = body.edit_url; return null; }
        return body;
    }
    $('edSave').onclick = save;
    $('edPlay').onclick = async () => { const b = await save(); if (b) location.href = `${root.dataset.playUrl}?level=${b.number}`; };
    $('edExport').onclick = async () => { const text = JSON.stringify(payload(), null, 1); try { await navigator.clipboard.writeText(text); $('edStatus').textContent = 'Room JSON copied to the clipboard.'; } catch { prompt('Room JSON', text); } };
    $('edImport').onclick = () => { const text = prompt('Paste room JSON'); if (!text) return; try { const d = JSON.parse(text); map = d.map; start = d.player_start; guards = d.guards ?? []; blades = (d.traps ?? []).filter((t) => t.type === 'blade'); portals = d.portals ?? []; if (d.name) $('edName').value = d.name; $('edWidth').value = map[0].length; $('edHeight').value = map.length; refresh(); } catch (e) { showErrors([`Invalid JSON: ${e.message}`]); } };
    $('edDelete')?.addEventListener('click', async () => { if (!confirm('Delete this room? Seeded rooms come back on the next seed; custom rooms are gone for good.')) return; const res = await fetch(root.dataset.deleteUrl, { method: 'DELETE', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } }); if (res.ok) location.href = root.dataset.indexUrl; });

    refresh();
}
