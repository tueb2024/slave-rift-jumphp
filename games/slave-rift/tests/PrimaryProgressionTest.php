<?php

namespace App\Tests;

use App\Game\Run;
use PHPUnit\Framework\TestCase;

final class PrimaryProgressionTest extends TestCase
{
    public function testPrimaryWeaponStagesFollowLevelMilestones(): void
    {
        self::assertSame(-1, Run::primaryStageForLevel(1));
        self::assertSame(-1, Run::primaryStageForLevel(3));
        $previous=0;$lastGap=0;
        foreach(Run::PRIMARY_LEVELS as $stage=>$minimum){
            self::assertSame($stage,Run::primaryStageForLevel($minimum));
            self::assertSame($stage-1,Run::primaryStageForLevel($minimum-1));
            self::assertGreaterThan($lastGap,$minimum-$previous);$lastGap=$minimum-$previous;$previous=$minimum;
        }
        self::assertSame(6, Run::primaryStageForLevel(99));
    }
}
