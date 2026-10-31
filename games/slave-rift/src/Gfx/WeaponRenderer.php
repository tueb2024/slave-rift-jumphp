<?php

namespace App\Gfx;

use App\Game\Run;
use JumPHP\Renderer\Renderer;

/**
 * Resolution-aware first-person weapon renderer.
 *
 * The simulation owns cadence; this class only turns shot pulses into a short
 * visual state cycle. Existing atlases remain 4 × 144×96 frames.
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

    public function textureKey(int $stage): string
    {
        return match (true) {
            $stage <= 1 => 'weapon_colt',
            $stage <= 3 => 'weapon_mp44',
            $stage === 4 => 'weapon_bar',
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
                $age < 0.028 => self::FIRE,
                $age < 0.070 => self::RECOIL,
                $age < 0.125 => self::COOLDOWN,
                default => self::READY,
            };
        }
        $this->lastMuzzle = $muzzle;
        return $this->state;
    }

    public function frameForState(string $state): int
    {
        return match ($state) {
            self::FIRE => 1,
            self::RECOIL => 2,
            self::COOLDOWN => 3,
            default => 0,
        };
    }

    /** @return array{drawW:int,drawH:int,y:int,copies:int,spread:int} */
    public function layout(int $width, int $height, int $stage, float $time): array
    {
        $drawH = (int)round($height * ($stage <= 1 ? 0.27 : 0.29));
        $drawH = max(24, min((int)round($height * 0.40), $drawH));
        $drawW = (int)round($drawH * 1.5);
        $bob = (int)round(sin($time * 6.0) * $height * 0.004);
        $copies = in_array($stage, [1, 3, 6], true) ? 2 : 1;
        return [
            'drawW' => $drawW,
            'drawH' => $drawH,
            'y' => $height - $drawH + $bob,
            'copies' => $copies,
            'spread' => (int)round($drawW * 0.20),
        ];
    }

    /** @param array{texture:mixed} $texture */
    public function render(Renderer $r, Run $run, array $texture): void
    {
        $state = $this->update($run->time, $run->muzzle);
        $frame = $this->frameForState($state);
        $layout = $this->layout($r->getWidth(), $r->getHeight(), $run->primaryStage, $run->time);
        $cx = intdiv($r->getWidth(), 2);
        for ($gun = 0; $gun < $layout['copies']; $gun++) {
            $offset = (int)round(($gun - ($layout['copies'] - 1) / 2) * $layout['spread']);
            $x = (int)round($cx - $layout['drawW'] * 0.18 + $offset);
            $r->copy(
                $texture['texture'],
                [$frame * self::FRAME_W, 0, self::FRAME_W, self::FRAME_H],
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
