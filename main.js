// Mini Castle Wolfenstein — sprites, sound, and multiple levels
const canvas = document.getElementById('game');
const ctx = canvas.getContext('2d');
const info = document.getElementById('info');
const restartBtn = document.getElementById('restart');
const levelSpan = document.getElementById('level');
const soundCheckbox = document.getElementById('sound');

const TILE = 32;

// Levels: array of {map, playerStart, guards}
const levels = [
  {
    map: [
      [1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1],
      [1,0,0,0,0,0,0,0,0,1,0,0,0,0,0,0,0,3,0,1],
      [1,0,1,1,0,1,1,1,0,1,0,1,1,1,0,1,1,1,0,1],
      [1,0,1,0,0,0,0,1,0,0,0,1,0,0,0,1,0,0,0,1],
      [1,0,1,0,1,1,0,1,1,1,0,1,0,1,0,1,0,1,0,1],
      [1,0,0,0,0,1,0,0,0,1,0,0,0,1,0,0,0,1,0,1],
      [1,1,1,1,0,1,1,1,0,1,1,1,0,1,1,1,0,1,0,1],
      [1,0,0,1,0,0,0,1,0,0,0,1,0,0,0,1,0,0,0,1],
      [1,0,0,0,0,1,0,0,0,1,0,0,0,1,0,0,0,0,4,1],
      [1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1]
    ],
    playerStart: [1,1],
    guards:[ {patrol:[[9,1],[9,3],[9,6],[9,1]],spd:1.4}, {patrol:[[3,7],[6,7],[6,3],[3,7]],spd:1.0} ]
  },
  {
    map: [
      [1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1],
      [1,0,0,0,0,0,0,0,0,1,0,0,0,0,0,3,0,0,0,1],
      [1,0,1,1,0,1,1,1,0,1,0,1,1,1,0,1,1,1,0,1],
      [1,0,1,0,0,0,0,1,0,0,0,1,0,0,0,1,0,0,0,1],
      [1,0,1,0,1,1,0,1,1,1,0,1,0,1,0,1,0,1,0,1],
      [1,0,0,0,0,1,0,0,0,1,0,0,0,1,0,0,0,1,0,1],
      [1,1,1,1,0,1,1,1,0,1,1,1,0,1,1,1,0,1,0,1],
      [1,0,0,1,0,0,0,1,0,0,0,1,0,0,0,1,0,0,0,1],
      [1,0,0,0,0,1,0,0,0,1,0,0,0,1,0,0,0,0,4,1],
      [1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1]
    ],
    playerStart: [1,1],
    guards:[ {patrol:[[5,1],[9,1],[9,4],[5,4]],spd:1.6}, {patrol:[[2,7],[2,3],[7,3],[7,7]],spd:1.0} ]
  },
  {
    map: [
      [1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1],
      [1,0,0,0,0,0,0,0,0,1,0,0,0,0,0,0,0,3,0,1],
      [1,0,1,1,0,1,1,1,0,1,0,1,1,1,0,1,1,1,0,1],
      [1,0,1,0,0,0,0,1,0,0,0,1,0,0,0,1,0,0,0,1],
      [1,0,1,0,1,1,0,1,1,1,0,1,0,1,0,1,0,1,0,1],
      [1,0,0,0,0,1,0,0,0,1,0,0,0,1,0,0,0,1,0,1],
      [1,1,1,1,0,1,1,1,0,1,1,1,0,1,1,1,0,1,0,1],
      [1,0,0,1,0,0,0,1,0,0,0,1,0,0,0,1,0,0,0,1],
      [1,0,0,0,0,1,0,0,0,1,0,0,0,1,0,0,0,0,4,1],
      [1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1]
    ],
    playerStart: [1,1],
    guards:[ {patrol:[[9,1],[9,6]],spd:1.8}, {patrol:[[3,7],[6,7]],spd:1.2}, {patrol:[[12,4],[15,4]],spd:1.0} ]
  }
];

let levelIndex = 0;
let map = [];

const player = {x:1,y:1,px:1*TILE,py:1*TILE,speed:4,hasKey:false,alive:true};

let guards = [];

