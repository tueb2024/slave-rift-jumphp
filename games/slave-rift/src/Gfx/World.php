<?php

namespace App\Gfx;

use App\Game\BrushMap;
use App\Game\Run;
use JumPHP\Gl\Mat4;
use JumPHP\Gl\WebGl;
use JumPHP\Kernel\Engine;
use JumPHP\Renderer\Renderer;

/** Persistent GPU resources shared by every enemy and pickup. */
final class World
{
    public const KEY = [255, 0, 255];

    /** @var array<string,int> */
    private array $ids = [];
    private ?int $preparedMapId = null;
    /** @var array<int,array<string,float|bool>> */
    private array $enemyVisibility = [];
    /** @var array<int,int> Last rendered health, used to derive visual damage deltas without coupling Run to rendering. */
    private array $lastEnemyHealth = [];
    /** @var list<array{x:float,y:float,z:float,amount:int,born:float}> */
    private array $damagePopups = [];
    public int $visibleEnemies = 0;
    public int $renderLosTests = 0;
    public int $mapChunksDrawn = 0;
    public const MAX_IMPACTS = 24;
    public const MAX_DAMAGE_POPUPS = 48;

    public function prepare(WebGl $gl, Run $run): void
    {
        if (!$gl->isAvailable()) return;
        $mapId = spl_object_id($run->map);
        if ($this->ids === [] || $this->preparedMapId !== $mapId) {
            $materialMap = function (string $material) use ($run): BrushMap {
                $m = new BrushMap();
                $m->solids = array_values(array_filter($run->map->solids, static function (array $solid) use ($material): bool {
                    return strtolower((string)($solid['material'] ?? 'stone')) === $material;
                }));
                return $m;
            };
            $ids = [
                'program' => $gl->program(Meshes::VERTEX, Meshes::FRAGMENT),
                'worldStone' => $gl->buffer(Meshes::world($materialMap('stone')), true),
                'worldMetal' => $gl->buffer(Meshes::world($materialMap('metal')), true),
                'worldCeil' => $gl->buffer(Meshes::world($materialMap('ceil')), true),
                'worldCrate' => $gl->buffer(Meshes::world($materialMap('crate')), true),
                'guardian' => $gl->buffer(Meshes::enemy('guardian'), true),
                'runner' => $gl->buffer(Meshes::enemy('runner'), true),
                'brute' => $gl->buffer(Meshes::enemy('brute'), true),
                'pulse' => $gl->buffer(Meshes::pulse(), true),
                'stoneTex' => $gl->texture('assets/textures/librequake/brick1.png', false, true),
                'metalTex' => $gl->texture('assets/textures/librequake/met_blc_block.png', false, true),
                'ceilTex' => $gl->texture('assets/textures/librequake/med_flat8.png', false, true),
                'crateTex' => $gl->texture('assets/textures/librequake/crate4.png', false, true),
            ];
            $poster = Meshes::decals($run->map->decals, 'poster3615');
            if ($poster !== []) {
                $ids['poster3615'] = $gl->buffer($poster, true);
                $ids['poster3615Tex'] = $gl->texture('assets/textures/posters/3615-troll.png', false, false);
            }
            $this->ids = $ids;
            $this->preparedMapId = $mapId;
        }
        $gl->setLayer('below', 'native', self::KEY);
        $gl->setSupersample(1);
    }

    public static function eye(Run $run): array
    {
        return [$run->x, $run->y + 0.42, $run->z];
    }

    public static function camera(Run $run, float $aspect, float $pitch = 0.0): array
    {
        $eye = self::eye($run);
        $cp = cos($pitch);
        $target = [
            $eye[0] + sin($run->yaw) * $cp,
            $eye[1] + sin($pitch),
            $eye[2] + cos($run->yaw) * $cp,
        ];
        return Mat4::multiply(
            Mat4::perspective(67.0, $aspect, 0.08, 48.0),
            Mat4::lookAt($eye, $target),
        );
    }

    public function render(Engine $engine, Renderer $r, Run $run, float $pitch = 0.0): void
    {
        $this->captureDamagePopups($run);
        $gl = $engine->gl();
        if ($gl->isAvailable() && $this->ids !== []) {
            $this->mapChunksDrawn = 1;
            $r->clear(self::KEY);
            $this->draw3d($gl, $r, $run, $pitch);
            $this->drawPickupSprites($engine, $r, $run, $pitch);
            $this->drawEnemySprites($engine, $r, $run, $pitch);
            $this->drawDeathSprites($engine, $r, $run, $pitch);
            $this->drawImpactSprites($engine, $r, $run, $pitch);
            $this->drawDamagePopups($r, $run, $pitch);
            return;
        }
        $this->mapChunksDrawn = 0;
        $this->drawFallback($r, $run);
    }

