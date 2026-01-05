Mini Castle Wolfenstein — Minimal HTML demo

Files:
- index.html
- styles.css
- main.js
Mini Castle Wolfenstein — Demo with sprites, sound, and multiple levels

Files:
- index.html
- styles.css
- main.js

How to run:
1. Open `index.html` in your browser, or run a simple static server in the project folder:

```bash
# Python 3
python3 -m http.server 8000
# then open http://localhost:8000
```

Controls:
- Move: WASD or arrow keys
- `Space` will initialize audio in some browsers (user gesture)

Features added:
- Sprite rendering (small 32x32 sprites generated at runtime)
- WebAudio sounds for pickup/alert/win
- Multiple levels with progression and level UI
- Guard A* pathfinding and alert state

Next improvements I can add on request:
- Replace runtime-generated sprites with a sprite sheet and animations
- Add sound assets and music
- Level editor / JSON level loader
- Mobile touch controls and gamepad support
