# Slave Rift — JumPHP experiment

A deliberately anachronistic FPS prototype: **Quake-style brush maps**, a **PowerSlave/SlaveDriver-inspired runtime shape**, and **Chasm-like low-poly combat**, implemented as an original JumPHP game.

The project contains no Quake, PowerSlave or Chasm proprietary game data, textures, sounds or models. The current test level is mostly original geometry, with two small convex structures adapted from the GPL-2.0 `spirit1dm2.map` source by spirit to exercise the real brush importer.

## Milestone 0.2 — real brush geometry

- JumPHP game structure at repository root (`config/`, `src/`, `assets/`).
- OpenGL desktop / WebGL browser renderer using `JumPHP\Gl\WebGl` and `Mat4`.
- Quake `.map` reader that reconstructs **convex brushes from their planes**.
- Brush-plane intersections are rebuilt as real XYZ vertices, sorted per face and triangulated for the GPU.
- Rendering no longer reduces every brush to an axis-aligned box.
- Collision deliberately remains conservative AABB collision for this milestone.
- Test map: chamber → doorway → second chamber, now with non-orthogonal GPL-derived brush structures.
- FPS camera, forward/back, strafe and turn.
- One original low-poly guardian with chase/melee AI.
- Hitscan-style firing and HUD weapon overlay.
- `demo: true` autopilot for JumPHP smoke tests.
- Software top-down fallback when GL is unavailable.
- `site/index.html` plus repository preview artwork for JumPHP/web import metadata.

This is now a genuine polygonal 3D pipeline: the map supplies planes, the importer derives convex vertices and faces, and JumPHP/WebGL renders those triangles. It is still intentionally much smaller than Quake BSP or the Kex engine.

## Controls

- `Up` / `W`: forward
- `Down` / `S`: backward
- `Left` / `Right`: turn
- `A` / `D`: strafe
- `Space`: fire
- `Enter`: restart after death / clear
- `Esc`: pause if the host supplies the default pause action

JumPHP's action axis convention is `axis(negative, positive)`. The scene therefore maps back/forward, left/right strafe and left/right turn explicitly through that convention.

## JumPHP import

Import the public repository in the JumPHP playground/site importer:

`https://github.com/tueb2024/slave-rift-jumphp`

For a local JumPHP checkout, copy/clone it as `games/slave-rift` and run the normal JumPHP commands, e.g.:

```bash
bin/jumphp install slave-rift
bin/jumphp test slave-rift
bin/jumphp run slave-rift
```

The smoke configuration starts `App\Scene\PlayScene` with `demo: true`, so it should continue moving, aiming and firing without keyboard input.

## Geometry pipeline

```text
.map text
  ↓
BrushMap
  ├─ parse face planes
  ├─ orient planes toward brush interior
  ├─ intersect plane triplets → convex XYZ vertices
  ├─ collect/sort vertices per face
  ├─ triangulate faces → WebGL mesh
  ├─ AABB bounds → conservative collision
  ├─ player start
  └─ guardian spawn

PlayScene
  ↓
Run (simulation, collision, AI, hitscan)
  ↓
World (camera + renderer)
```

Coordinate conversion is explicit: Quake `X/Y/Z` becomes JumPHP world `X/Z/-Y`; 32 Quake units = 1 world unit.

The next useful importer milestone is to add texture coordinates/material textures and replace conservative AABB collision with brush-plane collision or a generated collision mesh.

## Licensing / provenance

Prototype code and original project geometry: GPL-3.0-or-later for this experiment. JumPHP itself is a separate MIT-licensed dependency/runtime.

`assets/maps/rift01.map` also contains two small adapted convex brush structures derived from `spirit1dm2.map` by spirit, published under GPL-2.0 in `dfsp-spirit/spirit-quake-maps-gpl`. Those structures were rescaled/repositioned and use only original/procedural Slave Rift materials; no Quake textures, sounds or models are included.

Source: `https://github.com/dfsp-spirit/spirit-quake-maps-gpl`

The upstream repository explicitly states that its `.map` sources are GPL but the original map assets are not included and generally need replacement. That is the approach used here.
