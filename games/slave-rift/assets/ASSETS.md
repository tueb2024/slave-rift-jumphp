# Asset provenance

Slave Rift uses only assets that are either original project work or from sources with explicit redistribution terms.

## Quake environment

- `maps/quake-dm1.map`: **DM1 — Place of Two Deaths**, from the original Quake map source release republished by `fzwoch/quake_map_source`. The source release is GPL; Slave Rift reads the real worldspawn brush layout and the seven `info_player_deathmatch` positions. Brush entities such as moving doors/lifts are ignored by the lightweight runtime rather than converted into static blockers.
  - Source: https://github.com/fzwoch/quake_map_source
  - Upstream file: `dm1.map`
- `maps/rift01.map` / `rift02.map`: earlier original Slave Rift prototype arenas, retained as fallbacks/tests. GPL-3.0-or-later.

## Environment textures

- `textures/quadcompati/cop1_1.png`, `ceil1_1.png`, `crate0_side.png`: from LibreQuake / QuadCompati v0.01. The upstream README states that all texture WADs in v0.01 are under CC0 / MIT0 / BSD0.
- `textures/quadcompati/metal_panel.png`, `floor_dark.png`, `trim_grid.png`: project-made derivatives used to give Quake material groups distinct stone/metal/floor/trim treatment.
- `textures/rift-stone.png`: compatibility copy kept for the renderer.
  - Source: https://github.com/LibreQuake/QuadCompati

## FPS weapon sprites

- `sprites/weapons/fps-weapons-cc0.png`: **FPS Weapons Overlay** by knekko, CC0. The 5×4 sheet provides knife, pistol, machine-gun and chain-gun animation frames. Slave Rift currently maps pistol → Colt view, machine gun → Sten/SMG tiers, chain gun → heavy/sulfateuse tiers.
  - Source: https://opengameart.org/content/fps-weapons-overlay
- `sprites/weapons/weapons.png` and `colt45.png`: earlier original Slave Rift placeholders, retained as fallbacks/history. CC0-1.0.

## Combat effects

- `sprites/effects/muzzle-large-fire.png`: three-frame muzzle-flash strip from **Gun Muzzle Flash Effects (Fire and Ion and Melee)** by Reactorcore, CC0. The image is 240×80 (three 80×80 frames) and is drawn over the weapon during firing. The PNG was extracted from the source archive without pixel edits.
  - Source: https://opengameart.org/node/179598
- `sprites/effects/spark-impact.png`: **Spark effect** by kurohina, CC0. The image is 288×32 (nine 32×32 frames) and plays at confirmed enemy-hit positions. The source PNG was copied without pixel edits.
  - Source: https://opengameart.org/content/spark-effect

## Pickups

- `sprites/pickups/health-pack.png` and `sprites/pickups/xp-orb.png`: cropped from **Items – armor, health, ammo** by Nmn, CC0. The original sheet is 411×318. The health pack crop is 32×38 and the blue orb crop is 29×36; the cyan chroma key was converted to transparent alpha. They replace the generated 3D pickup cubes with readable, floating 2.5D sprites.
  - Source: https://opengameart.org/content/items-armor-health-ammo

## Enemy sprites

The active runner / guardian / brute billboards now use the directional Nmn sheets below: mutant grunt, rat-man, and mutant leader respectively. The earlier Ragnar Random **FPS Monster Enemies** sheets remain in the repository as CC0 historical assets and are not loaded by the current renderer.

- `sprites/enemies/eviloogie.png`
- `sprites/enemies/smorficus.png`
- `sprites/enemies/demonario.png`
- Source: https://opengameart.org/content/fps-monster-enemies

The uploaded Duke Nukem 3D Overlord sprite set is used only as a private visual/animation reference and is **not committed to this repository**.

### CC0 directional enemy candidates

These are selected replacement candidates for the renderer. Three are now active: the grunt for runners, the rat-man for guardians, and the leader for brutes. The remaining sheets are staged for later enemy roles. The OpenGameArt pages identify Pawel “Nmn” Zarczynski as the author and release these works under CC0. The original cyan chroma key was converted to transparent alpha; sprite pixels and layout were otherwise retained.

| Local file | Original title / source | Dimensions | Frames / directions / animation notes | Intended role |
| --- | --- | ---: | --- | --- |
| `sprites/enemies/nmn-cc0/mutant-grunt.png` | [Monster Mutant Grunt for FPS game](https://opengameart.org/content/monster-mutant-grunt-for-fps-game) | 814×1227 | 96 cells (8×12); 8 rotational views; idle, 4 walk rows, 3 attack rows, pain and death rows wired | runner |
| `sprites/enemies/nmn-cc0/mutant-leader.png` | [Monster Mutant Leader for FPS Game](https://opengameart.org/content/monster-mutant-leader-for-fps-game) | 889×1493 | 96 cells (8×12); 8 rotational views; idle, 4 walk rows, 3 attack rows, pain and death rows wired | brute |
| `sprites/enemies/nmn-cc0/melee-mutant.png` | [Monster close combat mutant for FPS game using sprites](https://opengameart.org/content/monster-close-combat-mutant-for-fps-game-using-sprites) | 844×737 | Mixed 5/8 view layout; the author notes that five symmetrical poses are mirrored; clip map pending | melee candidate |
| `sprites/enemies/nmn-cc0/ratman.png` | [Monster Rat-man for fps game of 2.5 kind](https://opengameart.org/content/monster-rat-man-for-fps-game-of-25-kind) | 592×658 | 72 cells (8×9); 8 directional views; idle, 4 walk rows, 3 attack rows, pain and death rows wired | guardian |
| `sprites/enemies/nmn-cc0/enforcer-guard.png` | [Human guard for Sprite Based FPS](https://opengameart.org/content/human-guard-for-sprite-based-fps) | 516×836 | 8 rotational views; movement, attack, crouch/jump and death poses; clip map pending | ranged / human elite candidate |
| `sprites/enemies/nmn-cc0/harkubus-heavy.png` | [Monster for 3d fps based on sprites – Harkubus](https://opengameart.org/content/monster-for-3d-fps-based-on-sprites-harkubus) | 716×1272 | Five visible directional columns and multiple action rows; exact clip map needs verification | heavy / boss candidate |
| `sprites/enemies/nmn-cc0/grell-flyer.png` | [Monster for 3d shooter – Sprite](https://opengameart.org/content/monster-for-3d-shooter-sprite) | 378×696 | Five visible columns; several flight, attack and death poses; exact clip map needs verification | flying / ranged candidate |

All seven sheets are by Nmn (Pawel Zarczynski), CC0. The local files are compact (under 1 MB total). The active sheets use camera-relative direction columns and state-selected row groups; exact clip ordering is a visual mapping of the provided sheet, since the source pages do not publish row names. Unassigned sheets remain candidates, not gameplay assignments.

## Audio

- Gameplay SFX and drum voices are generated at runtime by `src/Audio/RiftSfx.php` as original 44.1 kHz PCM.
- The reactive lead and wave sting are generated by JumPHP `Synth` in `src/Audio/RiftMusic.php`.

## Integrity

- `VENDORED.sha256` records hashes of the vendored Quake map and CC0 sprite sheets.
- No VG Resource / Spriters Resource / Duke or other proprietary ripped artwork is included in the repository.
