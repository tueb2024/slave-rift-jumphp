<?php

namespace App\Game;

/** Single spawn-time wave curve; weapon bonuses never raise enemy health. */
final class DifficultyDirector
{
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
            'runner' => ['health' => 24, 'speed' => 2.38, 'damage' => 10, 'radius' => 0.23, 'threat' => 0.70],
            'enforcer' => ['health' => 72, 'speed' => 1.02, 'damage' => 13, 'radius' => 0.27, 'threat' => 1.45],
            'brute' => ['health' => 144, 'speed' => 0.84, 'damage' => 32, 'radius' => 0.40, 'threat' => 2.80],
            default => ['health' => 48, 'speed' => 1.25, 'damage' => 14, 'radius' => 0.34, 'threat' => 1.00],
        };
    }

    /** Round the shared HP unit once so the 1:2:3:6 weights remain exact. */
    public static function enemyHealthFor(string $type, float $scale): int
    {
        $unit = max(1, (int)round(24 * $scale));
        return $unit * intdiv(self::enemyBaseProfile($type)['health'], 24);
    }

    public static function primaryDpsEstimate(int $level, int $manualDamageRank = 0, ?int $stage = null): float
    {
        $stage ??= Run::primaryStageForLevel($level);
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
        return 1.0 + min(20, max(0, $wave - 1)) * 0.025;
    }

    public static function damageScaleFor(float $powerIndex, int $wave, int $level): float
    {
        return 1.0 + min(20, max(0, $wave - 1)) * 0.015;
    }

    public static function speedScaleFor(int $wave, int $level): float
    {
        return 1.0 + min(20, max(0, $wave - 1)) * 0.003;
    }

    // Kept for callers of the old director. Profiles now apply exactly once at spawn.
    public function beforeStep(Run $run): void {}
    public function afterStep(Run $run): void {}

    /** @return array{primaryDps:float,powerIndex:float,healthScale:float,damageScale:float,speedScale:float} */
    public function snapshot(Run $run): array
    {
        $damageRank = $this->readInt($run, 'damageRank');
        $manualDamageRank = $damageRank;
        $primaryDps = self::primaryDpsEstimate($run->level, $manualDamageRank, $run->primaryStage);
        $baselineDps = self::primaryDpsEstimate(1, 0);
        $pulseRank = $this->readInt($run, 'pulseRank');
        $hasteRank = $this->readInt($run, 'hasteRank');
        $secondaryFactor = 1.0
            + $run->flameRank * 0.07
            + $run->rocketRank * 0.06
            + $pulseRank * 0.04
            + $hasteRank * 0.015;
        $powerIndex = max(1.0, ($primaryDps / max(1.0, $baselineDps)) * $secondaryFactor);
        return [
            'primaryDps' => $primaryDps,
            'powerIndex' => $powerIndex,
            'healthScale' => $run->mode->enemyHealthScale($run->wave),
            'damageScale' => $run->mode->damageScale($run->wave),
            'speedScale' => self::speedScaleFor($run->wave, $run->level),
        ];
    }

    private function readInt(Run $run, string $name): int
    {
        return (int)$this->property($name)->getValue($run);
    }

    private function property(string $name): \ReflectionProperty
    {
        if (!isset(self::$privateProperties[$name])) {
            self::$privateProperties[$name] = new \ReflectionProperty(Run::class, $name);
        }
        return self::$privateProperties[$name];
    }
}