    private function draw3d(WebGl $gl, Renderer $r, Run $run, float $pitch): void
    {
        $fog = [0.14, 0.12, 0.15];
        $gl->clear([18, 13, 22]);
        $vp = self::camera($run, $r->getWidth() / max(1, $r->getHeight()), $pitch);
        $common = ['u_viewProj' => $vp, 'u_eye' => self::eye($run), 'u_fog' => $fog];
        $draw = function (int $buffer, array $model, float $flash = 0.0, bool $textured = false, string $texture = 'stoneTex') use ($gl, $common): void {
            $gl->draw($this->ids['program'], [
                'attributes' => [
                    'a_pos' => [$buffer, 3, Meshes::STRIDE, 0],
                    'a_color' => [$buffer, 3, Meshes::STRIDE, 3],
                    'a_normal' => [$buffer, 3, Meshes::STRIDE, 6],
                    'a_uv' => [$buffer, 2, Meshes::STRIDE, 9],
                ],
                'uniforms' => $common + ['u_model' => $model, 'u_flash' => $flash, 'u_useTex' => $textured ? 1.0 : 0.0],
                'textures' => ['u_tex' => $this->ids[$texture]],
            ]);
        };

        $draw($this->ids['worldStone'], Mat4::identity(), 0.0, true, 'stoneTex');
        $draw($this->ids['worldMetal'], Mat4::identity(), 0.0, true, 'metalTex');
        $draw($this->ids['worldCeil'], Mat4::identity(), 0.0, true, 'ceilTex');
        $draw($this->ids['worldCrate'], Mat4::identity(), 0.0, true, 'crateTex');
        if (isset($this->ids['poster3615'], $this->ids['poster3615Tex'])) {
            $draw($this->ids['poster3615'], Mat4::identity(), 0.0, true, 'poster3615Tex');
        }
        if ($run->pulse > 0) {
            $size = (1 - $run->pulse / 0.25) * 4.2;
            $draw($this->ids['pulse'], Mat4::multiply(Mat4::translate($run->x, $run->y - 0.5, $run->z), Mat4::scale($size)));
        }
        foreach ($run->traces as $trace) {
            $start = Mat4::project($vp, [$trace['startX'], $trace['startY'], $trace['startZ']], $r->getWidth(), $r->getHeight());
            $end = Mat4::project($vp, [$trace['endX'], $trace['endY'], $trace['endZ']], $r->getWidth(), $r->getHeight());
            if ($start === null || $end === null || $start[2] < 0 || $start[2] > 1 || $end[2] < 0 || $end[2] > 1) continue;
            $color = match ($trace['type']) {
                'flame' => [255, 122, 45],
                'shuriken' => [125, 225, 255],
                'special' => [120, 240, 255],
                default => [255, 205, 105],
            };
            $r->drawLine((int)$start[0], (int)$start[1], (int)$end[0], (int)$end[1], $color);
        }
    }

