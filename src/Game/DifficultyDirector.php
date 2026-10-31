<?php

namespace App\Game;

/**
 * Applies player-power-aware enemy scaling without letting difficulty fully cancel upgrades.
 *
 * Enemy HP follows player power with a 0.58 elasticity: doubling effective player power
 * only multiplies enemy HP by about 1.50. Wave pressure still stacks on top, so the run
 * gets harder while every weapon milestone remains a real advantage.
 */
final class DifficultyDirector
{
    /** @var array<int,true> */
    private array $balancedEnemies = [];
    private int $injectedDamageRank = 0;

    /** @var array<string,\ReflectionProperty> */
    private static array $privateProperties = [];

    /**
     * Compensation ranks make the existing rhythmic weapon ladder strictly increase in
     * effective DPS without rewriting Run's firing semantics. Manual POWER ranks are
     * preserved and stack on top of this stage bonus.
     */
    public static function virtualDamageRankForStage(int $stage): int
    {
        return match (max(0, min(6, $stage))) {
            0, 1 => 0,
            2, 3 => 3,
            4 => 7,
            5 => 10,
            default => 6,
        };
    }

    /** @return array{health:int,speed:float,damage:int,radius:float,threat:float} */
    public static function enemyBaseProfile(string $type): array
    {
        return match ($type) {
            'runner' => ['health' => 24, 'speed' => 2.08, 'damage' => 7, 'radius' => 0.23, 'threat' => 0.70],
            'enforcer' => ['health' => 74, 'speed' => 1.02, 'damage' => 8, 'radius' => 0.27, 'threat' => 1.45],
            'brute' => ['health' => 180, 'speed' => 0.84, 'damage' => 20, 'radius' => 0.40, 'threat' => 2.80],
            default => ['health' => 52, 'speed' => 1.25, 'damage' => 10, 'radius' => 0.30, 'threat' => 1.00],
        };
    }

    public static function primaryDpsEstimate(int $level, int $manualDamageRank = 0): float
    {
        $stage = Run::primaryStageForLevel($level);
        $shotsPerBeat = match ($stage) {
            0 => 1.0,
            1 => 2.0,
            2 => 3.0,
            3, 4 => 6.0,
            5 => 4.0,
            default => 8.0,
        };
        $baseDamage = match ($stage) {
            0, 1 => 38.0,
            2, 3 => 14.0,
            4 => 10.0,
            default => 13.0,
        };
        $damageRank = max(0, $manualDamageRank) + self::virtualDamageRankForStage($stage);
        $damage = $baseDamage * (1.0 + $damageRank * 0.30);
        $bpm = RhythmClock::BPM_BASE + max(0, $level - 1) * RhythmClock::BPM_PER_LEVEL;
        return $damage * $shotsPerBeat * $bpm / 60.0;
    }

    public static function healthScaleFor(float $powerIndex, int $wave): float
    {
        $power = max(1.0, $powerIndex);
        $waveScale = 1.0 + max(0, $wave - 1) * 0.045;
        return min(6.5, $waveScale * pow($power, 0.58));
    }

    public static function damageScaleFor(float $powerIndex, int $wave, int $level): float
    {
        $powerTerm = log(max(1.0, $powerIndex), 2.0) * 0.025;
        return min(2.25, 1.0 + max(0, $wave - 1) * 0.032 + max(0, $level - 1) * 0.010 + $powerTerm);
    }

    public static function speedScaleFor(int $wave, int $level): float
    {
        return min(1.18, 1.0 + max(0, $wave - 1) * 0.004 + max(0, $level - 1) * 0.002);
    }

    /** Apply the stage-specific weapon compensation before Run resolves a simulation step. */
    public function beforeStep(Run $run): void
    {
        $current = $this->readInt($run, 'damageRank');
        $manualRank = max(0, $current - $this->injectedDamageRank);
        $this->injectedDamageRank = self::virtualDamageRankForStage($run->primaryStage);
        $this->writeInt($run, 'damageRank', $manualRank + $this->injectedDamageRank);
    }

    /** Balance only newly spawned enemies; existing enemies never heal when the player levels. */
    public function afterStep(Run $run): void
    {
        $liveIds = [];
        foreach ($run->enemies as $id => &$enemy) {
            $id = (int)$id;
            $liveIds[$id] = true;
            if (isset($this->balancedEnemies[$id])) continue;

            $profile = self::enemyBaseProfile((string)($enemy['type'] ?? 'guardian'));
            $balance = $this->snapshot($run);
            $health = max(1, (int)round($profile['health'] * $balance['healthScale']));
            $enemy['health'] = $health;
            $enemy['maxHealth'] = $health;
            $enemy['speed'] = $profile['speed'] * $balance['speedScale'];
            $enemy['damage'] = max(1, (int)round($profile['damage'] * $balance['damageScale']));
            $enemy['radius'] = $profile['radius'];
            $enemy['threat'] = $profile['threat'];
            $enemy['balanceLevel'] = $run->level;
            $enemy['balanceWave'] = $run->wave;
            $this->balancedEnemies[$id] = true;
        }
        unset($enemy);
        $this->balancedEnemies = array_intersect_key($this->balancedEnemies, $liveIds);
    }

    /** @return array{primaryDps:float,powerIndex:float,healthScale:float,damageScale:float,speedScale:float} */
    public function snapshot(Run $run): array
    {
        $damageRank = $this->readInt($run, 'damageRank');
        $manualDamageRank = max(0, $damageRank - $this->injectedDamageRank);
        $primaryDps = self::primaryDpsEstimate($run->level, $manualDamageRank);
        $baselineDps = self::primaryDpsEstimate(1, 0);
        $pulseRank = $this->readInt($run, 'pulseRank');
        $hasteRank = $this->readInt($run, 'hasteRank');
        $secondaryFactor = 1.0
            + $run->flameRank * 0.07
            + $run->shurikenRank * 0.06
            + $pulseRank * 0.04
            + $hasteRank * 0.015;
        $powerIndex = max(1.0, ($primaryDps / max(1.0, $baselineDps)) * $secondaryFactor);
        return [
            'primaryDps' => $primaryDps,
            'powerIndex' => $powerIndex,
            'healthScale' => self::healthScaleFor($powerIndex, $run->wave),
            'damageScale' => self::damageScaleFor($powerIndex, $run->wave, $run->level),
            'speedScale' => self::speedScaleFor($run->wave, $run->level),
        ];
    }

    private function readInt(Run $run, string $name): int
    {
        return (int)$this->property($name)->getValue($run);
    }

    private function writeInt(Run $run, string $name, int $value): void
    {
        $this->property($name)->setValue($run, $value);
    }

    private function property(string $name): \ReflectionProperty
    {
        if (!isset(self::$privateProperties[$name])) {
            self::$privateProperties[$name] = new \ReflectionProperty(Run::class, $name);
        }
        return self::$privateProperties[$name];
    }
}
