<?php

namespace App\Game;

/** Pure simulation: movement, collision, jumping, one low-poly enemy and hitscan fire. */
final class Run
{
    public const PLAYER_RADIUS = 0.28;
    public const PLAYER_HALF_HEIGHT = 0.70;
    public const MOVE_SPEED = 3.1;
    public const TURN_SPEED = 2.35;
    public const JUMP_SPEED = 5.2;
    public const GRAVITY = 13.5;

    public float $x;
    public float $y;
    public float $z;
    public float $yaw;
    public float $vy = 0.0;
    public bool $grounded = false;
    public int $health = 100;
    public int $shots = 0;
    public int $hits = 0;
    public float $muzzle = 0.0;
    public float $time = 0.0;
    public string $state = 'play';

    /** @var array{x:float,y:float,z:float,health:int,cooldown:float,flash:float} */
    public array $guardian;

    public function __construct(public readonly BrushMap $map)
    {
        [$this->x, $this->y, $this->z] = $map->playerStart;
        $this->yaw = $map->playerYaw;
        [$gx, $gy, $gz] = $map->guardianStart;
        $this->guardian = ['x' => $gx, 'y' => $gy, 'z' => $gz, 'health' => 80, 'cooldown' => 0.0, 'flash' => 0.0];
    }

    public function step(float $dt, float $forward, float $strafe, float $turn, bool $fire, bool $jump = false): void
    {
        $dt = min($dt, 0.05);
        $this->time += $dt;
        $this->muzzle = max(0.0, $this->muzzle - $dt);
        $this->guardian['flash'] = max(0.0, $this->guardian['flash'] - $dt);

        if ($this->state !== 'play') return;

        if ($this->grounded && !$this->collides($this->x, $this->y - 0.04, $this->z, self::PLAYER_RADIUS, self::PLAYER_HALF_HEIGHT)) {
            $this->grounded = false;
        }
        if ($jump && $this->grounded) {
            $this->vy = self::JUMP_SPEED;
            $this->grounded = false;
        }
        if (!$this->grounded) {
            $this->vy -= self::GRAVITY * $dt;
            $this->movePlayerVertical($this->vy * $dt);
        }

        $this->yaw += $turn * self::TURN_SPEED * $dt;
        $fx = sin($this->yaw);
        $fz = cos($this->yaw);
        $rx = cos($this->yaw);
        $rz = -sin($this->yaw);
        $dx = ($fx * $forward + $rx * $strafe) * self::MOVE_SPEED * $dt;
        $dz = ($fz * $forward + $rz * $strafe) * self::MOVE_SPEED * $dt;
        $this->movePlayer($dx, $dz);

        if ($fire && $this->muzzle <= 0.0) $this->shoot();
        $this->stepGuardian($dt);

        if ($this->health <= 0) $this->state = 'dead';
        elseif ($this->guardian['health'] <= 0) $this->state = 'clear';
    }

    /** Demo used by JumPHP smoke tests. @return array{float,float,float,bool} */
    public function autopilot(): array
    {
        if ($this->state !== 'play') return [0.0, 0.0, 0.0, false];
        $dx = $this->guardian['x'] - $this->x;
        $dz = $this->guardian['z'] - $this->z;
        $target = atan2($dx, $dz);
        $error = self::angleDelta($target, $this->yaw);
        $turn = max(-1.0, min(1.0, $error * 2.0));
        $distance = hypot($dx, $dz);
        $forward = abs($error) < 0.65 && $distance > 2.4 ? 0.72 : 0.0;
        $strafe = $distance < 2.0 ? sin($this->time * 1.7) * 0.55 : 0.0;
        $fire = abs($error) < 0.18 && $distance < 10.0;
        return [$forward, $strafe, $turn, $fire];
    }

    private function movePlayer(float $dx, float $dz): void
    {
        $nx = $this->x + $dx;
        if (!$this->collides($nx, $this->y, $this->z, self::PLAYER_RADIUS, self::PLAYER_HALF_HEIGHT)) $this->x = $nx;
        $nz = $this->z + $dz;
        if (!$this->collides($this->x, $this->y, $nz, self::PLAYER_RADIUS, self::PLAYER_HALF_HEIGHT)) $this->z = $nz;
    }

