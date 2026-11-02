<?php
namespace App\Tests;
use App\Game\XpCurve;
use App\Game\Run;
use App\Game\HordeArena;
use PHPUnit\Framework\TestCase;

final class XpCurveTest extends TestCase
{
    public function testClassRewardsAndEveryNinthHealingDropArePreserved(): void
    {
        foreach([XpCurve::CURRENT=>[2,2,2,5],XpCurve::WEIGHTED=>[1,2,3,6],XpCurve::ACCESSIBLE=>[2,3,4,6]]as$model=>$values){
            $curve=new XpCurve($model);$run=new Run(HordeArena::build(),42,null,$curve);
            for($i=1;$i<=18;$i++){
                $type=['runner','guardian','enforcer','brute'][($i-1)%4];
                self::assertSame($values[($i-1)%4],$curve->reward($type));
                (new \ReflectionMethod($run,'spawnEnemyAt'))->invoke($run,0,.55,2,$type);
                (new \ReflectionMethod($run,'damage'))->invoke($run,array_key_first($run->enemies),100000);
                $p=$run->pickups[array_key_last($run->pickups)];
                self::assertSame($i%9===0?'health':'xp',$p['kind']);
                self::assertSame($i%9===0?18:$curve->reward($type),$p['value']);
            }
        }
    }

    public function testActualThresholdsAreMonotonicAndMatchTheAuditedTotals(): void
    {
        foreach([4=>19,8=>54,12=>100,16=>157,20=>225,24=>305]as$level=>$xp){
            self::assertSame($xp,XpCurve::totalForLevel($level));
            self::assertSame($level,XpCurve::levelForTotal($xp));
            self::assertSame($level-1,XpCurve::levelForTotal($xp-1));
        }
    }
}
