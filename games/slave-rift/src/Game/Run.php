<?php

namespace App\Game;

/** Bounded, deterministic PHP simulation. No renderer or host dependency. */
final class Run
{
    public const PLAYER_RADIUS = 0.28;
    public const PLAYER_HALF_HEIGHT = 0.70;
    public const MOVE_SPEED = 3.8;
    public const TURN_SPEED = 2.35;
    public const JUMP_SPEED = 5.2;
    public const GRAVITY = 13.5;
    public const MAX_ENEMIES = 48;
    public const MAX_PICKUPS = 80;

    public float $x;
    public float $y;
    public float $z;
    public float $yaw;
    public float $vy = 0.0;
    public bool $grounded = false;
    public int $health = 100;
    public int $maxHealth = 100;
    public int $shots = 0;
    public int $hits = 0;
    public int $kills = 0;
    public float $muzzle = 0.0;
    public float $hurt = 0.0;
    public float $pulse = 0.0;
    public float $time = 0.0;
    public string $state = 'play';
    public array $enemies = [];
    public array $pickups = [];
    public array $traces = [];
    public int $wave = 1;
    public float $waveTime = 0.0;
    public float $intermission = 0.0;
    public int $remaining = 12;
    public int $level = 1;
    public int $xp = 0;
    public int $nextXp = 6;
    public bool $pulseUnlocked = false;
    public array $choices = [];
    public string $message = 'AUTO FIRE - FACE THE HORDE';
    public float $messageTime = 4.0;

    private float $fireClock = 0.0;
    private float $pulseClock = 1.0;
    private float $spawnClock = 0.4;
    private float $invincible = 0.0;
    private int $nextId = 1;
    private int $rng;
    private int $damageRank = 0;
    private int $hasteRank = 0;
    private int $magnetRank = 0;
    private int $pulseRank = 0;

    public function __construct(public readonly BrushMap $map, int $seed = 1337)
    {
        [$this->x, $this->y, $this->z] = $map->playerStart;
        $this->yaw = $map->playerYaw;
        $this->rng = max(1, $seed % 65521);
    }

    public function step(float $dt, float $forward, float $strafe, float $turn, bool $fire = false, bool $jump = false): void
    {
        if ($this->state !== 'play') return;
        $dt = max(0.0, min($dt, 0.05));
        $this->time += $dt;
        $this->waveTime += $dt;
        foreach (['muzzle', 'hurt', 'pulse', 'messageTime', 'invincible'] as $field) {
            $this->$field = max(0.0, $this->$field - $dt);
        }
        foreach ($this->traces as $i => &$trace) {
            $trace['life'] -= $dt;
            if ($trace['life'] <= 0) unset($this->traces[$i]);
        }
        unset($trace);
        $this->yaw -= max(-1.0, min(1.0, $turn)) * self::TURN_SPEED * $dt;
        $length = max(1.0, hypot($forward, $strafe));
        $dx = (sin($this->yaw) * $forward - cos($this->yaw) * $strafe) / $length * self::MOVE_SPEED * $dt;
        $dz = (cos($this->yaw) * $forward + sin($this->yaw) * $strafe) / $length * self::MOVE_SPEED * $dt;
        if ($jump && $this->grounded) {
            $this->vy = self::JUMP_SPEED;
            $this->grounded = false;
        }
        $this->movePlayer($dx, $dz);
        $this->vy -= self::GRAVITY * $dt;
        $this->vertical($this->vy * $dt);

        $this->stepWaves($dt);
        $this->stepEnemies($dt);
        if ($this->health <= 0) {
            $this->health = 0;
            $this->state = 'dead';
            return;
        }
        $this->fireClock -= $dt;
        $this->pulseClock -= $dt;
        // F remains an optional narrow-aim priority modifier; weapons always fire.
        if ($this->fireClock <= 0) $this->shoot($fire);
        if ($this->pulseUnlocked && $this->pulseClock <= 0) $this->firePulse();
        $this->stepPickups($dt);
    }

