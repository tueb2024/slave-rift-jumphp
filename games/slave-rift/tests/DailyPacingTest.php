<?php
namespace App\Tests;

use App\Game\{CampaignRun,GameMode,Run};
use PHPUnit\Framework\TestCase;

final class DailyPacingTest extends TestCase
{
    public function testOpeningWaitsForKillsInsteadOfStackingOrDiscardingSurvivors(): void
    {
        $r=CampaignRun::daily('2026-11-05')->run;
        $r->health=100000; // Pressure-only fixture, without firing or upgrades.
        for($i=0;$i<600;$i++)$r->step(.05,0,0,0);
        self::assertSame(1,$r->wave);
        self::assertSame(4,$r->spawned);
        self::assertCount(4,$r->enemies);
        self::assertSame(6,$r->remaining);
        $survivors=array_keys($r->enemies);$victim=array_shift($survivors);
        (new \ReflectionMethod(Run::class,'damage'))->invoke($r,$victim,10000);
        for($i=0;$i<60;$i++)$r->step(.05,0,0,0);
        self::assertSame(5,$r->spawned);
        self::assertSame(5,$r->remaining);
        self::assertCount(4,$r->enemies);
        foreach($survivors as $id)self::assertArrayHasKey($id,$r->enemies);
    }

    public function testFirstWeaponUpgradeUnlocksAFasterOpeningCadence(): void
    {
        $m=GameMode::daily('2026-11-05');
        self::assertSame(0,$m->primaryStageForKills(7));
        self::assertSame(1,$m->primaryStageForKills(8));
        self::assertLessThan($m->spawnInterval(1,7),$m->spawnInterval(1,8));
        self::assertSame(1,$m->spawnBatch(1));
        self::assertGreaterThan(0,$m->intermissionSeconds());
    }

    public function testLaterMapsIncreasePressureAndKeepEveryWeaponReachable(): void
    {
        $kills=0;$previousCap=0;$previousBudget=0;$previousInterval=INF;
        foreach(range(1,4) as $s){
            $m=GameMode::daily('2026-11-05',$s);$budget=$m->finalWaveSize;
            for($w=1;$w<=$m->waveCount();$w++){
                $budget+=$m->waveBudget($w);
                self::assertGreaterThanOrEqual($m->enemyLimit(1),$m->enemyLimit($w));
                self::assertLessThanOrEqual(20,$m->enemyLimit($w));
            }
            self::assertGreaterThan($previousBudget,$budget);
            self::assertGreaterThan($previousCap,$m->enemyLimit(1));
            self::assertLessThan($previousInterval,$m->spawnInterval(1));
            $previousCap=$m->enemyLimit(1);$previousBudget=$budget;$previousInterval=$m->spawnInterval(1);$kills+=$budget;
        }
        self::assertSame(6,$m->primaryStageForKills($kills));
        self::assertSame(0,GameMode::daily('2026-11-05')->primaryStageForKills(0));
    }
}
