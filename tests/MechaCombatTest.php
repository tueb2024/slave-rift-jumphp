<?php
namespace App\Tests;
use App\Game\{Run,HordeArena,GameMode,RoomModule,BrushMapFactory,SecondaryScheduler,CampaignRun};
use App\Gfx\EnemyAtlas;
use PHPUnit\Framework\TestCase;
final class MechaCombatTest extends TestCase
{
    private function fixture(): Run{$r=new Run(HordeArena::build(),42);$r->remaining=0;$r->intermission=1000;$r->yaw=0;return $r;}
    private function invoke(object $r,string $m,...$args): mixed{return (new \ReflectionMethod($r,$m))->invoke($r,...$args);}
    private function spawn(Run $r,string $type,float $x,float $z): int{$this->invoke($r,'spawnEnemyAt',$x,.55,$z,$type);return array_key_last($r->enemies);}
    public function testAllClassesHaveOriginalOrientedAnimatedSheets(): void
    {
        foreach(['runner','guardian','enforcer','brute','siege']as$t){$sheet=EnemyAtlas::SHEETS[$t];self::assertStringStartsWith('assets/generated/',$sheet['path']);
            foreach(['idle','walk','attack','pain','death']as$state)for($d=0;$d<8;$d++)self::assertTrue(EnemyAtlas::valid($sheet,EnemyAtlas::frame($t,$state,7,$d)));
        }
        self::assertCount(5,array_unique(array_column(EnemyAtlas::SHEETS,'texture')));
    }
    public function testEnforcerLocksAimAndCanBeDodged(): void
    {
        $r=$this->fixture();$id=$this->spawn($r,'enforcer',0,8);$r->enemies[$id]['cooldown']=0;
        $r->step(.05,0,0,0);self::assertSame(100,$r->health);self::assertGreaterThan(0,$r->enemies[$id]['windup']);
        $r->x+=2;for($i=0;$i<14;$i++)$r->step(.05,0,0,0);self::assertSame(100,$r->health);
        $r=$this->fixture();$id=$this->spawn($r,'enforcer',0,8);$r->enemies[$id]['cooldown']=0;
        for($i=0;$i<16;$i++)$r->step(.05,0,0,0);self::assertLessThan(100,$r->health);
    }
    public function testBrutePreparesHeavyHitAndRunnerKeepsClosing(): void
    {
        $r=$this->fixture();$id=$this->spawn($r,'brute',0,1);$r->enemies[$id]['cooldown']=0;$r->step(.05,0,0,0);
        self::assertSame(100,$r->health);self::assertGreaterThan(0,$r->enemies[$id]['windup']);
        $runner=$this->spawn($r,'runner',4,4);$before=$r->enemies[$runner]['x'];for($i=0;$i<18;$i++)$r->step(.05,0,0,0);
        self::assertLessThan(100,$r->health);self::assertLessThan($before,$r->enemies[$runner]['x']);
    }
    public function testSniperPrioritisesVisibleEnforcerAndCreatesLaser(): void
    {
        $r=$this->fixture();$r->sniperRank=1;$a=$this->spawn($r,'guardian',0,5);$b=$this->spawn($r,'enforcer',1,14);
        self::assertTrue($this->invoke($r,'fireSniper'));self::assertArrayHasKey($a,$r->enemies);self::assertArrayNotHasKey($b,$r->enemies);
        self::assertSame('sniper',$r->traces[0]['type']);self::assertGreaterThan(0,$r->secondaryInventory()[0]['remaining']);
    }
    public function testExplosiveTravelsBeforeApplyingRealSplash(): void
    {
        $r=$this->fixture();$r->rocketRank=2;$a=$this->spawn($r,'brute',0,7);$b=$this->spawn($r,'brute',1,7);
        $hp=$r->enemies[$b]['health'];self::assertTrue($this->invoke($r,'fireMiniRockets'));self::assertSame($hp,$r->enemies[$b]['health']);self::assertCount(1,$r->projectiles);
        for($i=0;$i<40;$i++)$this->invoke($r,'stepProjectiles',.05);
        self::assertCount(0,$r->projectiles);self::assertLessThan($hp,$r->enemies[$b]['health']??0);self::assertLessThan($hp,$r->enemies[$a]['health']??0);
    }
    public function testWallStopsProjectileAndExplosionCannotReachHiddenTarget(): void
    {
        $room=new RoomModule('test');$room->addBox(-20,-1,-20,20,0,20);$room->addBox(-10,0,4,10,4,4.5);
        $map=BrushMapFactory::fromGenerated($room->solids,[[0,.75,0]],[0,.75,0],0);
        $r=new Run($map,42);$id=$this->spawn($r,'brute',0,6);$hp=$r->enemies[$id]['health'];
        $r->projectiles[]=['x'=>0.,'y'=>.8,'z'=>0.,'tx'=>0.,'ty'=>.8,'tz'=>6.,'kind'=>'rockets','age'=>0.,'damage'=>400,'radius'=>4.,'speed'=>18.];
        for($i=0;$i<5;$i++)$this->invoke($r,'stepProjectiles',.05);
        self::assertCount(0,$r->projectiles);self::assertSame($hp,$r->enemies[$id]['health']);self::assertLessThan(4.01,array_values($r->explosions)[0]['z']);
        self::assertFalse($this->invoke($r,'fireSniper'));
    }
    public function testContinuousFlameAndItsStateSurviveMapTransition(): void
    {
        $r=$this->fixture();$r->flameRank=2;$this->invoke($r,'startFlamethrower');$r->step(.05,0,0,0);
        self::assertNotEmpty($r->flameSegments);$active=$r->flameActive;$save=$r->progressionSnapshot();$r2=$this->fixture();$r2->restoreProgression($save,0);
        self::assertSame($active,$r2->flameActive);for($i=0;$i<60;$i++)$r2->step(.05,0,0,0);
        self::assertSame(0.0,$r2->flameActive);self::assertSame([],$r2->flameSegments);
    }
    public function testFiveSecondariesNeverBeginTogetherAndSerialize(): void
    {
        $r=$this->fixture();$r->flameRank=1;$r->rocketRank=1;$r->sniperRank=1;$r->artilleryRank=1;$r->pulseUnlocked=true;
        $this->spawn($r,'brute',0,2);$r->enemies[array_key_first($r->enemies)]['health']=100000;
        foreach(['sniperClock','artilleryClock','rocketClock','flameCooldownClock','pulseClock']as$p)(new \ReflectionProperty($r,$p))->setValue($r,0.0);
        $events=[];for($i=0;$i<150;$i++){$r->step(1/60,0,0,0);$events=array_merge($events,$r->drainSecondaryEvents());}
        self::assertGreaterThanOrEqual(5,count($events));for($i=1;$i<count($events);$i++)self::assertGreaterThanOrEqual($events[$i]['spacing']-1e-8,$events[$i]['time']-$events[$i-1]['time']);
        $copy=$this->fixture();$copy->restoreProgression($r->progressionSnapshot(),0);self::assertCount(5,$copy->secondaryInventory());
    }
    public function testHordeSpawnsOneBossPerFifthWaveAndBlocksAdvance(): void
    {
        $r=new Run(HordeArena::build(),42,GameMode::horde(42));$r->wave=5;$r->remaining=0;$r->step(.05,0,0,0);$boss=$r->boss;
        self::assertNotNull($boss);self::assertTrue($boss->active);for($i=0;$i<20;$i++)$r->step(.05,0,0,0);
        self::assertSame($boss,$r->boss);self::assertSame(0.0,$r->intermission);
        foreach([0,1,2]as$i)$boss->damageOrb($i,999);$boss->damage(99999);$r->step(.05,0,0,0);self::assertGreaterThan(0,$r->intermission);
        $r->wave=10;$r->intermission=0;$r->step(.05,0,0,0);self::assertNotSame($boss,$r->boss);self::assertGreaterThan($boss->maxHealth,$r->boss->maxHealth);
    }
    public function testDailyCampaignHasFourFixedSeedsAndPreservesProgression(): void
    {
        $c=CampaignRun::daily('2026-11-03');self::assertCount(4,array_unique($c->stageSeeds));$area=0;
        for($stage=1;$stage<=4;$stage++){
            self::assertSame(GameMode::DAILY,$c->run->mode->kind);self::assertGreaterThan($area,$c->geometry['footprint']);$area=$c->geometry['footprint'];
            self::assertNotNull($c->run->boss);$c->run->sniperRank=2;$c->run->artilleryRank=3;$c->run->time=100.*$stage;$c->run->health=20;$c->run->state='won';
            if($stage<4){self::assertSame('level_complete',$c->state());self::assertTrue($c->advance());self::assertSame(48,$c->run->health);self::assertSame(2,$c->run->sniperRank);self::assertSame(3,$c->run->artilleryRank);self::assertSame(100.*$stage,$c->run->time);}
            else{self::assertSame('won',$c->state());self::assertFalse($c->advance());}
        }
    }    public function testLargerSurfaceUsesFixedModulesOnPreviouslyFailingDays(): void
    {
        foreach(['2026-02-10','2026-02-27','2026-03-11','2026-03-13']as$date){
            $c=CampaignRun::daily($date);$area=0;
            foreach(range(1,4)as$stage){self::assertGreaterThan($area,$c->geometry['footprint']);self::assertEquals(1,$c->geometry['horizontalScale']);$area=$c->geometry['footprint'];$c->run->state='won';if($stage<4)self::assertTrue($c->advance());}
        }
    }

}
