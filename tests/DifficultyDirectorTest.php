<?php

namespace App\Tests;

use App\Game\DifficultyDirector;
use PHPUnit\Framework\TestCase;

final class DifficultyDirectorTest extends TestCase
{
    public function testWeaponMilestonesHaveStrictlyIncreasingEffectiveDps(): void
    {
        $levels = [1, 4, 8, 12, 16, 20, 24];
        $previous = 0.0;
        foreach ($levels as $level) {
            $dps = DifficultyDirector::primaryDpsEstimate($level);
            self::assertGreaterThan($previous, $dps, 'DPS must rise at level ' . $level);
            $previous = $dps;
        }
    }

    public function testEnemySpritesMapToDistinctThreatProfiles(): void
    {
        $runner = DifficultyDirector::enemyBaseProfile('runner');
        $guardian = DifficultyDirector::enemyBaseProfile('guardian');
        $enforcer = DifficultyDirector::enemyBaseProfile('enforcer');
        $brute = DifficultyDirector::enemyBaseProfile('brute');

        self::assertLessThan($guardian['health'], $runner['health']);
        self::assertLessThan($enforcer['health'], $guardian['health']);
        self::assertLessThan($brute['health'], $enforcer['health']);
        self::assertGreaterThan($guardian['speed'], $runner['speed']);
        self::assertGreaterThan($brute['damage'], $guardian['damage']);
        self::assertGreaterThan($enforcer['threat'], $guardian['threat']);
    }

    public function testHealthScalingRewardsPlayerPowerInsteadOfCancellingIt(): void
    {
        $baseline = DifficultyDirector::healthScaleFor(1.0, 1);
        $doublePower = DifficultyDirector::healthScaleFor(2.0, 1);
        self::assertSame(1.0, $baseline);
        self::assertGreaterThan(1.0, $doublePower);
        self::assertLessThan(2.0, $doublePower);
    }

    public function testWavesKeepIncreasingPressureAtEqualPlayerPower(): void
    {
        $early = DifficultyDirector::healthScaleFor(3.0, 3);
        $late = DifficultyDirector::healthScaleFor(3.0, 12);
        self::assertGreaterThan($early, $late);
        self::assertGreaterThan(
            DifficultyDirector::damageScaleFor(3.0, 3, 8),
            DifficultyDirector::damageScaleFor(3.0, 12, 8),
        );
        self::assertGreaterThan(
            DifficultyDirector::speedScaleFor(3, 8),
            DifficultyDirector::speedScaleFor(12, 8),
        );
    }
}