let keysPressed = {};
let message = '';

// create small sprite images as data URLs
// generate a compact sprite sheet and frame metadata
function createSpriteSheet(){
  const cols = 8, rows = 3;
  const sw = TILE * cols, sh = TILE * rows;
  const c = document.createElement('canvas'); c.width = sw; c.height = sh;
  const g = c.getContext('2d');
  // row 0: tiles
  // floor
  g.fillStyle = '#111'; g.fillRect(0,0,TILE,TILE); g.strokeStyle='#111'; g.strokeRect(0,0,TILE,TILE);
  // wall
  g.fillStyle = '#666'; g.fillRect(1*TILE,0,TILE,TILE); g.strokeRect(1*TILE,0,TILE,TILE);
  // door
  g.fillStyle = '#3a3'; g.fillRect(2*TILE,0,TILE,TILE); g.fillStyle='#6b4'; g.fillRect(2*TILE+8,8,TILE-16,TILE-16); g.strokeRect(2*TILE,0,TILE,TILE);
  // key
  g.fillStyle = '#222'; g.fillRect(3*TILE,0,TILE,TILE); g.fillStyle='#ff8800'; g.fillRect(3*TILE+8,8,TILE-16,TILE-16); g.strokeRect(3*TILE,0,TILE,TILE);
  // exit
  g.fillStyle = '#024'; g.fillRect(4*TILE,0,TILE,TILE); g.fillStyle='#06f'; g.fillRect(4*TILE+6,6,TILE-12,TILE-12); g.strokeRect(4*TILE,0,TILE,TILE);

  // row 1: player frames (4)
  for(let i=0;i<4;i++){
    const x = i*TILE, y = TILE;
    g.fillStyle='#222'; g.fillRect(x,y,TILE,TILE);
    g.fillStyle='#ffdd00';
    const offset = (i%2===0)?6:8;
    g.fillRect(x+offset,y+6,TILE-12,TILE-12);
    g.strokeStyle='#111'; g.strokeRect(x,y,TILE,TILE);
  }

  // row 2: guard frames (2)
  for(let i=0;i<2;i++){
    const x = i*TILE, y = TILE*2;
    g.fillStyle='#222'; g.fillRect(x,y,TILE,TILE);
    g.fillStyle='#c33'; const off=(i%2===0)?6:8; g.fillRect(x+off,y+6,TILE-12,TILE-12);
    g.strokeStyle='#111'; g.strokeRect(x,y,TILE,TILE);
  }

  const img = new Image(); img.src = c.toDataURL();
  const frames = {
    floor:{x:0,y:0}, wall:{x:1,y:0}, door:{x:2,y:0}, key:{x:3,y:0}, exit:{x:4,y:0},
    playerFrames:[{x:0,y:1},{x:1,y:1},{x:2,y:1},{x:3,y:1}],
    guardFrames:[{x:0,y:2},{x:1,y:2}]
  };
  return {img,frames};
}

const sheet = createSpriteSheet();
const sprites = {sheet: sheet.img, frames: sheet.frames, ready: false};
sprites.sheet.onload = () => { sprites.ready = true; };
// animation state for player
player.frameIdx = 0; player.frameTimer = 0;

// Audio (simple beeps)
const AudioCtx = window.AudioContext || window.webkitAudioContext;
let audioCtx = null;
function ensureAudio(){ if(!audioCtx && AudioCtx) audioCtx = new AudioCtx(); }
function playBeep(freq=440,duration=0.1,decay=0.05){ if(!soundCheckbox.checked) return; ensureAudio(); if(!audioCtx) return; const o = audioCtx.createOscillator(); const g = audioCtx.createGain(); o.type='sine'; o.frequency.value=freq; g.gain.value=0.0001; o.connect(g); g.connect(audioCtx.destination); o.start(); g.gain.exponentialRampToValueAtTime(0.2, audioCtx.currentTime + 0.01); g.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + duration + decay); o.stop(audioCtx.currentTime + duration + decay + 0.02); }

