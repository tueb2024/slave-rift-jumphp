# Nmn enemy sheet inspection (2026-10-30)

These measurements come from the alpha pixels in the checked-in PNGs. All four sheets have eight camera directions in their directional rows. Their frame centers and row positions are irregular, and the death rows have fewer frames. `src/Gfx/EnemyAtlas.php` records a rectangle for every visible frame, padded by up to two transparent pixels. The renderer checks texture bounds before copying.

| Sheet | PNG | Layout and margins | Walk | Attack | Pain | Death |
| --- | --- | --- | --- | --- | --- | --- |
| mutant-grunt | 814×1227 | 12 rows; normal direction centers roughly x=60,165,265,365,460,550,640,730. First visible pixels x=28, y=13; last visible y=1151. Transparent row gaps vary from 13 to 33 px. | rows 0–3, 8 directions each | rows 5–7, 8 directions | row 10, 8 directions | row 11 at y=1082–1151, **7** sequential frames, not directions |
| ratman | 592×658 | 8 rows; direction centers roughly x=40,115,200,275,345,405,470,535. First visible pixels x=18, y=17; last visible y=616. Row gaps vary from 4 to 25 px. | rows 0–3, 8 directions | rows 4–5, 8 directions | row 6, 8 directions | row 7 at y=550–616, **6** sequential frames |
| mutant-leader | 889×1493 | 12 rows; direction centers roughly x=80,185,280,380,480,590,680,765. First visible pixels x=45, y=28; last visible y=1384. Row gaps vary from 17 to 38 px. | rows 0–3, 8 directions | rows 5–7, 8 directions | row 10, 8 directions | row 11 at y=1308–1384, **6** sequential frames |
| enforcer-guard | 516×836 | 11 rows; direction centers roughly x=45,105,165,225,285,345,400,455. First visible pixels x=27, y=15; last visible y=804. Row gaps vary from 9 to 19 px. | rows 8–10, 8 directions | rows 1–6, 8 directions | idle row 0 (sheet has no separate pain row) | row 7 at y=542–599, **6** sequential frames; walk follows death in the PNG |

The old `width / 8` and `height / rows` arithmetic was especially wrong at the bottom of the taller sheets: grunt death began at y=1082 but was sampled from y≈1125; leader death began at y=1308 but was sampled from y≈1369. Enforcer columns are also much tighter than 516/8, so late directions were cut across the wrong parts of the sheet. The rectangles now follow the actual opaque clusters, with transparent gutters between neighbors. All declared rectangles are checked against the original PNG dimensions by the simulation test.

## DM1 open-room measurement

The DM1 mesh has 492 brushes, 4,928 triangles and four persistent material draw calls. A local PHP benchmark placed the same 48 synthetic enemies around DM1 deathmatch anchors, projected them at 320×180 with the 67° game camera, and tested two views from spawn point 0:

| View | Projected candidates | Actually visible | Old render LOS CPU | Cached render LOS CPU, mean over 60 frames | Precise LOS/frame after cache |
| --- | ---: | ---: | ---: | ---: | ---: |
| Narrow view, yaw 180° | 2 | 1 | 0.49 ms | 0.06 ms | 0.3 |
| Open view, yaw 270° | 31 | 3 | 3.87 ms | 0.51 ms | 3.3 |

The open view is still more expensive because 29 more sprites survive projection and screen culling. The measured LOS cost falls by about 7.6× in that view. These are CLI CPU costs for the render LOS path, not whole-frame FPS measurements on jumphp.net. The in-game debug overlay reports total frame time, enemy counts, render LOS, simulation LOS, neighbor checks, visual budgets and `MAP 1` (one whole-map chunk drawn via four material buffers) for a live corridor/open-room comparison. The mesh was left intact because its four draw calls and 4,928 triangles did not explain the measured PHP cost; a spatial split would add batches without addressing that hotspot.

A separate deterministic DM1 wave-9 stress run (seed 4, stationary player, 20 simulated seconds) reached the unchanged cap of 48 active enemies. Before the anchor-based fallback, 41 of them were within 7 units of the player, with 11 same-floor pairs closer than 0.9 units. With the fallback distributed around DM1 anchors, 12 were within 7 units and one pair was closer than 0.9 units. The minimum pair distance was 0.89 units after local movement; newly spawned pairs obey the larger 2.0–2.8 unit comfort distance while free valid sites remain.
