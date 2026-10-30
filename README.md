# Slave Rift — FPS horde survival in PHP / JumPHP

A playable first survival loop built on the textured FPS prototype at `e706459`. Move and jump in first person, face the horde for automatic fire, collect XP crystals and choose upgrades while the action is paused. The simulation, progression, input, HUD and rendering orchestration are PHP. JumPHP supplies SDL / OpenGL on desktop and PHP WebAssembly / WebGL in the browser.

## Play

Import `https://github.com/tueb2024/slave-rift-jumphp` in the JumPHP playground and select the `mvp/fps-survivors` branch (or its eventual merged commit). The packaged entry point is **`games/slave-rift/`**. Root sources and that package are synchronized by `php tools/sync-package.php`; CI checks their contents match.

- **W/S or Up/Down:** move. **A/D:** strafe. **Left/Right:** turn.
- **Space:** jump. **P:** pause. **Enter:** restart after death.
- **Automatic Riveter:** hits a visible threat in the forward cone; close attackers get priority. Aim by turning. Hold **F** to narrow the targeting cone.
- **Automatic Pulse:** damages nearby enemies in all directions, respecting walls. Unlock it through an upgrade or automatically at wave 3.
- **1 / 2 / 3** (or **R / T / Y**): pick a level-up upgrade. Gamepad A/B/X also choose upgrades.

There is no ammunition management in this MVP. Enemy deaths drop cyan XP crystals; every ninth kill drops healing. Crystals drift toward you within pickup range. XP offers three different upgrades: damage, fire rate, pickup range, health, or Pulse. Survive indefinitely and improve your time / wave / kill count.

## The loop

Waves spend a growing spawn budget in batches. Spawn positions vary by seed, stay away from the player and reject solid geometry. Runners are fast and fragile, guardians are balanced, and brutes appear from wave 3 with high health. Every fourth wave favors brutes. A wave lasts at most 26 active seconds, followed by a four-second breather and +10 health; surviving enemies carry over. A cleared wave ends early.

At most 48 enemies and 80 pickups are active. Spawn attempts, simulation substeps and retained visual effects are bounded. The arena seed changes on a normal restart; demo/tests use a fixed seed. The small RNG stays within signed 32-bit integer arithmetic for PHP WebAssembly.

The original decorative wedges have been replaced with an original open arena, columns and four 0.18-unit steps leading onto the dais. A 0.23-unit player step-up plus resolved floor contact makes it walkable without jumping. Enemies use the same stair heights, so the dais is not a permanently safe perch.

## Architecture audit and implementation

| Area | Starting point | MVP |
|---|---|---|
| `Run` | One guardian, manual hitscan, death/clear | Wave director, multiple enemies, threat selection, two auto weapons, XP, upgrades, damage grace period |
| `PlayScene` | Input + fixed step + guardian HP | Input + 60 Hz fixed step, queued jump, upgrade/death/pause screens, survival HUD |
| `World` | World + one guardian buffer | Shared per-type enemy/pickup buffers, hit flashes, tracers and expanding Pulse; reuse resources on restart |
| `BrushMap` | Convex brush triangulation, AABB collision | Importer retained; original axis-aligned arena makes collision match visible geometry |
| `config/input.yaml` | Movement, jump, fire, restart | Upgrade choices and pause; corrected camera-relative strafe/turn directions |
| `assets` | Mixed test geometry and replacement PNGs | Original arena, original pixel texture, original procedural meshes; provenance in `assets/ASSETS.md` |
| Packaging | Two manually maintained copies | Sync tool and CI equality check |

Line of sight uses exact segment/AABB slab intersections. This prevents automatic weapons shooting through thin walls. Enemy pursuit tries tangents around blockers and uses spacing to reduce stacking; this is lightweight local steering, not a navigation mesh.

## Checks

Pure PHP checks need no engine or Composer:

```sh
php tools/simulation-test.php
php tools/sync-package.php --check
```

They exercise map reconstruction, jump/landing, stair climbing, enemy stair pursuit, wall collision, normalized movement, target selection, thin-wall obstruction, Pulse, XP, upgrade freeze, deterministic runs, all enemy types and a 200-second survival simulation.

`.github/workflows/checks.yml` runs those on PHP 8.2 and 8.5. A separate job pins JumPHP to `28ea932981fb0b6d8dc41f2c9739f941cb4befea`, builds its official SDL/OpenGL Docker runtime, runs integration tests and the 600-frame software smoke, then a 900-frame OpenGL demo with a screenshot artifact.

In an existing JumPHP checkout, copy `games/slave-rift/` into its games directory, configure Composer's local engine repository as in the official sample games, then run:

```sh
bin/jumphp install slave-rift
bin/jumphp test slave-rift
JUMPHP_SCENE='App\Scene\PlayScene' JUMPHP_SCENE_PARAMS='{"demo":true}' bin/jumphp shot slave-rift 900
bin/jumphp run slave-rift
```

The current Windows authoring environment has no PHP, Docker or WSL runtime. Native checks are therefore delegated to CI; see the workflow result for the published commit. The actual playground import, browser performance, gamepad mappings and human difficulty/fun assessment still require an interactive playtest. The software fallback is diagnostic top-down; the normal GL/WebGL game is FPS.

## License

Code, original map, meshes and preview: **GPL-3.0-or-later**. Original `rift-stone.png`: **CC0-1.0**. JumPHP is a separate MIT-licensed engine. See `LICENSE` and `assets/ASSETS.md`.

No proprietary game data or ripped sprites are included. VG Resource / Spriters Resource downloads were not used because a download listing does not establish permission to reuse the original game's artwork.
