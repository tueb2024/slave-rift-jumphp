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

    /** Sprite sheets by gameplay role. Rows are animation frames; columns are camera-relative directions. */
    private const ENEMY_SPRITES = [
        'runner' => [
            'texture' => 'enemy_mutant_grunt', 'path' => 'assets/sprites/enemies/nmn-cc0/mutant-grunt.png',
            'width' => 814, 'height' => 1227, 'directions' => 8, 'rows' => 12, 'scale' => 0.98,
            'clips' => ['idle' => [0], 'walk' => [0, 1, 2, 3], 'attack' => [5, 6, 7], 'pain' => [9]],
            'deathRow' => 11, 'deathFrames' => 7,
        ],
        'guardian' => [
            'texture' => 'enemy_ratman', 'path' => 'assets/sprites/enemies/nmn-cc0/ratman.png',
            'width' => 592, 'height' => 658, 'directions' => 8, 'rows' => 9, 'scale' => 0.98,
            'clips' => ['idle' => [0], 'walk' => [0, 1, 2, 3], 'attack' => [4, 5, 6], 'pain' => [7]],
            'deathRow' => 8, 'deathFrames' => 6,
        ],
        'brute' => [
            'texture' => 'enemy_mutant_leader', 'path' => 'assets/sprites/enemies/nmn-cc0/mutant-leader.png',
            'width' => 889, 'height' => 1493, 'directions' => 8, 'rows' => 12, 'scale' => 1.42,
            'clips' => ['idle' => [0], 'walk' => [0, 1, 2, 3], 'attack' => [5, 6, 7], 'pain' => [9]],
            'deathRow' => 11, 'deathFrames' => 6,
        ],
    ];

    /** @var array<string,int> */
    private array $ids = [];

    public function prepare(WebGl $gl, Run $run): void
    {
        if (!$gl->isAvailable()) return;
        if ($this->ids === []) {
            $materialMap = function (string $material) use ($run): BrushMap {
                $m = new BrushMap();
                $m->solids = array_values(array_filter($run->map->solids, static function (array $solid) use ($material): bool {
                    return strtolower((string)($solid['material'] ?? 'stone')) === $material;
                }));
                return $m;
            };
            $this->ids = [
                'program' => $gl->program(Meshes::VERTEX, Meshes::FRAGMENT),
                'worldStone' => $gl->buffer(Meshes::world($materialMap('stone')), true),
                'worldMetal' => $gl->buffer(Meshes::world($materialMap('metal')), true),
                'worldCeil' => $gl->buffer(Meshes::world($materialMap('ceil')), true),
                'worldCrate' => $gl->buffer(Meshes::world($materialMap('crate')), true),
                'guardian' => $gl->buffer(Meshes::enemy('guardian'), true),
                'runner' => $gl->buffer(Meshes::enemy('runner'), true),
                'brute' => $gl->buffer(Meshes::enemy('brute'), true),
                'xp' => $gl->buffer(Meshes::pickup(false), true),
                'health' => $gl->buffer(Meshes::pickup(true), true),
                'pulse' => $gl->buffer(Meshes::pulse(), true),
                'stoneTex' => $gl->texture('assets/textures/rift-stone.png', false, true),
                'metalTex' => $gl->texture('assets/textures/quadcompati/metal_panel.png', false, true),
                'ceilTex' => $gl->texture('assets/textures/quadcompati/ceil1_1.png', false, true),
                'crateTex' => $gl->texture('assets/textures/quadcompati/crate0_side.png', false, true),
            ];
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
        $gl = $engine->gl();
        if ($gl->isAvailable() && $this->ids !== []) {
            $r->clear(self::KEY);
            $this->draw3d($gl, $r, $run, $pitch);
            $this->drawEnemySprites($engine, $r, $run, $pitch);
            $this->drawDeathSprites($engine, $r, $run, $pitch);
            $this->drawImpactSprites($engine, $r, $run, $pitch);
            return;
        }
        $this->drawFallback($r, $run);
    }

    private function draw3d(WebGl $gl, Renderer $r, Run $run, float $pitch): void
    {
        $fog = [0.075, 0.055, 0.085];
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
        // Enemies are drawn as alpha billboards in the native overlay.
        foreach ($run->pickups as $pickup) {
            $draw($this->ids[$pickup['kind']], Mat4::multiply(
                Mat4::translate($pickup['x'], $pickup['y'] + sin($run->time * 4) * 0.06, $pickup['z']),
                Mat4::rotateY($run->time * 2),
            ));
        }
        if ($run->pulse > 0) {
            $size = (1 - $run->pulse / 0.25) * 4.2;
            $draw($this->ids['pulse'], Mat4::multiply(Mat4::translate($run->x, $run->y - 0.5, $run->z), Mat4::scale($size)));
        }
        foreach ($run->traces as $trace) {
            $point = Mat4::project($vp, [$trace['x'], $trace['y'], $trace['z']], $r->getWidth(), $r->getHeight());
            if ($point !== null && $point[2] >= 0 && $point[2] <= 1) {
                $r->drawLine(intdiv($r->getWidth(), 2), intdiv($r->getHeight(), 2), (int)$point[0], (int)$point[1], [255,210,100]);
            }
        }
    }

    private function drawEnemySprites(Engine $engine, Renderer $r, Run $run, float $pitch): void
    {
        $sdl = $engine->sdl;
        foreach (self::ENEMY_SPRITES as $sprite) {
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
            if (!$run->lineClear(self::eye($run), [$enemy['x'], $enemy['y'] + 0.15, $enemy['z']])) continue;
            $point = Mat4::project($vp, [$enemy['x'], $enemy['y'] + 0.10, $enemy['z']], $r->getWidth(), $r->getHeight());
            if ($point === null || $point[2] < 0 || $point[2] > 1) continue;
            $sprite = self::ENEMY_SPRITES[$enemy['type']] ?? self::ENEMY_SPRITES['guardian'];
            $state = $enemy['animState'] ?? 'walk';
            $clip = $sprite['clips'][$state] ?? $sprite['clips']['walk'];
            $row = $clip[(int)floor(($enemy['age'] ?? 0.0) * 7.0) % count($clip)];
            $facing = atan2($run->x - $enemy['x'], $run->z - $enemy['z']) - ($enemy['yaw'] ?? 0.0);
            $facing = fmod($facing, 2 * M_PI);
            if ($facing < 0) $facing += 2 * M_PI;
            $column = (int)round($facing / (2 * M_PI) * $sprite['directions']) % $sprite['directions'];
            $cellW = $sprite['width'] / $sprite['directions'];
            $cellH = $sprite['height'] / $sprite['rows'];
            $sourceX = (int)floor($column * $cellW);
            $sourceY = (int)floor($row * $cellH);
            $sourceW = (int)floor(($column + 1) * $cellW) - $sourceX;
            $sourceH = (int)floor(($row + 1) * $cellH) - $sourceY;
            $spriteH = (int)max(18, min(126, (205.0 / max(1.25, $distance)) * $sprite['scale']));
            $spriteW = (int)max(12, round($spriteH * $cellW / $cellH));
            $x = (int)round($point[0] - $spriteW / 2);
            $y = (int)round($point[1] - $spriteH * 0.55);
            $tex = $sdl->getTexture($sprite['texture']);
            $r->copy($tex['texture'], [$sourceX, $sourceY, $sourceW, $sourceH], $x, $y, $spriteW, $spriteH);
            if ($enemy['flash'] > 0) $r->drawRect($x - 1, $y - 1, $spriteW + 2, $spriteH + 2, [255, 105, 75, 190], 1);
        }
    }

    private function drawImpactSprites(Engine $engine, Renderer $r, Run $run, float $pitch): void
    {
        if ($run->traces === []) return;
        $sdl = $engine->sdl;
        if (!$sdl->hasTexture('hit_spark')) $sdl->loadTexture('hit_spark', 'assets/sprites/effects/spark-impact.png');
        $texture = $sdl->getTexture('hit_spark');
        $vp = self::camera($run, $r->getWidth() / max(1, $r->getHeight()), $pitch);
        foreach ($run->traces as $trace) {
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
            $sprite = self::ENEMY_SPRITES[$corpse['type']] ?? self::ENEMY_SPRITES['guardian'];
            $distance = hypot($corpse['x'] - $run->x, $corpse['z'] - $run->z);
            if ($distance > 40.0 || !$run->lineClear(self::eye($run), [$corpse['x'], $corpse['y'] + 0.1, $corpse['z']])) continue;
            $point = Mat4::project($vp, [$corpse['x'], $corpse['y'] + 0.1, $corpse['z']], $r->getWidth(), $r->getHeight());
            if ($point === null || $point[2] < 0 || $point[2] > 1) continue;
            $cellW = $sprite['width'] / $sprite['directions'];
            $cellH = $sprite['height'] / $sprite['rows'];
            $frame = min($sprite['deathFrames'] - 1, (int)floor($corpse['age'] / 0.64 * $sprite['deathFrames']));
            $sourceX = (int)floor($frame * $cellW);
            $sourceY = (int)floor($sprite['deathRow'] * $cellH);
            $sourceW = (int)floor(($frame + 1) * $cellW) - $sourceX;
            $sourceH = (int)floor(($sprite['deathRow'] + 1) * $cellH) - $sourceY;
            $spriteH = (int)max(18, min(126, (205.0 / max(1.25, $distance)) * $sprite['scale']));
            $spriteW = (int)max(12, round($spriteH * $cellW / $cellH));
            $x = (int)round($point[0] - $spriteW / 2);
            $y = (int)round($point[1] - $spriteH * 0.55);
            $texture = $sdl->getTexture($sprite['texture']);
            $r->copy($texture['texture'], [$sourceX, $sourceY, $sourceW, $sourceH], $x, $y, $spriteW, $spriteH);
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
