<?php

namespace App\Gfx;

use App\Game\Run;
use JumPHP\Renderer\Renderer;

/**
 * Resolution-aware first-person weapon renderer.
 *
 * Each primary weapon owns a 576x96 atlas made of four 144x96 FPS frames:
 * ready, cooldown/return, fire, recoil. The weapon is always rendered; firing
 * only changes the selected frame and applies a tiny recoil displacement.
 */
final class WeaponRenderer
{
    public const READY = 'ready';
    public const FIRE = 'fire';
    public const RECOIL = 'recoil';
    public const COOLDOWN = 'cooldown';
    public const FRAME_W = 144;
    public const FRAME_H = 96;

    private string $state = self::READY;
    private float $stateStarted = 0.0;
    private float $lastMuzzle = 0.0;

    public function reset(float $time = 0.0): void
    {
        $this->state = self::READY;
        $this->stateStarted = $time;
        $this->lastMuzzle = 0.0;
    }

    /** Four visible primary weapons over the seven simulation stages. */
    public function textureKey(int $stage): string
    {
        return match (true) {
            $stage <= 1 => 'weapon_colt',
            $stage <= 3 => 'weapon_mp44',
            $stage <= 4 => 'weapon_bar',
            default => 'weapon_gatling',
        };
    }

    public function update(float $time, float $muzzle): string
    {
        if ($muzzle > $this->lastMuzzle + 0.015) {
            $this->state = self::FIRE;
            $this->stateStarted = $time;
        } else {
            $age = max(0.0, $time - $this->stateStarted);
            $this->state = match (true) {
                $age < 0.034 => self::FIRE,
                $age < 0.078 => self::RECOIL,
                $age < 0.132 => self::COOLDOWN,
                default => self::READY,
            };
        }
        $this->lastMuzzle = $muzzle;
        return $this->state;
    }

    public function frameForState(string $state): int
    {
        return match ($state) {
            self::FIRE => 2,
            self::RECOIL => 3,
            self::COOLDOWN => 1,
            default => 0,
        };
    }

    /** Atlases are one row high; kept for compatibility with older callers/tests. */
    public function rowForStage(int $stage): int
    {
        return 0;
    }

    /** @return array{drawW:int,drawH:int,x:int,y:int,copies:int,spread:int} */
    public function layout(int $width, int $height, int $stage, float $time): array
    {
        $dual = in_array($stage, [1, 3, 6], true);
        $ratio = match ($stage) {
            1 => 0.46,
            3 => 0.48,
            6 => 0.52,
            default => match (true) {
                $stage <= 1 => 0.54,
                $stage <= 3 => 0.58,
                $stage <= 4 => 0.61,
                default => 0.64,
            },
        };
        $drawH = max(72, min((int)round($height * 0.70), (int)round($height * $ratio)));
        $drawW = (int)round($drawH * self::FRAME_W / self::FRAME_H);
        $bob = (int)round((sin($time * 6.0) + 1.0) * 0.8);
        $spread = $dual ? max(24, (int)round($drawW * 0.82)) : 0;
        $x = $dual
            ? (int)round($width / 2 - $drawW / 2 - $spread / 2)
            : (int)round($width / 2 - $drawW * 0.46);
        $x = max(0, min($width - $drawW - $spread, $x));
        $y = $height - $drawH - 2 - $bob;

        return [
            'drawW' => $drawW,
            'drawH' => $drawH,
            'x' => $x,
            'y' => max(0, min($height - $drawH, $y)),
            'copies' => $dual ? 2 : 1,
            'spread' => $spread,
        ];
    }

    /** @param array{texture:mixed} $texture */
    public function render(Renderer $r, Run $run, array $texture): void
    {
        $state = $this->update($run->time, $run->muzzle);
        $frame = $this->frameForState($state);
        $layout = $this->layout($r->getWidth(), $r->getHeight(), $run->primaryStage, $run->time);

        $recoilY = match ($state) {
            self::FIRE => -3,
            self::RECOIL => -6,
            self::COOLDOWN => -2,
            default => 0,
        };
        $y = max(0, min($r->getHeight() - $layout['drawH'], $layout['y'] + $recoilY));
        $src = [$frame * self::FRAME_W, 0, self::FRAME_W, self::FRAME_H];

        if ($layout['copies'] === 2) {
            $r->copy(
                $texture['texture'],
                $src,
                $layout['x'],
                $y,
                $layout['drawW'],
                $layout['drawH'],
                true,
            );
            $r->copy(
                $texture['texture'],
                $src,
                $layout['x'] + $layout['spread'],
                $y,
                $layout['drawW'],
                $layout['drawH'],
            );
            return;
        }

        $r->copy(
            $texture['texture'],
            $src,
            $layout['x'],
            $y,
            $layout['drawW'],
            $layout['drawH'],
        );
    }

    public function state(): string
    {
        return $this->state;
    }
}
