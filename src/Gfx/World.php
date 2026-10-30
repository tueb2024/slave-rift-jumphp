<?php

namespace App\Gfx;

use App\Game\Run;
use JumPHP\Gl\Mat4;
use JumPHP\Gl\WebGl;
use JumPHP\Kernel\Engine;
use JumPHP\Renderer\Renderer;

/** One persistent world buffer plus one reusable guardian mesh. */
final class World
{
    public const KEY = [255, 0, 255];

    /** @var array<string,int> */
    private array $ids = [];

    public function prepare(WebGl $gl, Run $run): void
    {
        if (!$gl->isAvailable()) {
            return;
        }
        if ($this->ids === []) {
            $this->ids = [
                'program' => $gl->program(Meshes::VERTEX, Meshes::FRAGMENT),
                'world' => $gl->buffer(Meshes::world($run->map), true),
                'guardian' => $gl->buffer(Meshes::guardian(), true),
                'worldTex' => $gl->texture('assets/textures/quadcompati/cop1_1.png', false, true),
            ];
        }
        $gl->setLayer('below', 'native', self::KEY);
        $gl->setSupersample(2);
    }

    /** @return float[] */
    public static function eye(Run $run): array
    {
        return [$run->x, $run->y + 0.66, $run->z];
    }

    /** @return float[] */
    public static function camera(Run $run, float $aspect): array
    {
        $eye = self::eye($run);
        $target = [
            $eye[0] + sin($run->yaw),
            $eye[1],
            $eye[2] + cos($run->yaw),
        ];
        return Mat4::multiply(
            Mat4::perspective(67.0, $aspect, 0.08, 32.0),
            Mat4::lookAt($eye, $target),
        );
    }

    public function render(Engine $engine, Renderer $r, Run $run): void
    {
        $gl = $engine->gl();
        if ($gl->isAvailable() && $this->ids !== []) {
            $r->clear(self::KEY);
            $this->draw3d($gl, $r, $run);
            return;
        }
        $this->drawFallback($r, $run);
    }

    private function draw3d(WebGl $gl, Renderer $r, Run $run): void
    {
        $fog = [0.075, 0.055, 0.085];
        $gl->clear([18, 13, 22]);
        $vp = self::camera($run, $r->getWidth() / max(1, $r->getHeight()));
        $common = [
            'u_viewProj' => $vp,
            'u_eye' => self::eye($run),
            'u_fog' => $fog,
        ];
        $draw = function (int $buffer, array $model, float $flash = 0.0, bool $textured = false) use ($gl, $common): void {
            $gl->draw($this->ids['program'], [
                'attributes' => [
                    'a_pos' => [$buffer, 3, Meshes::STRIDE, 0],
                    'a_color' => [$buffer, 3, Meshes::STRIDE, 3],
                    'a_normal' => [$buffer, 3, Meshes::STRIDE, 6],
                    'a_uv' => [$buffer, 2, Meshes::STRIDE, 9],
                ],
                'uniforms' => $common + [
                    'u_model' => $model,
                    'u_flash' => $flash,
                    'u_useTex' => $textured ? 1.0 : 0.0,
                ],
                'textures' => [
                    'u_tex' => $this->ids['worldTex'],
                ],
            ]);
        };

        $draw($this->ids['world'], Mat4::identity(), 0.0, true);
        if ($run->guardian['health'] > 0) {
            $draw(
                $this->ids['guardian'],
                Mat4::translate($run->guardian['x'], $run->guardian['y'], $run->guardian['z']),
                $run->guardian['flash'] > 0 ? 1.0 : 0.0,
                false,
            );
        }
    }

    /** Diagnostic fallback: top-down map so headless/software screenshots stay informative. */
    private function drawFallback(Renderer $r, Run $run): void
    {
        $r->clear([18, 14, 22]);
        $scale = 9.0;
        $cx = $r->getWidth() / 2;
        $cy = $r->getHeight() / 2;
        foreach ($run->map->solids as $solid) {
            [$x0, , $z0] = $solid['min'];
            [$x1, , $z1] = $solid['max'];
            $sx = (int)round($cx + $x0 * $scale);
            $sy = (int)round($cy + $z0 * $scale);
            $sw = max(1, (int)round(($x1 - $x0) * $scale));
            $sh = max(1, (int)round(($z1 - $z0) * $scale));
            $r->fillRect($sx, $sy, $sw, $sh, $solid['color']);
        }
        $px = (int)round($cx + $run->x * $scale);
        $py = (int)round($cy + $run->z * $scale);
        $gx = (int)round($cx + $run->guardian['x'] * $scale);
        $gy = (int)round($cy + $run->guardian['z'] * $scale);
        $r->fillCircle($px, $py, 3, [235, 220, 160]);
        if ($run->guardian['health'] > 0) {
            $r->fillCircle($gx, $gy, 4, [190, 58, 52]);
        }
        $r->drawLine($px, $py, (int)round($px + sin($run->yaw) * 10), (int)round($py + cos($run->yaw) * 10), [255, 240, 180]);
    }
}
