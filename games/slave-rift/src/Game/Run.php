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
    public const MAX_VISUAL_TRACES = 40;

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
    public int $score = 0;
    public int $combo = 0;
    public float $comboTime = 0.0;
    public float $multiplier = 1.0;
    public float $muzzle = 0.0;
    public float $hurt = 0.0;
    public float $pulse = 0.0;
    public float $specialFlash = 0.0;
    public float $time = 0.0;
    public string $state = 'play';
    public array $enemies = [];
    /** @var array<int,array<string,mixed>> Short-lived visual death animations; excluded from live enemy pressure. */
    public array $corpses = [];
    public array $pickups = [];
    public array $traces = [];
    public int $losTests = 0;
    public int $collisionTests = 0;
    public int $neighborChecks = 0;
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
    private array $lastGroundedPosition;
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
        $this->lastGroundedPosition = [$this->x, $this->y, $this->z];
        $this->rng = max(1, $seed % 65521);
    }

    public function step(float $dt, float $forward, float $strafe, float $turn, bool $fire = false, bool $jump = false, bool $special = false): void
    {
        if ($this->state !== 'play') return;
        $dt = max(0.0, min($dt, 0.05));
        $this->losTests = $this->collisionTests = $this->neighborChecks = 0;
        $this->time += $dt;
        $this->waveTime += $dt;
        foreach (['muzzle', 'hurt', 'pulse', 'specialFlash', 'messageTime', 'invincible', 'specialCooldown'] as $field) {
            $this->$field = max(0.0, $this->$field - $dt);
        }
        if ($this->comboTime > 0) {
            $this->comboTime = max(0.0, $this->comboTime - $dt);
            if ($this->comboTime === 0.0) $this->resetCombo();
        }
        foreach ($this->traces as $i => &$trace) {
            $trace['life'] -= $dt;
            if ($trace['life'] <= 0) unset($this->traces[$i]);
        }
        unset($trace);
        foreach ($this->corpses as $i => &$corpse) {
            $corpse['age'] += $dt;
            if ($corpse['age'] >= 0.64) unset($this->corpses[$i]);
        }
        unset($corpse);
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
        if ($this->grounded) $this->lastGroundedPosition = [$this->x, $this->y, $this->z];
        if ($this->y < $this->lastGroundedPosition[1] - 3.0) {
            [$this->x, $this->y, $this->z] = $this->lastGroundedPosition;
            $this->vy = 0.0;
            $this->grounded = true;
        }

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
        if ($visibleTarget === null) {
            // Use the map's deathmatch points as safe exploration waypoints while
            // every live enemy is hidden behind geometry.
            $goalX = $target['x'];
            $goalZ = $target['z'];
            $waypointDistance = INF;
            foreach ($this->map->spawnPoints as $point) {
                $distance = hypot($point[0] - $this->x, $point[2] - $this->z);
                if ($distance < 6.0 || $distance >= $waypointDistance) continue;
                $goalX = $point[0];
                $goalZ = $point[2];
                $waypointDistance = $distance;
            }

            $goalAngle = atan2($goalX - $this->x, $goalZ - $this->z);
            $bestHeading = null;
            $bestPathScore = INF;
            for ($i = 0; $i < 24; $i++) {
                $heading = $goalAngle + $i * (M_PI / 12.0);
                $probeX = $this->x + sin($heading);
                $probeZ = $this->z + cos($heading);
                if ($this->collides($probeX, $this->y, $probeZ, self::PLAYER_RADIUS, self::PLAYER_HALF_HEIGHT)) continue;
                if (!$this->collides($probeX, $this->y - 0.12, $probeZ, self::PLAYER_RADIUS, self::PLAYER_HALF_HEIGHT)) continue;
                $remaining = hypot($goalX - $probeX, $goalZ - $probeZ);
                $score = $remaining + abs(self::angleDelta($heading, $goalAngle)) * 0.16;
                if ($score < $bestPathScore) {
                    $bestPathScore = $score;
                    $bestHeading = $heading;
                }
            }
            if ($bestHeading === null) return [0.18, sin($this->time * 0.8) * 0.55, 0.68, false];
            $error = self::angleDelta($bestHeading, $this->yaw);
            return [0.72, 0.0, max(-1.0, min(1.0, -$error * 1.5)), false];
        }
        // Prefer an enemy the weapons can actually hit. The nearest enemy may be
        // on the other side of a DM1 wall, where it would otherwise hold aim forever.
        $aimTarget = $visibleTarget;
        $error = self::angleDelta(atan2($aimTarget['x'] - $this->x, $aimTarget['z'] - $this->z), $this->yaw);
        $forward = hypot($visibleTarget['x'] - $this->x, $visibleTarget['z'] - $this->z) < 3.2 ? -0.35 : 0.25;
        return [$forward, 0.0, max(-1.0, min(1.0, -$error * 2)), abs($error) < 0.12];
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
            $waveBonus = (int)round(($this->wave * 150 + $this->combo * 50) * $this->multiplier);
            $this->score += $waveBonus;
            $this->intermission = 4.0;
            $this->health = min($this->maxHealth, $this->health + 10);
            $this->message = 'WAVE BONUS +' . $waveBonus . ' / HEAL +10';
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
                // DM1 has stacked floors. A spawn directly above the player
                // reads as an enemy walking on the ceiling from below.
                if (abs($y - $this->y) > 1.5) continue;
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
        $fallbackCandidates = [];
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $a = $this->random() * M_PI * 2;
            $r = 6.0 + $this->random() * 4.0;
            $x = $this->x + sin($a) * $r;
            $z = $this->z + cos($a) * $r;
            $ground = $this->supportHeight($x, $z, 0.42, $this->y + 2.0);
            if ($ground === null) continue;
            $y = $ground + 0.55;
            if (abs($y - $this->y) > 1.5) continue;
            if (hypot($x - $this->x, $z - $this->z) < 5.0 || $this->collides($x, $y, $z, 0.42, 0.55)) continue;
            $angle = abs(self::angleDelta(atan2($x - $this->x, $z - $this->z), $this->yaw));
            $visible = $this->lineClear([$this->x, $this->y + 0.35, $this->z], [$x, $y + 0.2, $z]);
            $fallbackCandidates[] = ['x' => $x, 'y' => $y, 'z' => $z,
                'score' => $angle + ($visible ? 0.0 : 2.0)];
        }
        if ($fallbackCandidates !== []) {
            usort($fallbackCandidates, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
            $point = $fallbackCandidates[0];
            return $this->spawnEnemyAt($point['x'], $point['y'], $point['z']);
        }
        return false;
    }

    private function spawnEnemyAt(float $x, float $y, float $z): bool
    {
        $roll = $this->random();
        $type = $this->wave >= 2 && $roll < 0.14 ? 'enforcer'
            : ($this->wave >= 3 && $roll < 0.30 ? 'brute' : ($roll < 0.58 ? 'runner' : 'guardian'));
        if ($this->wave % 4 === 0 && $this->remaining % 4 === 0) $type = 'brute';
        [$hp, $speed, $damage, $radius] = match ($type) {
            'runner' => [24, 2.05, 7, 0.23],
            'brute' => [135, 0.85, 18, 0.40],
            'enforcer' => [48, 1.0, 6, 0.27],
            default => [48, 1.25, 10, 0.30],
        };
        $hp = (int)round($hp * (1 + min(20, $this->wave - 1) * 0.07));
        $id = $this->nextId++;
        $yaw = atan2($this->x - $x, $this->z - $z);
        $this->enemies[$id] = ['id' => $id, 'type' => $type, 'x' => $x, 'y' => $y, 'z' => $z,
            'health' => $hp, 'maxHealth' => $hp, 'speed' => $speed, 'damage' => $damage,
            'radius' => $radius, 'flash' => 0.0, 'cooldown' => 0.6, 'age' => 0.0,
            'yaw' => $yaw, 'attackTime' => 0.0, 'moving' => false, 'animState' => 'idle'];
        return true;
    }

    private function stepEnemies(float $dt): void
    {
        $grid = [];
        foreach ($this->enemies as $id => $entry) {
            $cell = (int)floor($entry['x'] / 1.5) . ':' . (int)floor($entry['z'] / 1.5);
            $grid[$cell][$id] = true;
        }
        foreach ($this->enemies as $id => &$enemy) {
            $enemy['flash'] = max(0.0, $enemy['flash'] - $dt);
            $enemy['cooldown'] -= $dt;
            $enemy['attackTime'] = max(0.0, $enemy['attackTime'] - $dt);
            $enemy['age'] += $dt;
            $enemy['moving'] = false;
            $dx = $this->x - $enemy['x'];
            $dz = $this->z - $enemy['z'];
            $d = max(0.001, hypot($dx, $dz));
            $angle = atan2($dx, $dz);
            $enemy['yaw'] = $angle;
            $ranged = $enemy['type'] === 'enforcer';
            $attackRange = $ranged ? ($d > 2.5 && $d < 12.0) : ($d < $enemy['radius'] + 0.55 && abs($this->y - $enemy['y']) < 1.1);
            $attackVisible = $attackRange && $enemy['cooldown'] <= 0 && $this->lineClear(
                [$enemy['x'], $enemy['y'] + ($ranged ? 0.28 : 0.0), $enemy['z']],
                [$this->x, $this->y + ($ranged ? 0.35 : 0.0), $this->z],
            );
            if ($attackRange && $enemy['cooldown'] <= 0 && $attackVisible) {
                if ($this->invincible <= 0) {
                    $this->health -= $enemy['damage'];
                    $this->hurt = 0.25;
                    $this->invincible = 0.40;
                    $this->resetCombo();
                }
                $enemy['cooldown'] = $ranged ? 1.45 : 0.85;
                $enemy['attackTime'] = 0.32;
            }
            if ($d >= 0.55) {
                $side = $id % 2 === 0 ? 1 : -1;
                foreach ([0.0, $side * 0.65, -$side * 0.65, $side * 1.3, -$side * 1.3] as $offset) {
                    $speed = $enemy['speed'] * $dt;
                    $nx = $enemy['x'] + sin($angle + $offset) * $speed;
                    $nz = $enemy['z'] + cos($angle + $offset) * $speed;
                    $ground = $this->supportHeight($nx, $nz, $enemy['radius'], $enemy['y'] - 0.55 + 0.23);
                    if ($ground === null) continue;
                    $ny = $ground + 0.55;
                    if ($this->collides($nx, $ny, $nz, $enemy['radius'], 0.55)) continue;
                    $blocked = false;
                    $gx = (int)floor($nx / 1.5); $gz = (int)floor($nz / 1.5);
                    for ($ix = $gx - 1; $ix <= $gx + 1 && !$blocked; $ix++) for ($iz = $gz - 1; $iz <= $gz + 1 && !$blocked; $iz++) {
                        foreach ($grid[$ix . ':' . $iz] ?? [] as $otherId => $_) {
                            if ($otherId === $id) continue;
                            $this->neighborChecks++;
                            $other = $this->enemies[$otherId];
                            $spacing = ($enemy['radius'] + $other['radius']) * 0.8;
                            if (abs($ny - $other['y']) < 1.1 && hypot($nx - $other['x'], $nz - $other['z']) < $spacing) { $blocked = true; break; }
                        }
                    }
                    if (!$blocked) {
                        $oldCell = (int)floor($enemy['x'] / 1.5) . ':' . (int)floor($enemy['z'] / 1.5);
                        $newCell = $gx . ':' . $gz;
                        if ($oldCell !== $newCell) { unset($grid[$oldCell][$id]); $grid[$newCell][$id] = true; }
                        $enemy['x'] = $nx; $enemy['y'] = $ny; $enemy['z'] = $nz;
                        $enemy['moving'] = true;
                        break;
                    }
                }
            }
            $enemy['animState'] = $enemy['flash'] > 0 ? 'pain' : ($enemy['attackTime'] > 0 ? 'attack' : ($enemy['moving'] ? 'walk' : 'idle'));
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
        $this->addVisualTrace($enemy, 0.15, 'pistol');
        $this->damage($target, (int)round(38 * (1 + $this->damageRank * 0.35)));
        $this->hits++;
    }

    private function fireAutoWeapon(): void
    {
        $cone = match ($this->autoTier) { 1 => 0.50, 2 => 0.68, 3 => 0.90, default => 1.15 };
        $target = $this->findTarget(16.0, $cone);
        if ($target === null) { $this->autoClock = 0.04; return; }
        $enemy = $this->enemies[$target];
        $sample = $this->autoTier <= 1 ? 1 : ($this->autoTier <= 3 ? 2 : 3);
        if ($this->shots % $sample === 0) $this->addVisualTrace($enemy, 0.12, 'auto');
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
            $this->addVisualTrace($enemy, 0.2, 'special');
            $this->damage($id, max(38, 110 - $index * 22));
        }
        $this->message = 'RIFT LANCE';
        $this->messageTime = 0.7;
    }

    private function addVisualTrace(array $enemy, float $height, string $type): void
    {
        if (count($this->traces) >= self::MAX_VISUAL_TRACES) array_shift($this->traces);
        $life = $type === 'special' ? 0.08 : 0.06;
        $this->traces[] = [
            'startX' => $this->x + sin($this->yaw) * 0.5 - cos($this->yaw) * 0.14,
            'startY' => $this->y + 0.18,
            'startZ' => $this->z + cos($this->yaw) * 0.5 + sin($this->yaw) * 0.14,
            'endX' => $enemy['x'], 'endY' => $enemy['y'] + $height, 'endZ' => $enemy['z'],
            'x' => $enemy['x'], 'y' => $enemy['y'] + $height, 'z' => $enemy['z'],
            'life' => $life, 'totalLife' => $life, 'type' => $type,
        ];
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
        $this->enemies[$id]['animState'] = 'pain';
        if ($this->enemies[$id]['health'] > 0) return;
        $enemy = $this->enemies[$id];
        unset($this->enemies[$id]);
        $this->corpses[] = array_merge($enemy, ['age' => 0.0, 'animState' => 'death']);
        $this->kills++;
        $this->combo++;
        $this->comboTime = 3.5;
        $this->multiplier = match (true) {
            $this->combo >= 24 => 10.0,
            $this->combo >= 16 => 5.0,
            $this->combo >= 11 => 3.0,
            $this->combo >= 7 => 2.0,
            $this->combo >= 4 => 1.5,
            $this->combo >= 2 => 1.2,
            default => 1.0,
        };
        $baseScore = match ($enemy['type']) {
            'runner' => 100,
            'brute' => 800,
            'enforcer' => 400,
            default => 250,
        };
        $this->score += (int)round(($baseScore + $this->wave * 25) * $this->multiplier);
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

    private function resetCombo(): void
    {
        $this->combo = 0;
        $this->comboTime = 0.0;
        $this->multiplier = 1.0;
    }

    private function movePlayer(float $dx, float $dz): void
    {
        foreach ([[$dx, 0.0], [0.0, $dz]] as [$sx, $sz]) {
            $nx = $this->x + $sx;
            $nz = $this->z + $sz;
            $floor = null;
            if ($this->grounded) {
                $floor = $this->supportHeight($nx, $nz, self::PLAYER_RADIUS, $this->y - self::PLAYER_HALF_HEIGHT + 0.58);
                if ($floor === null || $floor < $this->y - self::PLAYER_HALF_HEIGHT - 0.8) continue;
            }
            if (!$this->collides($nx, $this->y, $nz, self::PLAYER_RADIUS, self::PLAYER_HALF_HEIGHT)) {
                $this->x = $nx; $this->z = $nz;
            } elseif ($this->grounded && $floor !== null) {
                $rise = $floor - ($this->y - self::PLAYER_HALF_HEIGHT);
                if ($rise > 0.0 && $rise <= 0.58 &&
                    !$this->collides($nx, $this->y + $rise + 0.002, $nz, self::PLAYER_RADIUS, self::PLAYER_HALF_HEIGHT)) {
                    $this->x = $nx; $this->z = $nz; $this->y += $rise + 0.002;
                }
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
        $this->losTests++;
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
        $this->collisionTests++;
        foreach ($this->map->solids as $solid) {
            [$minX, $minY, $minZ] = $solid['min']; [$maxX, $maxY, $maxZ] = $solid['max'];
            if ($y + $halfHeight <= $minY + 0.001 || $y - $halfHeight >= $maxY - 0.001) continue;
            $cx = $x - max($minX, min($maxX, $x));
            $cz = $z - max($minZ, min($maxZ, $z));
            if ($cx * $cx + $cz * $cz < $radius * $radius) return true;
        }
        return false;
    }

    private function supportHeight(float $x, float $z, float $radius, float $ceiling): ?float
    {
        $top = null;
        foreach ($this->map->solids as $solid) {
            $height = $solid['max'][1];
            if ($height > $ceiling + 0.002 || ($top !== null && $height < $top)) continue;
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
