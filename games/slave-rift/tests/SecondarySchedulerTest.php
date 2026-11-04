<?php
namespace App\Tests;
use App\Game\SecondaryScheduler;
use App\Game\HordeArena;
use App\Game\Run;
use PHPUnit\Framework\TestCase;

final class SecondarySchedulerTest extends TestCase
{
    public function testSimultaneousReadyWeaponsAreQueuedWithoutLosingTheirTurn(): void
    {
        $s=new SecondaryScheduler;
        self::assertSame('pulse',$s->step(0,['pulse','flame','rockets'],['pulse','flame','rockets'],.25));
        self::assertSame(['flame','rockets'],$s->pending());
        self::assertNull($s->step(.24,['flame','rockets'],['flame','rockets'],.25));
        self::assertSame('flame',$s->step(.01,['flame','rockets'],['flame','rockets'],.25));
        self::assertSame('rockets',$s->step(.25,['rockets'],['rockets'],.25));
    }

    public function testWaitingForATargetDoesNotRestartCooldownOrBlockOtherWeapons(): void
    {
        $s=new SecondaryScheduler;
        self::assertSame('rockets',$s->step(0,['pulse','rockets'],['rockets'],.25));
        self::assertSame(['pulse'],$s->pending());
        self::assertNull($s->step(.25,['pulse'],[],.25));
        self::assertSame('pulse',$s->step(0,['pulse'],['pulse'],.25));
    }

    public function testLegacyMultiPowerStateDispatchesOnlyOneSelectedPower(): void
    {
        $run=new Run(HordeArena::build(),42);$run->remaining=0;$run->intermission=3600;
        $run->flameRank=1;$run->rocketRank=1;$run->pulseUnlocked=true;
        foreach(['pulseClock','rocketClock','flameCooldownClock']as$name)(new \ReflectionProperty($run,$name))->setValue($run,0.0);
        (new \ReflectionMethod($run,'spawnEnemyAt'))->invoke($run,0.0,.55,2.0,'brute');
        $run->enemies[array_key_first($run->enemies)]['health']=100000;
        $events=[];for($i=0;$i<60;$i++){$run->step(1/60,0,0,0);array_push($events,...$run->drainSecondaryEvents());}
        self::assertSame(['flame'],array_unique(array_column($events,'id')));
        self::assertCount(1,$run->secondaryInventory());
    }
}
