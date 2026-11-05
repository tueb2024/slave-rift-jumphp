# Animated arsenal, exclusive powers and daily pressure

## Runtime artwork

Seven stages use separate eight-frame RGBA atlases exported at 96×128 per pose: pistol, dual pistols, SMG, dual SMGs, belt-fed machine gun, rotary gun and dual rotary guns. The dual artwork is split into left/right halves around the aim point and uses the actual shot side; secondary effects cannot trigger the primary weapon animation. Weapon animation is driven by primary shot serials from the shared rhythm-driven simulation.

Five animated power atlases provide flames, a sniper beam/impact, artillery explosions, a homing rocket exhaust loop and an EMP shock ring. World effects use the camera projection and geometry visibility; foreground flame/EMP effects run independently of the FPS weapon sprite. The old secondary gun icons are replaced by a recharge ring. Asset provenance is in `assets/ASSETS.md`.

The runtime PNGs total 426823 bytes before filesystem duplication into the synchronized package. Original high-resolution art, separate preview frames and GIFs are deliberately not part of the runtime.

## One power slot

Choosing a different power replaces the equipped one, clears other power ranks, cancels its active flame/projectiles and resets the old dispatch queue. Choosing the same power upgrades it. Snapshots preserve the selected power and cooldown; old multi-power saves are reduced to one deterministic selection. Pulse remains a mutually exclusive alternative. Switching is identified in upgrade descriptions.

EMP becomes available at level 8. It paralyzes nearby enemies within 3 m for 2 seconds, cancels their attack windups and also suspends a nearby boss's attacks. Cooldown is 12 seconds at rank one, decreasing to 10 at rank three. It does not deal damage. Rockets update the target position and turn at a bounded 3.5 rad/s, retaining wall collision and line-of-sight checks for blast damage.

Damaging powers were strengthened to compensate for the removal of stacked powers: flame base ticks 9, sniper base 180 + rank×55, rockets 95 + rank×28, artillery 150 + rank×28, pulse 45 + rank×20, before existing upgrades. No extra primary weapon or independent fire clock was added.

## Daily rules

| Map | Exploration budgets | Final wave | Total | Batch | Interval | Active cap | Intermission |
|---|---|---:|---:|---|---|---|---:|
| 1 | 10 / 12 / 14 | 8 | 44 | 1 | 1.15 s before kill 8, then .85 / .77 / .69 s | 4 / 6 / 8 | 4 s |
| 2 | 16 / 18 / 20 / 22 | 12 | 88 | 1 then 2 | .95 → .80 s | 8 → 12 | 3.5 s |
| 3 | 24 / 26 / 28 / 28 / 30 | 16 | 152 | 2 | .80 → .65 s | 12 → 16 | 3 s |
| 4 | 32 / 34 / 36 / 36 / 38 / 40 | 20 | 236 | 2 then 3 | .70 → .55 s | 16 → 20 | 2.5 s |

The complete campaign budget is **520 ordinary enemies**, excluding bosses and their modules. Primary weapons advance at **0 / 8 / 26 / 63 / 125 / 218 / 354 cumulative kills**. XP still controls upgrades and tempo, with costs `12 + 3×(level−1)`. Normal and Horde retain their existing rules.

HP scales remain 1 / 1.25 / 1.55 / 1.9 by map, plus the existing 3.5% per wave. Class ratios, damage, healing, boss rules and exclusive powers are unchanged. Spawn placement still checks support, collision, visibility and separation, with a .55 s activation delay. Full caps defer appearances rather than deleting survivors or forgetting the remaining budget. Every exploration wave must be cleared before the final encounter can start.


## Evidence and limitations

`tools/daily-pressure-report.php` tests three real daily dates and four generated maps per date with an invulnerable stationary player for 15 simulated seconds. Before the progressive update, observed peaks were 24 on map 1, 27–32 on map 2, 33–34 on map 3 and 30–35 on map 4. These are geometry-limited observations, not a promise that all 88 allowed enemies appear simultaneously or all fit on screen. The report runs in CI and is uploaded separately.

Tests cover asset dimensions and PNG transparency, the 520-enemy budget, primary kill thresholds, sustained bounded spawns, final-wave gating, power replacement/save migration, EMP immobilization/recovery and homing target updates. Native SDL tests capture every primary stage and five power effects. Browser tests verify replacement of flame by EMP and a single selected HUD power.

The previous survival simulation guard assumed stacked powers. With the exclusive slot, the current deterministic medium policy reaches wave 7 for one of three representative seeds; the other two runs end in combat deaths, not stalls. Its acceptance threshold now requires a reachable late-game path rather than the old two-of-three result. The input policy retains an upgraded power instead of randomly replacing it with rank one; it still uses only legal offered choices and never injects health/XP. These policy results do not estimate human win rates.

Audio composition and sample playback are unchanged by this update.
