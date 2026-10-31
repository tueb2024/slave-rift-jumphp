<?php

namespace App\Tests;

use App\Game\Run;
use PHPUnit\Framework\TestCase;

final class PrimaryProgressionTest extends TestCase
{
    public function testPrimaryWeaponStagesFollowLevelMilestones(): void
    {
        self::assertSame(0, Run::primaryStageForLevel(1));
        self::assertSame(1, Run::primaryStageForLevel(4));
        self::assertSame(2, Run::primaryStageForLevel(8));
        self::assertSame(3, Run::primaryStageForLevel(12));
        self::assertSame(4, Run::primaryStageForLevel(16));
        self::assertSame(5, Run::primaryStageForLevel(20));
        self::assertSame(6, Run::primaryStageForLevel(24));
        self::assertSame(6, Run::primaryStageForLevel(99));
    }
}