    private function drawEnemySprites(Engine $engine, Renderer $r, Run $run, float $pitch): void
    {
        $this->visibleEnemies = $this->renderLosTests = 0;
        $this->enemyVisibility = array_intersect_key($this->enemyVisibility, $run->enemies);
        $sdl = $engine->sdl;
        foreach (EnemyAtlas::SHEETS as $sprite) {
            if (!$sdl->hasTexture($sprite['texture'])) $sdl->loadTexture($sprite['texture'], $sprite['path']);
        }

        $vp = self::camera($run, $r->getWidth() / max(1, $r->getHeight()), $pitch);
        $enemies = array_values($run->enemies);
        usort($enemies, static function (array $a, array $b) use ($run): int {
            $da = hypot($a['x'] - $run->x, $a['z'] - $run->z);
            $db = hypot($b['x'] - $run->x, $b['z'] - $run->z);
            return $db <=> $da;
        });

        foreach ($enemies as $enemy) {
            $distance = hypot($enemy['x'] - $run->x, $enemy['z'] - $run->z);
            if ($distance > 40.0) continue;
            $point = Mat4::project($vp, [$enemy['x'], $enemy['y'] + 0.10, $enemy['z']], $r->getWidth(), $r->getHeight());
            if ($point === null || $point[2] < 0 || $point[2] > 1) continue;
            if ($point[0] < -160 || $point[0] > $r->getWidth() + 160 || $point[1] < -160 || $point[1] > $r->getHeight() + 160) continue;
            if (!$this->enemyVisible($run, $enemy, $distance)) continue;
            $sprite = EnemyAtlas::SHEETS[$enemy['type']] ?? EnemyAtlas::SHEETS['guardian'];
            $state = $enemy['animState'] ?? 'walk';
            $facing = atan2($run->x - $enemy['x'], $run->z - $enemy['z']) - ($enemy['yaw'] ?? 0.0);
            $facing = fmod($facing, 2 * M_PI);
            if ($facing < 0) $facing += 2 * M_PI;
            $column = (int)round($facing / (2 * M_PI) * 8) % 8;
            $source = EnemyAtlas::frame($enemy['type'], $state, (int)floor(($enemy['age'] ?? 0.0) * 7.0), $column);
            if ($source === null || !EnemyAtlas::valid($sprite, $source)) continue;
            [$x, $y, $spriteW, $spriteH] = $this->spriteRect($enemy['type'], $sprite, $source, $point, $distance);
            $tex = $sdl->getTexture($sprite['texture']);
            $r->copy($tex['texture'], $source, $x, $y, $spriteW, $spriteH);
            $this->visibleEnemies++;
            if ($enemy['flash'] > 0) $r->drawRect($x - 1, $y - 1, $spriteW + 2, $spriteH + 2, [255, 105, 75, 190], 1);
        }
    }

    /**
     * Scale every crop with one pixel-to-world ratio for its enemy type.
     * Tight source rectangles are allowed to become shorter/wider with the pose,
     * but no longer get stretched back to a full standing height. The bottom edge
     * remains anchored where the old renderer put the enemy's feet.
     */
    private function spriteRect(string $type, array $sprite, array $source, array $point, float $distance): array
    {
        $referenceHeight = match ($type) {
            'runner' => 77.0,
            'guardian' => 63.0,
            'brute' => 87.0,
            'enforcer' => 63.0,
            default => 63.0,
        };
        $standingHeight = max(24.0, min(160.0, (480.0 / max(1.25, $distance)) * $sprite['scale']));
        $pixelScale = $standingHeight / $referenceHeight;
        $spriteW = max(8, (int)round($source[2] * $pixelScale));
        $spriteH = max(4, (int)round($source[3] * $pixelScale));
        $feetY = $point[1] + $standingHeight * 0.45;
        return [
            (int)round($point[0] - $spriteW / 2),
            (int)round($feetY - $spriteH),
            $spriteW,
            $spriteH,
        ];
    }

    /** Amortize brush/triangle LOS only for sprites that survived projection and screen culling. */
    private function enemyVisible(Run $run, array $enemy, float $distance): bool
    {
        $id = $enemy['id'];
        $cached = $this->enemyVisibility[$id] ?? null;
        $far = $distance > 18.0;
        $interval = $far ? 0.20 + ($id % 5) * 0.015 : 0.08 + ($id % 5) * 0.01;
        $movement = $far ? 0.38 + ($id % 3) * 0.025 : 0.28 + ($id % 5) * 0.025;
        if ($cached === null || $run->time - $cached['time'] >= $interval
            || hypot($run->x - $cached['playerX'], $run->z - $cached['playerZ']) > $movement
            || abs($run->y - $cached['playerY']) > 0.3
            || hypot($enemy['x'] - $cached['enemyX'], $enemy['z'] - $cached['enemyZ']) > $movement
            || abs($enemy['y'] - $cached['enemyY']) > 0.3) {
            $this->renderLosTests++;
            $visible = $this->enemyLineClear($run, self::eye($run), [$enemy['x'], $enemy['y'] + 0.15, $enemy['z']]);
            $this->enemyVisibility[$id] = [
                'visible' => $visible, 'time' => $run->time,
                'playerX' => $run->x, 'playerY' => $run->y, 'playerZ' => $run->z,
                'enemyX' => $enemy['x'], 'enemyY' => $enemy['y'], 'enemyZ' => $enemy['z'],
            ];
        }
        return $this->enemyVisibility[$id]['visible'];
    }

