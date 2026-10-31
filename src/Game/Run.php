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
    public const MAX_VISUAL_CORPSES = 16;

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
    public int $primaryStage = 0;
    // Compatibility mirrors for the current HUD renderer. primaryStage is authoritative.
    public int $autoTier = 0;
    public string $autoWeapon = 'COLT';
    public int $autoGuns = 1;
    public int $flameRank = 0;
    public int $shurikenRank = 0;
    public float $flameActive = 0.0;
    public array $choices = [];
    public string $message = 'COLT .45 - RHYTHM FIRE';
    public float $messageTime = 4.0;

    private float $pulseClock = 1.0;
    /** @var list<array{delay:float,guns:int,split:bool,stage:int,lateral:float}> */
    private array $primaryBurstQueue = [];
    /** @var list<int> */
    private array $primaryAudioEvents = [];
    private int $primaryGunPhase = 0;
    private float $flameCooldownClock = 4.5;
    private float $flameTickClock = 0.0;
    private float $shurikenClock = 3.0;
    private float $spawnClock = 0.4;
    private float $invincible = 0.0;
    private int $nextId = 1;
    private array $recentSpawnPoints = [];
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
        $this->syncPrimaryStage();
    }

    public function step(float $dt, float $forward, float $strafe, float $turn, bool $fire = false, bool $jump = false, bool $special = false, array $rhythmSteps = []): void
    {
        if ($this->state !== 'play') return;
        $dt = max(0.0, min($dt, 0.05));
        $this->losTests = $this->collisionTests = $this->neighborChecks = 0;
        $this->time += $dt;
        $this->waveTime += $dt;
        foreach (['muzzle', 'hurt', 'pulse', 'specialFlash', 'messageTime', 'invincible'] as $field) {
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

        $this->pulseClock -= $dt;
        $this->stepPrimaryBursts($dt);
        foreach ($rhythmSteps as $rhythmStep) $this->triggerPrimaryRhythmStep((int)$rhythmStep);
        if ($this->pulseUnlocked && $this->pulseClock <= 0) $this->firePulse();
        if ($this->flameRank > 0) $this->stepFlamethrower($dt);
        if ($this->shurikenRank > 0) {
            $this->shurikenClock -= $dt;
            if ($this->shurikenClock <= 0) $this->fireShuriken();
        }

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
            'PULSE' => $this->upgradePulse(),
            'FLAMETHROWER' => $this->upgradeFlamethrower(),
            'SHURIKEN' => $this->upgradeShuriken(),
            default => $this->upgradeVitality(),
        };
        $this->choices = [];
        $this->state = 'play';
        $this->message = $choice . ' / ' . $this->arsenalLabel();
        $this->messageTime = 2.4;
        $this->checkLevel();
        return true;
    }

    private function upgradeVitality(): void
    {
        $this->maxHealth += 20;
        $this->health = min($this->maxHealth, $this->health + 40);
    }

    private function upgradePulse(): void
    {
        $this->pulseUnlocked = true;
        $this->pulseRank++;
        $this->pulseClock = min($this->pulseClock, 0.35);
    }

    private function upgradeFlamethrower(): void
    {
        $this->flameRank = min(3, $this->flameRank + 1);
        $this->flameCooldownClock = min($this->flameCooldownClock, 0.45);
    }

    private function upgradeShuriken(): void
    {
        $this->shurikenRank = min(3, $this->shurikenRank + 1);
        $this->shurikenClock = min($this->shurikenClock, 0.45);
    }

    private function syncPrimaryStage(): void
    {
        $next = self::primaryStageForLevel($this->level);
        $changed = $next !== $this->primaryStage;
        $this->primaryStage = $next;
        [$tier, $weapon, $guns] = match ($next) {
            0 => [0, 'COLT', 1],
            1 => [0, 'COLT', 2],
            2 => [1, 'MP44', 1],
            3 => [1, 'MP44', 2],
            4 => [3, 'BAR', 1],
            5 => [4, 'GATLING', 1],
            default => [4, 'GATLING', 2],
        };
        $this->autoTier = $tier;
        $this->autoWeapon = $weapon;
        $this->autoGuns = $guns;
        if ($changed) {
            $this->primaryBurstQueue = [];
            $this->message = $this->primaryWeaponLabel() . ' / RHYTHM LOCK';
            $this->messageTime = 2.6;
        }
    }

    public static function primaryStageForLevel(int $level): int
    {
        return match (true) {
            $level >= 24 => 6,
            $level >= 20 => 5,
            $level >= 16 => 4,
            $level >= 12 => 3,
            $level >= 8 => 2,
            $level >= 4 => 1,
            default => 0,
        };
    }

    public function primaryWeaponLabel(): string
    {
        return match ($this->primaryStage) {
            0 => 'COLT .45',
            1 => 'DUAL COLT',
            2 => 'MP44',
            3 => 'DUAL MP44',
            4 => 'BAR',
            5 => 'GATLING',
            default => 'DUAL GATLING',
        };
    }

    /** @return list<int> primary stages that actually emitted a shot event */
    public function drainPrimaryAudioEvents(): array
    {
        $events = $this->primaryAudioEvents;
        $this->primaryAudioEvents = [];
        return $events;
    }
    public function upgradeDescription(string $choice): string
    {
        return match ($choice) {
            'POWER' => '+30% PRIMARY DAMAGE / STRONGER SECONDARIES',
            'HASTE' => 'FASTER SECONDARY CYCLES',
            'MAGNET' => 'WIDER PICKUP RANGE',
            'PULSE' => $this->pulseUnlocked ? 'STRONGER WIDER PULSE' : 'UNLOCK RADIAL PULSE',
            'FLAMETHROWER' => $this->flameRank === 0 ? 'TRACKING FIRE CONE FOR 2.4s' : 'LONGER / WIDER / HOTTER SWEEP',
            'SHURIKEN' => $this->shurikenRank === 0 ? 'VOLLEY HITS UP TO 4 TARGETS' : 'MORE TARGETS / FASTER VOLLEY',
            default => '+20 MAX HP / HEAL 40',
        };
    }

    public function arsenalLabel(): string
    {
        $parts = [$this->primaryWeaponLabel()];
        if ($this->flameRank > 0) $parts[] = 'FLAME L' . $this->flameRank;
        if ($this->shurikenRank > 0) $parts[] = 'SHURIKEN L' . $this->shurikenRank;
        if ($this->pulseUnlocked) $parts[] = 'PULSE L' . max(1, $this->pulseRank);
        return implode(' + ', $parts);
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
        $type = $this->chooseEnemyType();
        $radius = $this->enemyRadius($type);
        if ($this->map->spawnPoints !== []) {
            $candidates = [];
            foreach ($this->map->spawnPoints as $index => $point) {
                [$x, $mapY, $z] = $point;
                $y = $mapY - 0.20;
                $distance = hypot($x - $this->x, $z - $this->z);
                if ($distance < 4.5 || $distance > 34.0) continue;
                // DM1 has stacked floors. A spawn directly above the player
                // reads as an enemy walking on the ceiling from below.
                if (abs($y - $this->y) > 1.5) continue;
                if ($this->collides($x, $y, $z, 0.42, 0.55)) continue;
                [$occupied, $nearby] = $this->spawnCrowding($x, $y, $z, $radius, $type);
                if ($occupied) continue;
                $angle = abs(self::angleDelta(atan2($x - $this->x, $z - $this->z), $this->yaw));
                $visible = $this->lineClear([$this->x, $this->y + 0.35, $this->z], [$x, $y + 0.25, $z]);
                $distanceScore = abs($distance - 12.0) * 0.28;
                $frontPenalty = $angle < 1.05 ? (1.05 - $angle) * 5.5 : 0.0;
                $visibilityPenalty = $visible ? 2.4 : -1.3;
                $recentPenalty = 0.0;
                foreach ($this->recentSpawnPoints as $age => $used) {
                    if ($used === $index) $recentPenalty += 3.0 - $age * 0.8;
                }
                $candidates[] = [
                    'score' => $distanceScore + $frontPenalty + $visibilityPenalty + $nearby * 1.5 + $recentPenalty + $this->random() * 0.55,
                    'x' => $x, 'y' => $y, 'z' => $z, 'index' => $index,
                ];
            }
            if ($candidates !== []) {
                usort($candidates, static fn (array $a, array $b): int => $a['score'] <=> $b['score']);
                $spawn = $candidates[0];
                $this->rememberSpawnPoint($spawn['index']);
                return $this->spawnEnemyAt($spawn['x'], $spawn['y'], $spawn['z'], $type);
            }
        }
        $fallbackCandidates = [];
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $a = $this->random() * M_PI * 2;
            $anchorIndex = null;
            if ($this->map->spawnPoints !== []) {
                $anchorIndex = min(count($this->map->spawnPoints) - 1, (int)floor($this->random() * count($this->map->spawnPoints)));
                [$baseX, $baseY, $baseZ] = $this->map->spawnPoints[$anchorIndex];
                $r = 2.5 + $this->random() * 3.5;
            } else {
                [$baseX, $baseY, $baseZ] = [$this->x, $this->y, $this->z];
                $r = 6.0 + $this->random() * 4.0;
            }
            $x = $baseX + sin($a) * $r;
            $z = $baseZ + cos($a) * $r;
            $distance = hypot($x - $this->x, $z - $this->z);
            if ($distance < 5.0 || $distance > 34.0) continue;
            $ground = $this->supportHeight($x, $z, 0.42, $baseY + 2.0);
            if ($ground === null) continue;
            $y = $ground + 0.55;
            if (abs($y - $this->y) > 1.5) continue;
            if ($this->collides($x, $y, $z, 0.42, 0.55)) continue;
            [$occupied, $nearby] = $this->spawnCrowding($x, $y, $z, $radius, $type);
            if ($occupied) continue;
            $angle = abs(self::angleDelta(atan2($x - $this->x, $z - $this->z), $this->yaw));
            $visible = $this->lineClear([$this->x, $this->y + 0.35, $this->z], [$x, $y + 0.2, $z]);
            $recentPenalty = 0.0;
            foreach ($this->recentSpawnPoints as $age => $used) {
                if ($used === $anchorIndex) $recentPenalty += 3.0 - $age * 0.8;
            }
            $fallbackCandidates[] = ['x' => $x, 'y' => $y, 'z' => $z,
                'index' => $anchorIndex,
                'score' => abs($distance - 12.0) * 0.28 + ($angle < 1.05 ? (1.05 - $angle) * 5.5 : 0.0)
                    + ($visible ? 2.4 : -1.3) + $nearby * 1.5 + $recentPenalty + $this->random() * 0.55];
        }
        if ($fallbackCandidates !== []) {
            usort($fallbackCandidates, static fn (array $a, array $b): int => $a['score'] <=> $b['score']);
            $point = $fallbackCandidates[0];
            if ($point['index'] !== null) $this->rememberSpawnPoint($point['index']);
            return $this->spawnEnemyAt($point['x'], $point['y'], $point['z'], $type);
        }
        return false;
    }

    private function chooseEnemyType(): string
    {
        $weights = self::enemyWeightsForWave($this->wave);
        $roll = $this->random();
        $cursor = 0.0;
        foreach ($weights as $type => $weight) {
            $cursor += $weight;
            if ($roll <= $cursor) return $type;
        }
        return 'brute';
    }

    /**
     * Four durability classes, progressively weighted toward tougher enemies.
     * Weapon DPS still grows faster than enemy health through DifficultyDirector;
     * this changes composition, not the underlying elasticity rule.
     *
     * @return array{runner:float,guardian:float,enforcer:float,brute:float}
     */
    public static function enemyWeightsForWave(int $wave): array
    {
        return match (true) {
            $wave <= 1 => ['runner' => 0.68, 'guardian' => 0.32, 'enforcer' => 0.00, 'brute' => 0.00],
            $wave <= 3 => ['runner' => 0.54, 'guardian' => 0.34, 'enforcer' => 0.12, 'brute' => 0.00],
            $wave <= 6 => ['runner' => 0.42, 'guardian' => 0.34, 'enforcer' => 0.18, 'brute' => 0.06],
            $wave <= 10 => ['runner' => 0.32, 'guardian' => 0.33, 'enforcer' => 0.23, 'brute' => 0.12],
            default => ['runner' => 0.22, 'guardian' => 0.30, 'enforcer' => 0.28, 'brute' => 0.20],
        };
    }

    private function enemyRadius(string $type): float
    {
        return match ($type) { 'runner' => 0.23, 'brute' => 0.40, 'enforcer' => 0.27, default => 0.30 };
    }

    private function spawnCrowding(float $x, float $y, float $z, float $radius, string $type): array
    {
        $nearby = 0;
        foreach ($this->enemies as $enemy) {
            if (abs($y - $enemy['y']) > 1.4) continue;
            $distance = hypot($x - $enemy['x'], $z - $enemy['z']);
            $spacing = ($type === 'brute' || $enemy['type'] === 'brute' ? 1.95 : 1.60)
                + $radius + $enemy['radius'];
            if ($distance < $spacing) return [true, $nearby];
            if ($distance < 5.0) $nearby++;
        }
        return [false, $nearby];
    }

    private function rememberSpawnPoint(int $index): void
    {
        array_unshift($this->recentSpawnPoints, $index);
        $this->recentSpawnPoints = array_slice($this->recentSpawnPoints, 0, 4);
    }

    private function spawnEnemyAt(float $x, float $y, float $z, string $type): bool
    {
        [$hp, $speed, $damage, $radius] = match ($type) {
            'runner' => [24, 2.08, 7, 0.23],
            'brute' => [180, 0.84, 20, 0.40],
            'enforcer' => [74, 1.02, 8, 0.27],
            default => [52, 1.25, 10, 0.30],
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
                $separateX = $separateZ = 0.0;
                $cx = (int)floor($enemy['x'] / 1.5); $cz = (int)floor($enemy['z'] / 1.5);
                for ($ix = $cx - 1; $ix <= $cx + 1; $ix++) for ($iz = $cz - 1; $iz <= $cz + 1; $iz++) {
                    foreach ($grid[$ix . ':' . $iz] ?? [] as $otherId => $_) {
                        if ($otherId === $id) continue;
                        $this->neighborChecks++;
                        $other = $this->enemies[$otherId];
                        if (abs($enemy['y'] - $other['y']) > 1.1) continue;
                        $awayX = $enemy['x'] - $other['x']; $awayZ = $enemy['z'] - $other['z'];
                        $gap = hypot($awayX, $awayZ);
                        if ($gap < 0.001 || $gap >= 1.6) continue;
                        $weight = (1.6 - $gap) / (1.6 * $gap);
                        $separateX += $awayX * $weight;
                        $separateZ += $awayZ * $weight;
                    }
                }
                $seekAngle = atan2($dx / $d + $separateX * 0.28, $dz / $d + $separateZ * 0.28);
                $side = $id % 2 === 0 ? 1 : -1;
                foreach ([0.0, $side * 0.65, -$side * 0.65, $side * 1.3, -$side * 1.3] as $offset) {
                    $speed = $enemy['speed'] * $dt;
                    $nx = $enemy['x'] + sin($seekAngle + $offset) * $speed;
                    $nz = $enemy['z'] + cos($seekAngle + $offset) * $speed;
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
                            $spacing = $enemy['radius'] + $other['radius'] + ($enemy['type'] === 'brute' || $other['type'] === 'brute' ? 0.55 : 0.42);
                            if (abs($ny - $other['y']) < 1.1) {
                                $nextDistance = hypot($nx - $other['x'], $nz - $other['z']);
                                $oldDistance = hypot($enemy['x'] - $other['x'], $enemy['z'] - $other['z']);
                                if ($nextDistance < $spacing && $nextDistance <= $oldDistance) { $blocked = true; break; }
                            }
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

    private function triggerPrimaryRhythmStep(int $step): void
    {
        $step = (($step % 16) + 16) % 16;
        $beat = 60.0 / (RhythmClock::BPM_BASE + max(0, $this->level - 1) * RhythmClock::BPM_PER_LEVEL);
        switch ($this->primaryStage) {
            case 0:
                if (($step % 4) === 0) $this->firePrimaryEvent(1, false, 0.0, 0);
                break;
            case 1:
                if (($step % 2) === 0) {
                    $lateral = $this->primaryGunPhase === 0 ? -0.16 : 0.16;
                    $this->firePrimaryEvent(1, false, $lateral, 1);
                    $this->primaryGunPhase = 1 - $this->primaryGunPhase;
                }
                break;
            case 2:
            case 3:
                if (($step % 4) !== 0) break;
                $guns = $this->primaryStage === 3 ? 2 : 1;
                $stage = $this->primaryStage;
                $this->firePrimaryEvent($guns, false, 0.0, $stage);
                // Long-short spacing gives the MP44 burst a deliberate swing.
                $this->queuePrimaryBurst($beat * 0.42, $guns, false, $stage);
                $this->queuePrimaryBurst($beat * 0.66, $guns, false, $stage);
                break;
            case 4:
                if (($step % 4) !== 0) break;
                $this->firePrimaryEvent(1, false, 0.0, 4);
                foreach ([0.11, 0.22, 0.33, 0.44, 0.55] as $fraction) {
                    $this->queuePrimaryBurst($beat * $fraction, 1, false, 4);
                }
                break;
            case 5:
                $this->firePrimaryEvent(1, false, 0.0, 5);
                break;
            default:
                // Ultimate stage: one rhythmic event, two guns, two targets when available.
                $this->firePrimaryEvent(2, true, 0.0, 6);
                break;
        }
    }

    private function queuePrimaryBurst(float $delay, int $guns, bool $split, int $stage, float $lateral = 0.0): void
    {
        if (count($this->primaryBurstQueue) >= 24) array_shift($this->primaryBurstQueue);
        $this->primaryBurstQueue[] = compact('delay', 'guns', 'split', 'stage', 'lateral');
    }

    private function stepPrimaryBursts(float $dt): void
    {
        if ($this->primaryBurstQueue === []) return;
        $ready = [];
        foreach ($this->primaryBurstQueue as $index => &$burst) {
            $burst['delay'] -= $dt;
            if ($burst['delay'] <= 0.0) {
                $ready[] = $burst;
                unset($this->primaryBurstQueue[$index]);
            }
        }
        unset($burst);
        $this->primaryBurstQueue = array_values($this->primaryBurstQueue);
        foreach ($ready as $burst) {
            $this->firePrimaryEvent($burst['guns'], $burst['split'], $burst['lateral'], $burst['stage']);
        }
    }

    private function firePrimaryEvent(int $guns, bool $splitTargets = false, float $singleLateral = 0.0, ?int $stage = null): void
    {
        $stage ??= $this->primaryStage;
        $targetCount = $splitTargets ? max(1, $guns) : 1;
        $cone = $stage >= 5 ? 0.24 : 0.18;
        $targets = $this->findTargets(19.0, $cone, $targetCount);
        if ($targets === []) return;

        $baseDamage = match ($stage) {
            0, 1 => 38,
            2, 3 => 14,
            4 => 10,
            default => 13,
        };
        $damage = (int)round($baseDamage * (1 + $this->damageRank * 0.30));
        $fired = 0;
        for ($gun = 0; $gun < max(1, $guns); $gun++) {
            $target = $splitTargets ? ($targets[$gun] ?? $targets[0]) : $targets[0];
            if (!isset($this->enemies[$target])) continue;
            $enemy = $this->enemies[$target];
            $lateral = $guns > 1 ? ($gun - ($guns - 1) / 2) * 0.22 : $singleLateral;
            $this->addVisualTrace($enemy, 0.14, $stage <= 1 ? 'pistol' : 'auto', $lateral);
            $this->damage($target, $damage);
            $this->shots++;
            $this->hits++;
            $fired++;
        }
        if ($fired === 0) return;
        $this->muzzle = max($this->muzzle, 0.11);
        if (count($this->primaryAudioEvents) >= 32) array_shift($this->primaryAudioEvents);
        $this->primaryAudioEvents[] = $stage;
    }
    private function stepFlamethrower(float $dt): void
    {
        if ($this->flameActive > 0.0) {
            $this->flameActive = max(0.0, $this->flameActive - $dt);
            $this->flameTickClock -= $dt;
            if ($this->flameTickClock <= 0.0) {
                $this->fireFlameTick();
                $this->flameTickClock += 0.12;
            }
            return;
        }
        $this->flameCooldownClock -= $dt;
        if ($this->flameCooldownClock > 0.0) return;
        $this->flameActive = 2.4 + ($this->flameRank - 1) * 0.45;
        $this->flameCooldownClock = max(5.4, 8.0 - ($this->flameRank - 1) * 0.7 - $this->hasteRank * 0.15);
        $this->flameTickClock = 0.0;
        $this->specialFlash = max($this->specialFlash, 0.18);
    }

    private function fireFlameTick(): void
    {
        $range = 5.2 + ($this->flameRank - 1) * 0.7;
        $cone = 0.48 + ($this->flameRank - 1) * 0.10;
        $limit = 6 + ($this->flameRank - 1) * 2;
        $targets = $this->findTargets($range, $cone, $limit);
        $baseDamage = 6 + ($this->flameRank - 1) * 2;
        foreach ($targets as $index => $id) {
            if (!isset($this->enemies[$id])) continue;
            $enemy = $this->enemies[$id];
            if ($index < 3) $this->addVisualTrace($enemy, 0.10, 'flame', ($index - 1) * 0.08);
            $this->damage($id, (int)round($baseDamage * (1 + $this->damageRank * 0.18)));
            $this->hits++;
        }
        if ($targets !== []) {
            $this->shots++;
            $this->muzzle = max($this->muzzle, 0.035);
        }
    }

    private function fireShuriken(): void
    {
        $count = 4 + ($this->shurikenRank - 1) * 2;
        $targets = $this->findTargets(18.0, 1.35, $count);
        if ($targets === []) {
            $this->shurikenClock = 0.25;
            return;
        }
        $damage = (int)round((26 + ($this->shurikenRank - 1) * 8) * (1 + $this->damageRank * 0.25));
        foreach ($targets as $index => $id) {
            if (!isset($this->enemies[$id])) continue;
            $enemy = $this->enemies[$id];
            $this->addVisualTrace($enemy, 0.18, 'shuriken', ($index - (count($targets) - 1) / 2) * 0.04);
            $this->damage($id, $damage);
            $this->shots++;
            $this->hits++;
        }
        $this->specialFlash = max($this->specialFlash, 0.12);
        $this->shurikenClock = max(1.4, 3.1 - ($this->shurikenRank - 1) * 0.4 - $this->hasteRank * 0.12);
    }

    private function addVisualTrace(array $enemy, float $height, string $type, float $lateral = 0.0): void
    {
        if (count($this->traces) >= self::MAX_VISUAL_TRACES) array_shift($this->traces);
        $life = in_array($type, ['flame', 'shuriken'], true) ? 0.075 : 0.06;
        $this->traces[] = [
            'startX' => $this->x + sin($this->yaw) * 0.5 - cos($this->yaw) * (0.14 + $lateral),
            'startY' => $this->y + 0.18,
            'startZ' => $this->z + cos($this->yaw) * 0.5 + sin($this->yaw) * (0.14 + $lateral),
            'endX' => $enemy['x'], 'endY' => $enemy['y'] + $height, 'endZ' => $enemy['z'],
            'x' => $enemy['x'], 'y' => $enemy['y'] + $height, 'z' => $enemy['z'],
            'life' => $life, 'totalLife' => $life, 'type' => $type,
        ];
    }

    private function findTarget(float $range, float $cone): ?int
    {
        return $this->findTargets($range, $cone, 1)[0] ?? null;
    }

    /** @return list<int> */
    private function findTargets(float $range, float $cone, int $limit): array
    {
        $candidates = [];
        foreach ($this->enemies as $id => $enemy) {
            $d = hypot($enemy['x'] - $this->x, $enemy['z'] - $this->z);
            if ($d > $range) continue;
            $angle = abs(self::angleDelta(atan2($enemy['x'] - $this->x, $enemy['z'] - $this->z), $this->yaw));
            if ($angle > $cone) continue;
            $score = $angle * 7 + $d * 0.14;
            if ($this->lineClear([$this->x, $this->y + 0.35, $this->z], [$enemy['x'], $enemy['y'] + 0.15, $enemy['z']])) {
                $candidates[$id] = $score;
            }
        }
        asort($candidates);
        return array_slice(array_map('intval', array_keys($candidates)), 0, max(1, $limit));
    }

    private function firePulse(): void
    {
        $targets = [];
        foreach ($this->enemies as $id => $enemy) {
            if (hypot($enemy['x'] - $this->x, $enemy['z'] - $this->z) <= 3.6 + $this->pulseRank * 0.35 &&
                $this->lineClear([$this->x, $this->y, $this->z], [$enemy['x'], $enemy['y'], $enemy['z']])) $targets[] = $id;
        }
        if ($targets === []) { $this->pulseClock = 0.20; return; }
        foreach ($targets as $id) $this->damage($id, 30 + $this->pulseRank * 14);
        $this->pulse = 0.25;
        $this->pulseClock = max(1.0, 2.4 - $this->pulseRank * 0.15 - $this->hasteRank * 0.04);
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
        if (count($this->corpses) >= self::MAX_VISUAL_CORPSES) array_shift($this->corpses);
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
        $this->nextXp = 6 + ($this->level - 1) * 4;
        $this->syncPrimaryStage();

        $weaponPool = $this->weaponUpgradePool();
        $this->choices = [];
        if ($weaponPool !== []) {
            $index = (int)floor($this->random() * count($weaponPool));
            $this->choices[] = $weaponPool[$index];
            array_splice($weaponPool, $index, 1);
        }
        $pool = ['POWER', 'HASTE', 'MAGNET', 'VITALITY'];
        if (!$this->pulseUnlocked || $this->pulseRank < 3) $pool[] = 'PULSE';
        $pool = array_merge($pool, $weaponPool);
        while (count($this->choices) < 3 && $pool !== []) {
            $index = (int)floor($this->random() * count($pool));
            $this->choices[] = $pool[$index];
            array_splice($pool, $index, 1);
        }
        $this->state = 'upgrade';
    }

    /** @return list<string> */
    private function weaponUpgradePool(): array
    {
        $pool = [];
        if ($this->level >= 3 && $this->flameRank < 3) $pool[] = 'FLAMETHROWER';
        if ($this->level >= 3 && $this->shurikenRank < 3) $pool[] = 'SHURIKEN';
        return $pool;
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