function playSound(name){ if(!soundCheckbox.checked) return; if(name==='pickup') playBeep(880,0.08); else if(name==='alert') playBeep(220,0.18); else if(name==='win') playBeep(1200,0.25); else if(name==='caught') playBeep(160,0.18); }

function tileAt(x,y){ if(y<0||y>=map.length||x<0||x>=map[0].length) return 1; return map[y][x]; }
function canWalkTile(x,y,ignoreDoors=false){ const t = tileAt(x,y); if(t===1) return false; if(t===2 && !ignoreDoors) return false; return true; }

function loadLevel(i){
  levelIndex = i;
  const lvl = levels[i];
  map = JSON.parse(JSON.stringify(lvl.map));
  player.x = lvl.playerStart[0]; player.y = lvl.playerStart[1]; player.px = player.x*TILE; player.py = player.y*TILE;
  player.hasKey = false; player.alive = true; message = '';
  guards = lvl.guards.map(g=>({
    patrol: g.patrol.slice(), i:0, spd: g.spd, state: 'calm', path: [], pathIdx: 0, lastSeen: null,
    px: g.patrol[0][0]*TILE, py: g.patrol[0][1]*TILE,
    frameIdx: 0, frameTimer: 0
  }));
  levelSpan.textContent = (levelIndex+1);
}

function nextLevel(){ if(levelIndex+1 < levels.length){ loadLevel(levelIndex+1); message='Next level!'; playSound('pickup'); } else { message='All levels complete!'; playSound('win'); player.alive=false; } }

function restart(){ loadLevel(levelIndex); }

restartBtn.onclick = restart;
window.addEventListener('keydown',e=>{keysPressed[e.key.toLowerCase()]=true; if(e.key===' '){ ensureAudio(); } });
window.addEventListener('keyup',e=>{keysPressed[e.key.toLowerCase()]=false});

// A* (same as before)
function astar(sx,sy,ex,ey){
  const cols = map[0].length, rows = map.length;
  function h(x,y){return Math.abs(x-ex)+Math.abs(y-ey)}
  const open = new Map(); const closed = new Set(); const key = (x,y)=>`${x},${y}`;
  open.set(key(sx,sy), {x:sx,y:sy,g:0,f:h(sx,sy),came:null});
  while(open.size){ let best=null,bk=null; for(const [k,v] of open){ if(!best||v.f<best.f){best=v;bk=k} } open.delete(bk); const {x,y,g} = best; if(x===ex && y===ey){ const path=[]; let cur=best; while(cur){ path.push([cur.x,cur.y]); cur=cur.came } path.reverse(); return path; } closed.add(key(x,y)); const nbrs=[[1,0],[-1,0],[0,1],[0,-1]]; for(const [dx,dy] of nbrs){ const nx=x+dx, ny=y+dy; if(nx<0||ny<0||nx>=cols||ny>=rows) continue; if(!canWalkTile(nx,ny)) continue; if(closed.has(key(nx,ny))) continue; const ng=g+1; const existing=open.get(key(nx,ny)); const nf=ng+h(nx,ny); if(!existing||ng<existing.g) open.set(key(nx,ny),{x:nx,y:ny,g:ng,f:nf,came:best}); } }
  return null;
}