    /** Test sprite visibility against actual brush faces, not their coarse AABBs. */
    private function enemyLineClear(Run $run, array $a, array $b): bool
    {
        foreach ($run->map->solids as $solid) {
            $lo = 0.001; $hi = 0.999;
            for ($axis = 0; $axis < 3; $axis++) {
                $d = $b[$axis] - $a[$axis];
                if (abs($d) < 1e-8) {
                    if ($a[$axis] < $solid['min'][$axis] || $a[$axis] > $solid['max'][$axis]) { $lo = 2; break; }
                } else {
                    $t0 = ($solid['min'][$axis] - $a[$axis]) / $d;
                    $t1 = ($solid['max'][$axis] - $a[$axis]) / $d;
                    $lo = max($lo, min($t0, $t1)); $hi = min($hi, max($t0, $t1));
                }
                if ($lo > $hi) break;
            }
            if ($lo > $hi) continue;

            $triangles = $solid['triangles'] ?? [];
            if ($triangles === []) return false;
            for ($i = 0, $n = count($triangles); $i + 2 < $n; $i += 3) {
                if ($this->segmentHitsTriangle($a, $b, $triangles[$i]['p'], $triangles[$i + 1]['p'], $triangles[$i + 2]['p'])) return false;
            }
        }
        return true;
    }

    private function segmentHitsTriangle(array $a, array $b, array $v0, array $v1, array $v2): bool
    {
        $d = [$b[0] - $a[0], $b[1] - $a[1], $b[2] - $a[2]];
        $e1 = [$v1[0] - $v0[0], $v1[1] - $v0[1], $v1[2] - $v0[2]];
        $e2 = [$v2[0] - $v0[0], $v2[1] - $v0[1], $v2[2] - $v0[2]];
        $p = [$d[1] * $e2[2] - $d[2] * $e2[1], $d[2] * $e2[0] - $d[0] * $e2[2], $d[0] * $e2[1] - $d[1] * $e2[0]];
        $det = $e1[0] * $p[0] + $e1[1] * $p[1] + $e1[2] * $p[2];
        if (abs($det) < 1e-8) return false;
        $inv = 1.0 / $det;
        $tvec = [$a[0] - $v0[0], $a[1] - $v0[1], $a[2] - $v0[2]];
        $u = ($tvec[0] * $p[0] + $tvec[1] * $p[1] + $tvec[2] * $p[2]) * $inv;
        if ($u < 0.0 || $u > 1.0) return false;
        $q = [$tvec[1] * $e1[2] - $tvec[2] * $e1[1], $tvec[2] * $e1[0] - $tvec[0] * $e1[2], $tvec[0] * $e1[1] - $tvec[1] * $e1[0]];
        $v = ($d[0] * $q[0] + $d[1] * $q[1] + $d[2] * $q[2]) * $inv;
        if ($v < 0.0 || $u + $v > 1.0) return false;
        $t = ($e2[0] * $q[0] + $e2[1] * $q[1] + $e2[2] * $q[2]) * $inv;
        return $t > 0.001 && $t < 0.999;
    }

    private function drawPickupSprites(Engine $engine, Renderer $r, Run $run, float $pitch): void
    {
        if ($run->pickups === []) return;
        $sdl = $engine->sdl;
        foreach ([
            'health' => ['name' => 'pickup_health', 'path' => 'assets/sprites/pickups/health-pack.png', 'width' => 32, 'height' => 38],
            'xp' => ['name' => 'pickup_xp', 'path' => 'assets/sprites/pickups/xp-orb.png', 'width' => 29, 'height' => 36],
        ] as $kind => $sprite) {
            if (!$sdl->hasTexture($sprite['name'])) $sdl->loadTexture($sprite['name'], $sprite['path']);
        }
        $vp = self::camera($run, $r->getWidth() / max(1, $r->getHeight()), $pitch);
        foreach ($run->pickups as $pickup) {
            $point3d = [$pickup['x'], $pickup['y'] + sin($run->time * 4) * 0.06 + 0.18, $pickup['z']];
            if (!$run->lineClear(self::eye($run), $point3d)) continue;
            $point = Mat4::project($vp, $point3d, $r->getWidth(), $r->getHeight());
            if ($point === null || $point[2] < 0 || $point[2] > 1) continue;
            $sprite = $pickup['kind'] === 'health'
                ? ['name' => 'pickup_health', 'width' => 32, 'height' => 38]
                : ['name' => 'pickup_xp', 'width' => 29, 'height' => 36];
            $distance = hypot($pickup['x'] - $run->x, $pickup['z'] - $run->z);
            $height = (int)max(14, min(34, 170.0 / max(1.25, $distance)));
            $width = (int)max(12, round($height * $sprite['width'] / $sprite['height']));
            $texture = $sdl->getTexture($sprite['name']);
            $r->copy($texture['texture'], [0, 0, $sprite['width'], $sprite['height']],
                (int)round($point[0] - $width / 2), (int)round($point[1] - $height / 2), $width, $height);
        }
    }

