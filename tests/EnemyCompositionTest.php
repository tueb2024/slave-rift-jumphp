<?php

namespace App\Tests;

use App\Game\DifficultyDirector;
use App\Game\Run;
use PHPUnit\Framework\TestCase;

final class EnemyCompositionTest extends TestCase
{
    public function testFourMobClassesHaveStrictlyIncreasingBaseHealth(): void
    {
        $health = [
            DifficultyDirector::enemyBaseProfile('runner')['health'],
            DifficultyDirector::enemyBaseProfile('guardian')['health'],
            DifficultyDirector::enemyBaseProfile('enforcer')['health'],
            DifficultyDirector::enemyBaseProfile('brute')['health'],
        ];
        self::assertSame([24, 52, 74, 180], $health);
        self::assertLessThan($health[1], $health[0]);
        self::assertLessThan($health[2], $health[1]);
        self::assertLessThan($health[3], $health[2]);
    }

    public function testLaterWavesShiftWeightTowardResistantClasses(): void
    {
        $early = Run::enemyWeightsForWave(1);
        $middle = Run::enemyWeightsForWave(6);
        $late = Run::enemyWeightsForWave(12);

        self::assertEqualsWithDelta(1.0, array_sum($early), 0.00001);
        self::assertEqualsWithDelta(1.0, array_sum($middle), 0.00001);
        self::assertEqualsWithDelta(1.0, array_sum($late), 0.00001);
        self::assertGreaterThan($early['enforcer'], $middle['enforcer']);
        self::assertGreaterThan($middle['enforcer'], $late['enforcer']);
        self::assertGreaterThan($middle['brute'], $late['brute']);
        self::assertLessThan($early['runner'], $middle['runner']);
        self::assertLessThan($middle['runner'], $late['runner']);
    }
}