function update(dt){
  if(!player.alive) return;
  let dx=0, dy=0;
  if(keysPressed['w']||keysPressed['arrowup']) dy=-1;
  if(keysPressed['s']||keysPressed['arrowdown']) dy=1;
  if(keysPressed['a']||keysPressed['arrowleft']) dx=-1;
  if(keysPressed['d']||keysPressed['arrowright']) dx=1;

  let moving = false;
  if(dx||dy){
    moving = true;
    const nx = player.px + dx*player.speed;
    const ny = player.py + dy*player.speed;
    const tx = Math.floor((nx+TILE/2)/TILE);
    const ty = Math.floor((ny+TILE/2)/TILE);
    if(canWalkTile(tx,ty,true)){
      if(tileAt(tx,ty)!==2 || player.hasKey){
        player.px = nx; player.py = ny; player.x = tx; player.y = ty;
      }
    }
  }

  // player animation
  if(moving){
    player.frameTimer += dt;
    if(player.frameTimer > 0.12){ player.frameTimer = 0; player.frameIdx = (player.frameIdx+1) % sprites.frames.playerFrames.length; }
  } else { player.frameIdx = 0; player.frameTimer = 0; }

  // pickups
  if(tileAt(player.x,player.y)===3){ player.hasKey=true; map[player.y][player.x]=0; message='Got the key! Doors will open.'; playSound('pickup'); }

  // exit
  if(tileAt(player.x,player.y)===4){ playSound('win'); if(levelIndex+1 < levels.length){ message='Level complete!'; setTimeout(nextLevel,400); } else { message='You escaped — All levels complete!'; player.alive=false; } }

  // open doors when key obtained
  if(player.hasKey){ for(let y=0;y<map.length;y++) for(let x=0;x<map[0].length;x++) if(map[y][x]===2) map[y][x]=0; }

  // guards
  guards.forEach(g=>{
    const gx = Math.floor((g.px+TILE/2)/TILE), gy = Math.floor((g.py+TILE/2)/TILE);
    const vx = (player.px+TILE/2) - (g.px+TILE/2);
    const vy = (player.py+TILE/2) - (g.py+TILE/2);
    const d = Math.hypot(vx,vy);
    const canSee = d < 160 && !rayBlocked(gx,gy,player.x,player.y);
    if(canSee){ g.state='alert'; g.lastSeen={x:player.x,y:player.y,time:performance.now()}; const path = astar(gx,gy,player.x,player.y); if(path && path.length>1){ g.path=path; g.pathIdx=1; } playSound('alert'); }

    if(g.state==='alert'){
      if(g.path && g.pathIdx < g.path.length){ const target=g.path[g.pathIdx]; const tx=target[0]*TILE, ty=target[1]*TILE; const dxg=tx-g.px, dyg=ty-g.py; const dist=Math.hypot(dxg,dyg); if(dist<2) g.pathIdx++; else { g.px += (dxg/dist)*g.spd; g.py += (dyg/dist)*g.spd; } }
      else { if(g.lastSeen && performance.now()-g.lastSeen.time > 3000){ g.state='calm'; g.path=[]; g.pathIdx=0; } }
    } else { const target=g.patrol[g.i]; const tx=target[0]*TILE, ty=target[1]*TILE; const dxg=tx-g.px, dyg=ty-g.py; const dist=Math.hypot(dxg,dyg); if(dist<2) g.i=(g.i+1)%g.patrol.length; else { g.px += (dxg/dist)*g.spd; g.py += (dyg/dist)*g.spd; } }

    // guard animation
    g.frameTimer = (g.frameTimer || 0) + dt;
    if(g.frameTimer > 0.18){ g.frameTimer = 0; g.frameIdx = (g.frameIdx + 1) % sprites.frames.guardFrames.length; }

    const pdx = (player.px+TILE/2)-(g.px+TILE/2); const pdy = (player.py+TILE/2)-(g.py+TILE/2); if(Math.hypot(pdx,pdy) < 12){ message='Caught by a guard! Game over.'; player.alive=false; playSound('caught'); }
  });
}

function rayBlocked(x0,y0,x1,y1){ let dx = Math.abs(x1-x0), sx = x0<x1?1:-1; let dy = -Math.abs(y1-y0), sy = y0<y1?1:-1; let err = dx+dy; let x=x0,y=y0; while(true){ if(x===x1&&y===y1) return false; const t = tileAt(x,y); if(t===1 || t===2) return true; const e2 = 2*err; if(e2>=dy){ err+=dy; x+=sx; } if(e2<=dx){ err+=dx; y+=sy; } } }