    private function drawImpactSprites(Engine $engine, Renderer $r, Run $run, float $pitch): void
    {
        if ($run->traces === []) return;
        $sdl = $engine->sdl;
        if (!$sdl->hasTexture('hit_spark')) $sdl->loadTexture('hit_spark', 'assets/sprites/effects/spark-impact.png');
        $texture = $sdl->getTexture('hit_spark');
        $vp = self::camera($run, $r->getWidth() / max(1, $r->getHeight()), $pitch);
        foreach (array_slice($run->traces, -self::MAX_IMPACTS) as $trace) {
            $point = Mat4::project($vp, [$trace['x'], $trace['y'], $trace['z']], $r->getWidth(), $r->getHeight());
            if ($point === null || $point[2] < 0 || $point[2] > 1) continue;
            $distance = hypot($trace['x'] - $run->x, $trace['z'] - $run->z);
            $duration = max(1 / 60, (float)($trace['totalLife'] ?? 0.09));
            $elapsed = max(0.0, $duration - (float)$trace['life']);
            $frame = min(8, (int)floor($elapsed / $duration * 9));
            $size = (int)max(18, min(46, 150.0 / max(1.25, $distance)));
            $r->copy($texture['texture'], [$frame * 32, 0, 32, 32],
                (int)round($point[0] - $size / 2), (int)round($point[1] - $size / 2), $size, $size);
        }
    }

    private function drawDeathSprites(Engine $engine, Renderer $r, Run $run, float $pitch): void
    {
        if ($run->corpses === []) return;
        $sdl = $engine->sdl;
        $vp = self::camera($run, $r->getWidth() / max(1, $r->getHeight()), $pitch);
        foreach ($run->corpses as $corpse) {
            $sprite = EnemyAtlas::SHEETS[$corpse['type']] ?? EnemyAtlas::SHEETS['guardian'];
            $distance = hypot($corpse['x'] - $run->x, $corpse['z'] - $run->z);
            if ($distance > 40.0) continue;
            $point = Mat4::project($vp, [$corpse['x'], $corpse['y'] + 0.1, $corpse['z']], $r->getWidth(), $r->getHeight());
            if ($point === null || $point[2] < 0 || $point[2] > 1) continue;
            if ($point[0] < -160 || $point[0] > $r->getWidth() + 160 || $point[1] < -160 || $point[1] > $r->getHeight() + 160) continue;
            if (!$run->lineClear(self::eye($run), [$corpse['x'], $corpse['y'] + 0.1, $corpse['z']])) continue;
            $deathFrames = count($sprite['frames'][$sprite['death']]);
            $frame = min($deathFrames - 1, (int)floor($corpse['age'] / 0.64 * $deathFrames));
            $source = EnemyAtlas::frame($corpse['type'], 'death', $frame, 0);
            if ($source === null || !EnemyAtlas::valid($sprite, $source)) continue;
            [$x, $y, $spriteW, $spriteH] = $this->spriteRect($corpse['type'], $sprite, $source, $point, $distance);
            $texture = $sdl->getTexture($sprite['texture']);
            $r->copy($texture['texture'], $source, $x, $y, $spriteW, $spriteH);
        }
    }

    /** Detect health deltas at render time so every damage source gets feedback without duplicating simulation events. */
    private function captureDamagePopups(Run $run): void
    {
        $current = [];
        foreach ($run->enemies as $enemy) $current[(int)$enemy['id']] = $enemy;
        foreach ($run->corpses as $corpse) $current[(int)$corpse['id']] = $corpse;

        foreach ($current as $id => $enemy) {
            $health = (int)$enemy['health'];
            if (isset($this->lastEnemyHealth[$id]) && $health < $this->lastEnemyHealth[$id]) {
                $amount = $this->lastEnemyHealth[$id] - $health;
                if ($amount > 0) {
                    if (count($this->damagePopups) >= self::MAX_DAMAGE_POPUPS) array_shift($this->damagePopups);
                    $this->damagePopups[] = [
                        'x' => (float)$enemy['x'], 'y' => (float)$enemy['y'] + 0.72, 'z' => (float)$enemy['z'],
                        'amount' => $amount, 'born' => $run->time,
                    ];
                }
            }
            $this->lastEnemyHealth[$id] = $health;
        }

        foreach (array_keys($this->lastEnemyHealth) as $id) {
            if (!isset($current[$id])) unset($this->lastEnemyHealth[$id]);
        }
        $this->damagePopups = array_values(array_filter(
            $this->damagePopups,
            static fn (array $popup): bool => $run->time - $popup['born'] < 0.72,
        ));
    }

