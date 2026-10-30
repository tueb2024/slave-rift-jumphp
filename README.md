# Slave Rift — JumPHP experiment

A deliberately anachronistic FPS prototype: **Quake-style brush maps**, a **PowerSlave/SlaveDriver-inspired runtime shape**, and **Chasm-like low-poly combat**, implemented as an original JumPHP game.

This first milestone is intentionally small and clean-room. It contains **no Quake, PowerSlave or Chasm game data, textures, sounds, maps or models**. The bundled map and geometry are original and exist only to prove the pipeline.

## Milestone 0.1

- JumPHP game structure at repository root (`config/`, `src/`, `assets/`).
- OpenGL desktop / WebGL browser renderer using `JumPHP\Gl\WebGl` and `Mat4`.
- A deliberately restricted Quake `.map` reader: **axis-aligned worldspawn brushes** become solid boxes.
- Original test map: chamber → doorway → corridor → second chamber.
- FPS camera, forward/back, strafe and turn.
- Simple AABB world collision.
- One original low-poly guardian with chase/melee AI.
- Hitscan-style firing and HUD weapon overlay.
- `demo: true` autopilot for JumPHP smoke tests.
- Software top-down fallback when GL is unavailable.

The goal of this milestone is not to implement Quake BSP or clone SlaveDriver. It proves that a textual brush map can feed a PHP simulation and JumPHP's current 3D renderer.

## Controls

- `Up` / `W`: forward
- `Down` / `S`: backward
- `Left` / `Right`: turn
- `A` / `D`: strafe
- `Space`: fire
- `Enter`: restart after death / clear
- `Esc`: pause if the host supplies the default pause action

## JumPHP import

This repository is shaped as a single JumPHP game. Import the public GitHub repository in the JumPHP playground/site importer:

`https://github.com/tueb2024/slave-rift-jumphp`

For a local JumPHP checkout, copy/clone it as `games/slave-rift` and run the normal JumPHP commands, e.g.:

```bash
bin/jumphp install slave-rift
bin/jumphp test slave-rift
bin/jumphp run slave-rift
```

The smoke configuration starts `App\Scene\PlayScene` with `demo: true`, so it should continue moving, aiming and firing without keyboard input.

## Map subset

`assets/maps/rift01.map` uses normal Quake-style entity/brush text syntax, but the current parser intentionally supports only a safe first subset:

- `worldspawn` brushes;
- axis-aligned convex boxes;
- `info_player_start` with `origin` and optional `angle`;
- `monster_guardian` with `origin`.

The parser reads the plane points of a brush and derives the brush AABB. Angled wedges and arbitrary convex brushes therefore **must not** be treated as faithful geometry yet. That is the next importer milestone.

Coordinate conversion is explicit: Quake `X/Y/Z` becomes JumPHP world `X/Z/Y` with Quake Y negated for camera-friendly forward orientation; 32 Quake units = 1 world unit.

## Architecture

```text
.map text
  ↓
BrushMap (parse + coordinate conversion)
  ├─ solid AABBs → collision
  ├─ world mesh → WebGL/OpenGL buffer
  ├─ player start
  └─ guardian spawn

PlayScene
  ↓
Run (simulation, collision, AI, hitscan)
  ↓
World (camera + renderer)
```

This intentionally echoes the useful *concepts* of older sector/object engines — logical objects separated from rendering and collision — without copying their platform-specific renderer.

## Licensing / provenance

Prototype code and original map: GPL-3.0-or-later for this experiment. JumPHP itself is a separate MIT-licensed dependency/runtime. No third-party game assets are included.

Future experiments may ingest maps whose own licenses permit redistribution. A map being readable by this importer does not grant rights to redistribute that map's textures or other game data.