function draw(){
  // if sprite sheet hasn't finished loading, draw simple placeholders
  if(!sprites.ready){
    ctx.clearRect(0,0,canvas.width,canvas.height);
    for(let y=0;y<map.length;y++){
      for(let x=0;x<map[0].length;x++){
        const t = map[y][x];
        const px = x*TILE, py = y*TILE;
        if(t===1) ctx.fillStyle='#444';
        else if(t===2) ctx.fillStyle='#3a3';
        else if(t===3) ctx.fillStyle='#ff8800';
        else if(t===4) ctx.fillStyle='#06f';
        else ctx.fillStyle='#222';
        ctx.fillRect(px,py,TILE,TILE);
        ctx.strokeStyle='#111'; ctx.strokeRect(px,py,TILE,TILE);
      }
    }
    // simple player and guards
    ctx.fillStyle = player.alive ? '#ffdd00' : '#777';
    ctx.fillRect(player.px+6, player.py+6, TILE-12, TILE-12);
    guards.forEach(g=>{ ctx.fillStyle = (g.state==='alert')? '#ff5555' : '#c33'; ctx.fillRect(g.px+6,g.py+6,TILE-12,TILE-12); });
    info.textContent = `Player: (${player.x},${player.y})\nKey: ${player.hasKey?"Yes":"No"}\n${message}`;
    return;
  }

  ctx.clearRect(0,0,canvas.width,canvas.height);
  for(let y=0;y<map.length;y++){
    for(let x=0;x<map[0].length;x++){
      const t = map[y][x];
      const px = x*TILE, py = y*TILE;
      if(t===1) drawTileFrame('wall',px,py);
      else if(t===2) drawTileFrame('door',px,py);
      else if(t===3) drawTileFrame('key',px,py);
      else if(t===4) drawTileFrame('exit',px,py);
      else drawTileFrame('floor',px,py);
      ctx.strokeStyle='#111'; ctx.strokeRect(px,py,TILE,TILE);
    }
  }

  // player
  const pf = sprites.frames.playerFrames[player.frameIdx % sprites.frames.playerFrames.length];
  ctx.drawImage(sprites.sheet, pf.x*TILE, pf.y*TILE, TILE, TILE, player.px, player.py, TILE, TILE);

  // guards
  guards.forEach(g=>{
    const gf = sprites.frames.guardFrames[g.frameIdx % sprites.frames.guardFrames.length];
    ctx.drawImage(sprites.sheet, gf.x*TILE, gf.y*TILE, TILE, TILE, g.px, g.py, TILE, TILE);
    if(g.state==='alert'){
      ctx.strokeStyle='rgba(255,0,0,0.15)'; ctx.beginPath(); ctx.ellipse(g.px+TILE/2,g.py+TILE/2,120,60,0,-Math.PI/2,Math.PI/2); ctx.fillStyle='rgba(255,0,0,0.06)'; ctx.fill();
    }
    if(g.path && g.path.length){ ctx.strokeStyle='rgba(0,255,0,0.25)'; ctx.beginPath(); for(let i=0;i<g.path.length;i++){ const p=g.path[i]; const cx=p[0]*TILE+TILE/2, cy=p[1]*TILE+TILE/2; if(i===0) ctx.moveTo(cx,cy); else ctx.lineTo(cx,cy); } ctx.stroke(); }
  });

  info.textContent = `Player: (${player.x},${player.y})\nKey: ${player.hasKey?"Yes":"No"}\n${message}`;
}

function drawTileFrame(name,x,y){
  const f = sprites.frames[name];
  ctx.drawImage(sprites.sheet, f.x*TILE, f.y*TILE, TILE, TILE, x, y, TILE, TILE);
}

let last = performance.now(); function loop(t){ const dt=(t-last)/1000; last=t; update(dt); draw(); requestAnimationFrame(loop); }
requestAnimationFrame(loop);

// helper placement: ensure there is a locked door on each level and a key
(function placeExtras(){ for(const lvl of levels){ let foundKey=false; for(let y=0;y<lvl.map.length;y++) for(let x=0;x<lvl.map[0].length;x++) if(lvl.map[y][x]===3) foundKey=true; if(!foundKey) lvl.map[1][lvl.map[0].length-3]=3; // place key
  // add a door somewhere near bottom-right if none
  let placedDoor=false; for(let y=lvl.map.length-2;y>0;y--) for(let x=lvl.map[0].length-2;x>0;x--){ if(lvl.map[y][x]===0 && lvl.map[y][x+1]===1){ lvl.map[y][x+1]=2; placedDoor=true; break; } if(placedDoor) break; } } })();

loadLevel(0);
