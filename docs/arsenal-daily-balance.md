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

| Map | Exploration waves | First-wave budget | Added per wave | Final-wave budget | Spawn batch | Interval | Active cap | HP scale, wave 1 |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| 1 | 3 | 24 | 6 | 24 | 3 | .65 s | 24 | 1.0 |
| 2 | 4 | 32 | 6 | 36 | 4 | .55 s | 40 | 1.25 |
| 3 | 5 | 40 | 6 | 48 | 5 | .45 s | 64 | 1.55 |
| 4 | 6 | 48 | 6 | 64 | 6 | .38 s | 88 | 1.9 |

Each map additionally ends with its boss. The complete fixed campaign budget is 1064 ordinary enemies (114/200/308/442 per map), excluding boss modules. Rushing the final room cannot skip the exploration waves. The final-room trigger waits for them to be cleared. The simulation does not spawn further exploration waves beyond its count.

Daily HP increases another 3.5% of the map base per wave; the four class HP weights remain exactly 1:2:3:6. Primary upgrades do not cause enemy HP to increase. Intermissions last 1.5 seconds. Failed placements retry after .12 seconds with a bounded search. Daily fallback placements follow the player's current room rather than sampling distant map anchors. They still require valid ground, free space, geometry visibility and separation; a .55-second activation delay and visible marker telegraph newly spawned enemies.

Daily XP costs are `12 + 3×(level−1)`; XP drops and collection still matter for power upgrades, but the seven primary weapons advance at cumulative kill counts 0/16/53/128/255/447/724, across maps. This separates weapon access from a flood of XP levels while making it proportional to the larger enemy budget. Normal/horde retain their original primary XP milestones.

## Evidence and limitations

`tools/daily-pressure-report.php` tests three real daily dates and four generated maps per date with an invulnerable stationary player for 15 simulated seconds. Initial observed peaks were 24 on map 1, 27–32 on map 2, 33–34 on map 3 and 30–35 on map 4. These are geometry-limited observations, not a promise that all 88 allowed enemies appear simultaneously or all fit on screen. The report runs in CI and is uploaded separately.

Tests cover asset dimensions and PNG transparency, the 1064-enemy budget, primary kill thresholds, sustained bounded spawns, final-wave gating, power replacement/save migration, EMP immobilization/recovery and homing target updates. Native SDL tests capture every primary stage and five power effects. Browser tests verify replacement of flame by EMP and a single selected HUD power.

The previous survival simulation guard assumed stacked powers. With the exclusive slot, the current deterministic medium policy reaches wave 7 for one of three representative seeds; the other two runs end in combat deaths, not stalls. Its acceptance threshold now requires a reachable late-game path rather than the old two-of-three result. The input policy retains an upgraded power instead of randomly replacing it with rank one; it still uses only legal offered choices and never injects health/XP. These policy results do not estimate human win rates.

Audio composition and sample playback are unchanged by this update.