    public function chooseUpgrade(int $slot): bool
    {
        if ($this->state !== 'upgrade' || !isset($this->choices[$slot])) return false;
        $choice = $this->choices[$slot];
        match ($choice) {
            'POWER' => $this->damageRank++,
            'HASTE' => $this->hasteRank++,
            'MAGNET' => $this->magnetRank++,
            'PULSE' => $this->pulseRank++,
            default => $this->maxHealth += 20,
        };
        if ($choice === 'VITALITY') $this->health = min($this->maxHealth, $this->health + 40);
        if ($choice === 'PULSE') $this->pulseUnlocked = true;
        $this->choices = [];
        $this->state = 'play';
        $this->message = $choice . ' UPGRADED';
        $this->messageTime = 2.0;
        $this->checkLevel();
        return true;
    }

    public function upgradeDescription(string $choice): string
    {
        return match ($choice) {
            'POWER' => '+35% RIVET DAMAGE',
            'HASTE' => 'FASTER AUTO FIRE',
            'MAGNET' => 'WIDER PICKUP RANGE',
            'PULSE' => $this->pulseUnlocked ? 'STRONGER WIDER PULSE' : 'UNLOCK AREA WEAPON',
            default => '+20 MAX HP / HEAL 40',
        };
    }

    /** Demo controls, also used by the integration smoke test. */
    public function autopilot(): array
    {
        $target = null;
        $best = INF;
        foreach ($this->enemies as $enemy) {
            $d = hypot($enemy['x'] - $this->x, $enemy['z'] - $this->z);
            if ($d < $best) { $target = $enemy; $best = $d; }
        }
        if ($target === null) return [0.25, 0.4, 0.3, false];
        $error = self::angleDelta(atan2($target['x'] - $this->x, $target['z'] - $this->z), $this->yaw);
        return [$best < 3.2 ? -0.7 : 0.25, 0.65, max(-1.0, min(1.0, -$error * 2)), false];
    }

    private function stepWaves(float $dt): void
    {
        if ($this->intermission > 0) {
            $this->intermission = max(0.0, $this->intermission - $dt);
            if ($this->intermission <= 0) {
                $this->wave++;
                $this->waveTime = 0.0;
                $this->remaining = min(70, 8 + $this->wave * 4);
                $this->spawnClock = 0.0;
                $this->message = 'WAVE ' . $this->wave . ($this->wave % 4 === 0 ? ' - BRUTE SURGE' : '');
                $this->messageTime = 2.5;
                if ($this->wave >= 3 && !$this->pulseUnlocked) {
                    $this->pulseUnlocked = true;
                    $this->message = 'PULSE UNLOCKED - CLOSE RANGE BLAST';
                }
            }
            return;
        }
        if (($this->remaining === 0 && $this->enemies === []) || $this->waveTime >= 26.0) {
            $this->intermission = 4.0;
            $this->health = min($this->maxHealth, $this->health + 10);
            $this->message = 'RIFT BREATHER - HEAL +10';
            $this->messageTime = 3.0;
            return;
        }
        $this->spawnClock -= $dt;
        if ($this->remaining <= 0 || $this->spawnClock > 0 || count($this->enemies) >= self::MAX_ENEMIES) return;
        $batch = min($this->remaining, 1 + intdiv($this->wave, 3));
        for ($i = 0; $i < $batch && count($this->enemies) < self::MAX_ENEMIES; $i++) {
            if ($this->spawnEnemy()) $this->remaining--;
        }
        $this->spawnClock = max(0.22, 1.2 - $this->wave * 0.065);
    }

