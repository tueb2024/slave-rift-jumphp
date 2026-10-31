<?php

namespace App\Gfx;

use App\Game\Run;
use JumPHP\Renderer\Renderer;

/**
 * Resolution-aware first-person weapon renderer.
 *
 * The visual source is the CC0 FPS overlay already vendored in the repository:
 * five 64x64 animation frames per row (knife, pistol, machine gun, chain gun).
 * Cadence remains simulation-owned; this renderer only selects the visual frame.
 */
final class WeaponRenderer
{
    public const READY = 'ready';
    public const FIRE = 'fire';
    public const RECOIL = 'recoil';
    public const COOLDOWN = 'cooldown';
    public const FRAME_W = 64;
    public const FRAME_H = 64;

    private string $state = self::READY;
    private float $stateStarted = 0.0;
    private float $lastMuzzle = 0.0;

    public function reset(float $time = 0.0): void
    {
        $this->state = self::READY;
        $this->stateStarted = $time;
        $this->lastMuzzle = 0.0;
    }

    /** All primary stages use the same real first-person CC0 sheet. */
    public function textureKey(int $stage): string
    {
        return 'weapon_colt';
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

    /** Sheet row: 1 pistol, 2 machine gun, 3 chain gun. */
    public function rowForStage(int $stage): int
    {
        return match (true) {
            $stage <= 1 => 1,
            $stage <= 4 => 2,
            default => 3,
        };
    }

    /** @return array{drawW:int,drawH:int,y:int,copies:int,spread:int} */
    public function layout(int $width, int $height, int $stage, float $time): array
    {
        $ratio = match (true) {
            $stage <= 1 => 0.58,
            $stage <= 4 => 0.64,
            default => 0.70,
        };
        $drawH = (int)round($height * $ratio);
        $drawH = max(58, min((int)round($height * 0.76), $drawH));
        $drawW = $drawH;
        $bob = (int)round(sin($time * 6.0) * $height * 0.006);
        $copies = in_array($stage, [1, 3, 6], true) ? 2 : 1;
        return [
            'drawW' => $drawW,
            'drawH' => $drawH,
            'y' => $height - $drawH + (int)round($height * 0.10) + $bob,
            'copies' => $copies,
            'spread' => (int)round($drawW * 0.40),
        ];
    }

    /** @param array{texture:mixed} $texture */
    public function render(Renderer $r, Run $run, array $texture): void
    {
        $state = $this->update($run->time, $run->muzzle);
        $frame = $this->frameForState($state);
        $row = $this->rowForStage($run->primaryStage);
        $layout = $this->layout($r->getWidth(), $r->getHeight(), $run->primaryStage, $run->time);
        $cx = intdiv($r->getWidth(), 2);

        for ($gun = 0; $gun < $layout['copies']; $gun++) {
            $offset = (int)round(($gun - ($layout['copies'] - 1) / 2) * $layout['spread']);
            $x = (int)round($cx - $layout['drawW'] / 2 + $offset);
            $r->copy(
                $texture['texture'],
                [$frame * self::FRAME_W, $row * self::FRAME_H, self::FRAME_W, self::FRAME_H],
                $x,
                $layout['y'],
                $layout['drawW'],
                $layout['drawH'],
            );
        }
    }

    public function state(): string
    {
        return $this->state;
    }
}