    private function movePlayerVertical(float $dy): void
    {
        if (abs($dy) < 1e-7) return;
        $ny = $this->y + $dy;
        if ($this->collides($this->x, $ny, $this->z, self::PLAYER_RADIUS, self::PLAYER_HALF_HEIGHT)) {
            if ($dy < 0.0) $this->grounded = true;
            $this->vy = 0.0;
            return;
        }
        $this->y = $ny;
    }

    private function stepGuardian(float $dt): void
    {
        if ($this->guardian['health'] <= 0) return;
        $this->guardian['cooldown'] = max(0.0, $this->guardian['cooldown'] - $dt);
        $dx = $this->x - $this->guardian['x'];
        $dz = $this->z - $this->guardian['z'];
        $distance = hypot($dx, $dz);
        if ($distance > 1.15) {
            $speed = $distance > 5.0 ? 1.8 : 1.15;
            $len = max(0.001, $distance);
            $this->moveGuardian($dx / $len * $speed * $dt, $dz / $len * $speed * $dt);
        } elseif ($this->guardian['cooldown'] <= 0.0) {
            $this->health -= 9;
            $this->guardian['cooldown'] = 0.75;
            $this->guardian['flash'] = 0.12;
        }
    }

    private function moveGuardian(float $dx, float $dz): void
    {
        $r = 0.30; $h = 0.55;
        $nx = $this->guardian['x'] + $dx;
        if (!$this->collides($nx, $this->guardian['y'], $this->guardian['z'], $r, $h)) $this->guardian['x'] = $nx;
        $nz = $this->guardian['z'] + $dz;
        if (!$this->collides($this->guardian['x'], $this->guardian['y'], $nz, $r, $h)) $this->guardian['z'] = $nz;
    }

    private function shoot(): void
    {
        $this->shots++;
        $this->muzzle = 0.22;
        if ($this->guardian['health'] <= 0) return;
        $dx = $this->guardian['x'] - $this->x;
        $dz = $this->guardian['z'] - $this->z;
        $distance = hypot($dx, $dz);
        if ($distance > 11.0) return;
        $aim = atan2($dx, $dz);
        if (abs(self::angleDelta($aim, $this->yaw)) > 0.12) return;
        if (!$this->lineClear($this->x, $this->z, $this->guardian['x'], $this->guardian['z'])) return;
        $this->guardian['health'] -= 20;
        $this->guardian['flash'] = 0.10;
        $this->hits++;
    }

    private function lineClear(float $ax, float $az, float $bx, float $bz): bool
    {
        for ($i = 1; $i < 16; $i++) {
            $t = $i / 16;
            $x = $ax + ($bx - $ax) * $t;
            $z = $az + ($bz - $az) * $t;
            if ($this->collides($x, $this->y, $z, 0.03, 0.20)) return false;
        }
        return true;
    }

    private function collides(float $x, float $y, float $z, float $radius, float $halfHeight): bool
    {
        $bodyMinY = $y - $halfHeight;
        $bodyMaxY = $y + $halfHeight;
        foreach ($this->map->solids as $solid) {
            [$minX, $minY, $minZ] = $solid['min'];
            [$maxX, $maxY, $maxZ] = $solid['max'];
            if ($bodyMaxY <= $minY + 0.001 || $bodyMinY >= $maxY - 0.001) continue;
            $closestX = max($minX, min($maxX, $x));
            $closestZ = max($minZ, min($maxZ, $z));
            $cx = $x - $closestX;
            $cz = $z - $closestZ;
            if ($cx * $cx + $cz * $cz < $radius * $radius) return true;
        }
        return false;
    }

    private static function angleDelta(float $a, float $b): float
    {
        $d = fmod($a - $b + M_PI, 2 * M_PI);
        if ($d < 0) $d += 2 * M_PI;
        return $d - M_PI;
    }
}