    private function spawnEnemy(): bool
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $a = $this->random() * M_PI * 2;
            $r = 6.0 + $this->random() * 4.0;
            $x = max(-10.8, min(10.8, $this->x + sin($a) * $r));
            $z = max(-10.8, min(10.8, $this->z + cos($a) * $r));
            if (hypot($x - $this->x, $z - $this->z) < 5.0 || $this->collides($x, 0.56, $z, 0.42, 0.55)) continue;
            $roll = $this->random();
            $type = $this->wave >= 3 && $roll < 0.22 ? 'brute' : ($roll < 0.58 ? 'runner' : 'guardian');
            if ($this->wave % 4 === 0 && $this->remaining % 4 === 0) $type = 'brute';
            [$hp, $speed, $damage, $radius] = match ($type) {
                'runner' => [24, 2.05, 7, 0.23],
                'brute' => [135, 0.85, 18, 0.40],
                default => [48, 1.25, 10, 0.30],
            };
            $hp = (int)round($hp * (1 + min(20, $this->wave - 1) * 0.07));
            $id = $this->nextId++;
            $this->enemies[$id] = ['id' => $id, 'type' => $type, 'x' => $x, 'y' => 0.55, 'z' => $z,
                'health' => $hp, 'maxHealth' => $hp, 'speed' => $speed, 'damage' => $damage,
                'radius' => $radius, 'flash' => 0.0, 'cooldown' => 0.6, 'age' => 0.0];
            return true;
        }
        return false;
    }

    private function stepEnemies(float $dt): void
    {
        foreach ($this->enemies as $id => &$enemy) {
            $enemy['flash'] = max(0.0, $enemy['flash'] - $dt);
            $enemy['cooldown'] -= $dt;
            $enemy['age'] += $dt;
            $dx = $this->x - $enemy['x'];
            $dz = $this->z - $enemy['z'];
            $d = max(0.001, hypot($dx, $dz));
            if ($d < $enemy['radius'] + 0.55 && abs($this->y - $enemy['y']) < 1.1 &&
                $enemy['cooldown'] <= 0 && $this->lineClear([$enemy['x'], $enemy['y'], $enemy['z']], [$this->x, $this->y, $this->z])) {
                if ($this->invincible <= 0) {
                    $this->health -= $enemy['damage'];
                    $this->hurt = 0.25;
                    $this->invincible = 0.40;
                }
                $enemy['cooldown'] = 0.85;
            }
            if ($d < 0.55) continue;
            // Try both tangents when direct pursuit meets a pillar. No per-enemy pathfinding allocation.
            $angle = atan2($dx, $dz);
            $side = $id % 2 === 0 ? 1 : -1;
            foreach ([0.0, $side * 0.65, -$side * 0.65, $side * 1.3, -$side * 1.3] as $offset) {
                $speed = $enemy['speed'] * $dt;
                $nx = $enemy['x'] + sin($angle + $offset) * $speed;
                $nz = $enemy['z'] + cos($angle + $offset) * $speed;
                $ny = $this->supportHeight($nx, $nz, $enemy['radius'], $enemy['y'] - 0.55 + 0.23) + 0.55;
                if ($this->collides($nx, $ny, $nz, $enemy['radius'], 0.55)) continue;
                $blocked = false;
                foreach ($this->enemies as $otherId => $other) {
                    if ($otherId === $id) continue;
                    $spacing = ($enemy['radius'] + $other['radius']) * 0.8;
                    if (hypot($nx - $other['x'], $nz - $other['z']) < $spacing) { $blocked = true; break; }
                }
                if (!$blocked) { $enemy['x'] = $nx; $enemy['y'] = $ny; $enemy['z'] = $nz; break; }
            }
        }
        unset($enemy);
    }

    private function shoot(bool $focus): void
    {
        $target = null;
        $best = INF;
        foreach ($this->enemies as $id => $enemy) {
            $d = hypot($enemy['x'] - $this->x, $enemy['z'] - $this->z);
            $angle = abs(self::angleDelta(atan2($enemy['x'] - $this->x, $enemy['z'] - $this->z), $this->yaw));
            if ($d > 15 || $angle > ($focus ? 0.35 : 0.85)) continue;
            $score = $angle * 5 + $d * 0.20 - ($d < 2.5 ? 2 : 0);
            if ($score < $best && $this->lineClear([$this->x, $this->y + 0.35, $this->z], [$enemy['x'], $enemy['y'] + 0.15, $enemy['z']])) {
                $target = $id;
                $best = $score;
            }
        }
        if ($target === null) return; // Ready immediately as soon as a target enters view.
        $enemy = $this->enemies[$target];
        $this->traces[] = ['x' => $enemy['x'], 'y' => $enemy['y'] + 0.15, 'z' => $enemy['z'], 'life' => 0.075];
        $this->damage($target, (int)round(18 * (1 + $this->damageRank * 0.35)));
        $this->shots++;
        $this->hits++;
        $this->muzzle = 0.09;
        $this->fireClock = max(0.11, 0.30 * pow(0.83, $this->hasteRank));
    }

    private function firePulse(): void
    {
        $targets = [];
        foreach ($this->enemies as $id => $enemy) {
            if (hypot($enemy['x'] - $this->x, $enemy['z'] - $this->z) <= 3.6 + $this->pulseRank * 0.35 &&
                $this->lineClear([$this->x, $this->y, $this->z], [$enemy['x'], $enemy['y'], $enemy['z']])) $targets[] = $id;
        }
        if ($targets === []) return;
        foreach ($targets as $id) $this->damage($id, 30 + $this->pulseRank * 14);
        $this->pulse = 0.25;
        $this->pulseClock = max(1.0, 2.4 - $this->pulseRank * 0.15);
    }

    private function damage(int $id, int $amount): void
    {
        $this->enemies[$id]['health'] -= $amount;
        $this->enemies[$id]['flash'] = 0.12;
        if ($this->enemies[$id]['health'] > 0) return;
        $enemy = $this->enemies[$id];
        unset($this->enemies[$id]);
        $this->kills++;
        $kind = $this->kills % 9 === 0 ? 'health' : 'xp';
        $value = $kind === 'health' ? 18 : ($enemy['type'] === 'brute' ? 5 : 2);
        // Merge overflow into an existing gem; never silently lose the progression reward.
        if (count($this->pickups) >= self::MAX_PICKUPS) {
            $key = array_key_first($this->pickups);
            if ($kind === 'xp' && $this->pickups[$key]['kind'] === 'xp') $this->pickups[$key]['value'] += $value;
            else array_shift($this->pickups);
        }
        if (count($this->pickups) < self::MAX_PICKUPS) {
            $this->pickups[] = ['x' => $enemy['x'], 'y' => $enemy['y'] - 0.3, 'z' => $enemy['z'], 'kind' => $kind, 'value' => $value, 'age' => 0.0];
        }
    }

    private function stepPickups(float $dt): void
    {
        foreach ($this->pickups as $id => &$pickup) {
            $pickup['age'] += $dt;
            $dx = $this->x - $pickup['x'];
            $dz = $this->z - $pickup['z'];
            $d = hypot($dx, $dz);
            if ($d < 1.9 + $this->magnetRank * 1.0 && $this->lineClear([$pickup['x'], $pickup['y'], $pickup['z']], [$this->x, $this->y, $this->z])) {
                if ($d < 0.6) {
                    if ($pickup['kind'] === 'xp') $this->xp += $pickup['value'];
                    else $this->health = min($this->maxHealth, $this->health + $pickup['value']);
                    unset($this->pickups[$id]);
                } else {
                    $s = min($d, $dt * 6);
                    $pickup['x'] += $dx / $d * $s;
                    $pickup['z'] += $dz / $d * $s;
                }
            }
        }
        unset($pickup);
        $this->checkLevel();
    }

    private function checkLevel(): void
    {
        if ($this->xp < $this->nextXp || $this->state !== 'play') return;
        $this->xp -= $this->nextXp;
        $this->level++;
        $this->nextXp = 6 + ($this->level - 1) * 4;
        $pool = ['POWER', 'HASTE', 'MAGNET', 'VITALITY', 'PULSE'];
        $this->choices = [];
        for ($i = 0; $i < 3; $i++) {
            $index = (int)floor($this->random() * count($pool));
            $this->choices[] = $pool[$index];
            array_splice($pool, $index, 1);
        }
        $this->state = 'upgrade'; // Freeze simulation; the scene still accepts upgrade input.
    }

    private function movePlayer(float $dx, float $dz): void
    {
        foreach ([[$dx, 0.0], [0.0, $dz]] as [$sx, $sz]) {
            $nx = $this->x + $sx;
            $nz = $this->z + $sz;
            if (!$this->collides($nx, $this->y, $nz, self::PLAYER_RADIUS, self::PLAYER_HALF_HEIGHT)) {
                $this->x = $nx; $this->z = $nz;
            } elseif ($this->grounded && !$this->collides($nx, $this->y + 0.23, $nz, self::PLAYER_RADIUS, self::PLAYER_HALF_HEIGHT)) {
                $this->x = $nx; $this->z = $nz; $this->y += 0.23;
            }
        }
    }

    private function vertical(float $dy): void
    {
        $this->grounded = false;
        if (!$this->collides($this->x, $this->y + $dy, $this->z, self::PLAYER_RADIUS, self::PLAYER_HALF_HEIGHT)) {
            $this->y += $dy;
            return;
        }
        // Resolve right to the surface instead of stopping one full timestep above it.
        $safe = 0.0; $blocked = 1.0;
        for ($i = 0; $i < 12; $i++) {
            $t = ($safe + $blocked) * 0.5;
            if ($this->collides($this->x, $this->y + $dy * $t, $this->z, self::PLAYER_RADIUS, self::PLAYER_HALF_HEIGHT)) $blocked = $t;
            else $safe = $t;
        }
        $this->y += $dy * $safe;
        $this->grounded = $dy < 0;
        $this->vy = 0.0;
    }

    /** Exact segment / AABB slab test: thin walls cannot be skipped by sampling. */
    public function lineClear(array $a, array $b): bool
    {
        foreach ($this->map->solids as $solid) {
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
            if ($lo <= $hi) return false;
        }
        return true;
    }

    public function collides(float $x, float $y, float $z, float $radius, float $halfHeight): bool
    {
        foreach ($this->map->solids as $solid) {
            [$minX, $minY, $minZ] = $solid['min']; [$maxX, $maxY, $maxZ] = $solid['max'];
            if ($y + $halfHeight <= $minY + 0.001 || $y - $halfHeight >= $maxY - 0.001) continue;
            $cx = $x - max($minX, min($maxX, $x));
            $cz = $z - max($minZ, min($maxZ, $z));
            if ($cx * $cx + $cz * $cz < $radius * $radius) return true;
        }
        return false;
    }

    private function supportHeight(float $x, float $z, float $radius, float $ceiling): float
    {
        $top = 0.0;
        foreach ($this->map->solids as $solid) {
            $height = $solid['max'][1];
            if ($height > $ceiling + 0.002 || $height < $top) continue;
            $dx = $x - max($solid['min'][0], min($solid['max'][0], $x));
            $dz = $z - max($solid['min'][2], min($solid['max'][2], $z));
            if ($dx * $dx + $dz * $dz < $radius * $radius) $top = $height;
        }
        return $top;
    }

    private function random(): float
    {
        // Product remains below 2^31 on the browser's 32-bit PHP runtime.
        $this->rng = ($this->rng * 25173 + 13849) % 65521;
        return $this->rng / 65521;
    }

    private static function angleDelta(float $a, float $b): float
    {
        $d = fmod($a - $b + M_PI, 2 * M_PI);
        if ($d < 0) $d += 2 * M_PI;
        return $d - M_PI;
    }
}