    private function drawDamagePopups(Renderer $r, Run $run, float $pitch): void
    {
        if ($this->damagePopups === []) return;
        $vp = self::camera($run, $r->getWidth() / max(1, $r->getHeight()), $pitch);
        $scale = max(1, min(2, (int)round($r->getHeight() / 180.0)));
        foreach ($this->damagePopups as $popup) {
            $age = max(0.0, $run->time - $popup['born']);
            $progress = min(1.0, $age / 0.72);
            $point = Mat4::project(
                $vp,
                [$popup['x'], $popup['y'] + $progress * 0.38, $popup['z']],
                $r->getWidth(),
                $r->getHeight(),
            );
            if ($point === null || $point[2] < 0 || $point[2] > 1) continue;
            if ($point[0] < -40 || $point[0] > $r->getWidth() + 40 || $point[1] < -20 || $point[1] > $r->getHeight() + 20) continue;
            $alpha = (int)round(255 * (1.0 - $progress));
            $text = '-' . $popup['amount'];
            $width = $this->miniTextWidth($text, $scale);
            $this->drawMiniText($r, $text, (int)round($point[0] - $width / 2), (int)round($point[1] - 8 * $scale), $scale, [255, 214, 92, $alpha]);
        }
    }

    private function miniTextWidth(string $text, int $scale): int
    {
        return max(0, strlen($text) * 4 * $scale - $scale);
    }

    /** Tiny 3×5 numeric font: legible at 320×180 and scales to higher logical resolutions. */
    private function drawMiniText(Renderer $r, string $text, int $x, int $y, int $scale, array $color): void
    {
        $glyphs = [
            '-' => ['000','000','111','000','000'],
            '0' => ['111','101','101','101','111'],
            '1' => ['010','110','010','010','111'],
            '2' => ['111','001','111','100','111'],
            '3' => ['111','001','111','001','111'],
            '4' => ['101','101','111','001','001'],
            '5' => ['111','100','111','001','111'],
            '6' => ['111','100','111','101','111'],
            '7' => ['111','001','010','010','010'],
            '8' => ['111','101','111','101','111'],
            '9' => ['111','101','111','001','111'],
        ];
        $cursor = $x;
        foreach (str_split($text) as $char) {
            $rows = $glyphs[$char] ?? $glyphs['0'];
            foreach ($rows as $row => $bits) {
                for ($col = 0; $col < 3; $col++) {
                    if ($bits[$col] === '1') $r->fillRect($cursor + $col * $scale, $y + $row * $scale, $scale, $scale, $color);
                }
            }
            $cursor += 4 * $scale;
        }
    }

    private function drawFallback(Renderer $r, Run $run): void
    {
        $r->clear([18, 14, 22]);
        $scale = 5.6; $cx = $r->getWidth() / 2; $cy = $r->getHeight() / 2;
        foreach ($run->map->solids as $solid) {
            [$x0, , $z0] = $solid['min']; [$x1, , $z1] = $solid['max'];
            $r->fillRect((int)round($cx + $x0 * $scale), (int)round($cy + $z0 * $scale),
                max(1, (int)round(($x1 - $x0) * $scale)), max(1, (int)round(($z1 - $z0) * $scale)), $solid['color']);
        }
        $px = (int)round($cx + $run->x * $scale); $py = (int)round($cy + $run->z * $scale);
        $r->fillCircle($px, $py, 3, [235,220,160]);
        foreach ($run->enemies as $enemy) {
            $r->fillCircle((int)round($cx + $enemy['x'] * $scale), (int)round($cy + $enemy['z'] * $scale),
                $enemy['type'] === 'brute' ? 4 : 2, $enemy['type'] === 'runner' ? [80,210,140] : [210,70,80]);
        }
        foreach ($run->pickups as $pickup) {
            $r->fillCircle((int)round($cx + $pickup['x'] * $scale), (int)round($cy + $pickup['z'] * $scale), 1,
                $pickup['kind'] === 'health' ? [250,70,80] : [80,235,235]);
        }
        $r->drawLine($px, $py, (int)round($px + sin($run->yaw) * 10), (int)round($py + cos($run->yaw) * 10), [255,240,180]);
    }
}
