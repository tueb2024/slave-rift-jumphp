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
    public float $specialFlash = 0.0;
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
    public int $autoTier = 0;
    public string $autoWeapon = 'LOCKED';
    public float $specialCooldown = 0.0;
    public float $specialCooldownMax = 7.5;
    public array $choices = [];
    public string $message = 'COLT .45 - MANUAL FIRE';
    public float $messageTime = 4.0;

    private float $manualClock = 0.0;
    private float $autoClock = 0.0;
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
        // Quake deathmatch maps often use info_player_start as an intermission
        // camera. DM1's is above empty space, so use a real deathmatch spawn.
        [$this->x, $this->y, $this->z] = $map->spawnPoints[0] ?? $map->playerStart;
        $this->yaw = $map->playerYaw;
        $this->rng = max(1, $seed % 65521);
    }

    public function step(float $dt, float $forward, float $strafe, float $turn, bool $fire = false, bool $jump = false, bool $special = false): void
    {
        if ($this->state !== 'play') return;
        $dt = max(0.0, min($dt, 0.05));
        $this->time += $dt;
        $this->waveTime += $dt;
        foreach (['muzzle', 'hurt', 'pulse', 'specialFlash', 'messageTime', 'invincible', 'specialCooldown'] as $field) {
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

        $this->manualClock -= $dt;
        $this->autoClock -= $dt;
        $this->pulseClock -= $dt;

        // Layer 1: the Colt is always player-controlled and rewards aim.
        if ($fire && $this->manualClock <= 0) $this->fireColt();
        // Layer 2: automatic weapons appear after the first level-up and grow to a full "sulfateuse".
        if ($this->autoTier > 0 && $this->autoClock <= 0) $this->fireAutoWeapon();
        // Layer 3: pulse remains a separate radial survivor-style weapon.
        if ($this->pulseUnlocked && $this->pulseClock <= 0) $this->firePulse();
        // Layer 4: right-click special, deliberately active rather than automatic.
        if ($special && $this->specialCooldown <= 0) $this->fireSpecial();

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
        $this->syncArsenal();
        $this->message = $choice . ' UPGRADED / ' . $this->arsenalLabel();
        $this->messageTime = 2.4;
        $this->checkLevel();
        return true;
    }

    public function upgradeDescription(string $choice): string
    {
        return match ($choice) {
            'POWER' => '+35% COLT / AUTO DAMAGE',
            'HASTE' => 'FASTER AUTO FIRE',
            'MAGNET' => 'WIDER PICKUP RANGE',
            'PULSE' => $this->pulseUnlocked ? 'STRONGER WIDER PULSE' : 'UNLOCK RADIAL PULSE',
            default => '+20 MAX HP / HEAL 40',
        };
    }

    public function arsenalLabel(): string
    {
        return match ($this->autoTier) {
            1 => 'STEN AUTO',
            2 => 'SMG AUTO',
            3 => 'MACHINE GUN AUTO',
            4 => 'SULFATEUSE MAX',
            default => 'COLT .45 ONLY',
        };
    }

    /** Demo controls, also used by the integration smoke test. */
    public function autopilot(): array
    {
        $target = null;
        $best = INF;
        $visibleTarget = null;
        $visibleScore = INF;
        foreach ($this->enemies as $enemy) {
            $d = hypot($enemy['x'] - $this->x, $enemy['z'] - $this->z);
            if ($d < $best) { $target = $enemy; $best = $d; }
            if ($d <= 19.0 && $this->lineClear(
                [$this->x, $this->y + 0.35, $this->z],
                [$enemy['x'], $enemy['y'] + 0.15, $enemy['z']],
            )) {
                $angle = abs(self::angleDelta(atan2($enemy['x'] - $this->x, $enemy['z'] - $this->z), $this->yaw));
                $score = $d + $angle * 5.0;
                if ($score < $visibleScore) { $visibleTarget = $enemy; $visibleScore = $score; }
            }
        }
        if ($target === null) return [0.25, 0.4, 0.3, false];
        // Prefer an enemy the weapons can actually hit. The nearest enemy may be
        // on the other side of a DM1 wall, where it would otherwise hold aim forever.
        $aimTarget = $visibleTarget ?? $target;
        $error = self::angleDelta(atan2($aimTarget['x'] - $this->x, $aimTarget['z'] - $this->z), $this->yaw);
        $forward = $visibleTarget !== null && hypot($visibleTarget['x'] - $this->x, $visibleTarget['z'] - $this->z) < 3.2 ? -0.35 : 0.25;
        $strafe = $visibleTarget === null ? 0.25 : 0.0;
        return [$forward, $strafe, max(-1.0, min(1.0, -$error * 2)), $visibleTarget !== null && abs($error) < 0.12];
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
                    $this->message = 'PULSE UNLOCKED - RADIAL BLAST';
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
        if ($this->map->spawnPoints !== []) {
            $candidates = [];
            foreach ($this->map->spawnPoints as $point) {
                [$x, $mapY, $z] = $point;
                $y = $mapY - 0.20;
                $distance = hypot($x - $this->x, $z - $this->z);
                if ($distance < 4.5 || $distance > 34.0) continue;
                if ($this->collides($x, $y, $z, 0.42, 0.55)) continue;
                $occupied = false;
                foreach ($this->enemies as $enemy) {
                    if (hypot($x - $enemy['x'], $z - $enemy['z']) < 1.1 && abs($y - $enemy['y']) < 1.4) {
                        $occupied = true;
                        break;
                    }
                }
                if ($occupied) continue;
                $angle = abs(self::angleDelta(atan2($x - $this->x, $z - $this->z), $this->yaw));
                $visible = $this->lineClear([$this->x, $this->y + 0.35, $this->z], [$x, $y + 0.25, $z]);
                $distanceScore = abs($distance - 12.0) * 0.28;
                $frontPenalty = $angle < 1.05 ? (1.05 - $angle) * 5.5 : 0.0;
                $visibilityPenalty = $visible ? 2.4 : -1.3;
                $candidates[] = [
                    'score' => $distanceScore + $frontPenalty + $visibilityPenalty + $this->random() * 0.55,
                    'x' => $x, 'y' => $y, 'z' => $z,
                ];
            }
            if ($candidates !== []) {
                usort($candidates, static fn (array $a, array $b): int => $a['score'] <=> $b['score']);
                $spawn = $candidates[0];
                return $this->spawnEnemyAt($spawn['x'], $spawn['y'], $spawn['z']);
            }
        }
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $a = $this->random() * M_PI * 2;
            $r = 6.0 + $this->random() * 4.0;
            $x = $this->x + sin($a) * $r;
            $z = $this->z + cos($a) * $r;
            $ground = $this->supportHeight($x, $z, 0.42, $this->y + 2.0);
            $y = $ground + 0.55;
            if (hypot($x - $this->x, $z - $this->z) < 5.0 || $this->collides($x, $y, $z, 0.42, 0.55)) continue;
            return $this->spawnEnemyAt($x, $y, $z);
        }
        return false;
    }

    private function spawnEnemyAt(float $x, float $y, float $z): bool
    {
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
        $this->enemies[$id] = ['id' => $id, 'type' => $type, 'x' => $x, 'y' => $y, 'z' => $z,
            'health' => $hp, 'maxHealth' => $hp, 'speed' => $speed, 'damage' => $damage,
            'radius' => $radius, 'flash' => 0.0, 'cooldown' => 0.6, 'age' => 0.0];
        return true;
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

    private function fireColt(): void
    {
        $target = $this->findTarget(19.0, 0.14);
        $this->shots++;
        $this->muzzle = 0.12;
        $this->manualClock = 0.34;
        if ($target === null) return;
        $enemy = $this->enemies[$target];
        $this->traces[] = ['x' => $enemy['x'], 'y' => $enemy['y'] + 0.15, 'z' => $enemy['z'], 'life' => 0.09];
        $this->damage($target, (int)round(38 * (1 + $this->damageRank * 0.35)));
        $this->hits++;
    }

    private function fireAutoWeapon(): void
    {
        $cone = match ($this->autoTier) { 1 => 0.50, 2 => 0.68, 3 => 0.90, default => 1.15 };
        $target = $this->findTarget(16.0, $cone);
        if ($target === null) { $this->autoClock = 0.04; return; }
        $enemy = $this->enemies[$target];
        $this->traces[] = ['x' => $enemy['x'], 'y' => $enemy['y'] + 0.12, 'z' => $enemy['z'], 'life' => 0.055];
        $baseDamage = match ($this->autoTier) { 1 => 9, 2 => 11, 3 => 13, default => 15 };
        $baseRate = match ($this->autoTier) { 1 => 0.27, 2 => 0.20, 3 => 0.145, default => 0.095 };
        $this->damage($target, (int)round($baseDamage * (1 + $this->damageRank * 0.28)));
        $this->shots++;
        $this->hits++;
        $this->muzzle = max($this->muzzle, 0.055);
        $this->autoClock = max(0.065, $baseRate * pow(0.86, $this->hasteRank));
    }

    private function fireSpecial(): void
    {
        $primary = $this->findTarget(22.0, 0.42);
        $this->specialCooldown = $this->specialCooldownMax;
        $this->specialFlash = 0.35;
        if ($primary === null) {
            $this->message = 'RIFT LANCE - NO LOCK';
            $this->messageTime = 1.0;
            return;
        }
        $hit = [$primary];
        $anchor = $this->enemies[$primary];
        // Heavy piercing/chain weapon: strongest nearby targets are struck after the aimed primary.
        $candidates = [];
        foreach ($this->enemies as $id => $enemy) {
            if ($id === $primary) continue;
            $d = hypot($enemy['x'] - $anchor['x'], $enemy['z'] - $anchor['z']);
            if ($d <= 4.2 && $this->lineClear([$anchor['x'], $anchor['y'], $anchor['z']], [$enemy['x'], $enemy['y'], $enemy['z']])) {
                $candidates[$id] = $d;
            }
        }
        asort($candidates);
        foreach (array_slice(array_keys($candidates), 0, 3) as $id) $hit[] = $id;
        foreach ($hit as $index => $id) {
            if (!isset($this->enemies[$id])) continue;
            $enemy = $this->enemies[$id];
            $this->traces[] = ['x' => $enemy['x'], 'y' => $enemy['y'] + 0.2, 'z' => $enemy['z'], 'life' => 0.18];
            $this->damage($id, max(38, 110 - $index * 22));
        }
        $this->message = 'RIFT LANCE';
        $this->messageTime = 0.7;
    }

    private function findTarget(float $range, float $cone): ?int
    {
        $target = null;
        $best = INF;
        foreach ($this->enemies as $id => $enemy) {
            $d = hypot($enemy['x'] - $this->x, $enemy['z'] - $this->z);
            $angle = abs(self::angleDelta(atan2($enemy['x'] - $this->x, $enemy['z'] - $this->z), $this->yaw));
            if ($d > $range || $angle > $cone) continue;
            $score = $angle * 7 + $d * 0.14;
            if ($score < $best && $this->lineClear([$this->x, $this->y + 0.35, $this->z], [$enemy['x'], $enemy['y'] + 0.15, $enemy['z']])) {
                $target = $id;
                $best = $score;
            }
        }
        return $target;
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
        if (!isset($this->enemies[$id])) return;
        $this->enemies[$id]['health'] -= $amount;
        $this->enemies[$id]['flash'] = 0.12;
        if ($this->enemies[$id]['health'] > 0) return;
        $enemy = $this->enemies[$id];
        unset($this->enemies[$id]);
        $this->kills++;
        $kind = $this->kills % 9 === 0 ? 'health' : 'xp';
        $value = $kind === 'health' ? 18 : ($enemy['type'] === 'brute' ? 5 : 2);
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
        $this->syncArsenal();
        $this->nextXp = 6 + ($this->level - 1) * 4;
        $pool = ['POWER', 'HASTE', 'MAGNET', 'VITALITY', 'PULSE'];
        $this->choices = [];
        for ($i = 0; $i < 3; $i++) {
            $index = (int)floor($this->random() * count($pool));
            $this->choices[] = $pool[$index];
            array_splice($pool, $index, 1);
        }
        $this->state = 'upgrade';
    }

    private function syncArsenal(): void
    {
        $previous = $this->autoTier;
        $this->autoTier = min(4, max(0, $this->level - 1));
        $this->autoWeapon = match ($this->autoTier) {
            1 => 'STEN',
            2 => 'SMG',
            3 => 'MACHINE GUN',
            4 => 'SULFATEUSE',
            default => 'LOCKED',
        };
        if ($this->autoTier > $previous) {
            $this->message = $this->autoWeapon . ' AUTO FIRE UNLOCKED';
            $this->messageTime = 3.0;
            $this->autoClock = 0.1;
        }
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
