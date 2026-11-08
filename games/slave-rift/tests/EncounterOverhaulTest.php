<?php
namespace App\Tests;
use App\Game\{Run,BossController,DifficultyDirector,HordeArena,GameMode,CampaignRun,LevelAssembler,LevelGraph,EncounterGates,Navigation,RoomModule,BrushMapFactory};
use PHPUnit\Framework\TestCase;

final class EncounterOverhaulTest extends TestCase
{
    private function invoke(object $o,string $m,...$args): mixed{return (new \ReflectionMethod($o,$m))->invoke($o,...$args);}
    private function arena(): Run
    {
        $r=new Run(HordeArena::build(),42,GameMode::horde(42));
        $r->remaining=0;$r->intermission=1000;$r->x=0;$r->y=.7;$r->z=0;$r->yaw=0;
        $r->boss=new BossController(BossController::WALL,2200,6);
        $r->bossPosition=['x'=>0.,'y'=>1.,'z'=>5.];
        $r->boss->armOrbs([['x'=>0.,'y'=>1.,'z'=>5.]],1);$r->boss->activate();$r->boss->damageOrb(0,1);
        return $r;
    }
    public function testEveryNonShotgunSourceIsRejectedEvenForLethalDamage(): void
    {
        foreach([BossController::WALL] as $kind){
            $b=new BossController($kind,2200);$b->activate();$b->vulnerable=true;
            foreach(['primary','pulse','flame','sniper','rockets','artillery','emp','explosion',''] as $source){
                self::assertSame(0,$b->damage(999999,$source));self::assertSame(2200,$b->health);
            }
            self::assertSame(0,$b->damage(999999));self::assertFalse($b->defeated);
            self::assertSame(560,$b->damage(560,'shotgun'));
        }
    }
    public function testPrimaryAndAreaDamageCannotBypassCoreRule(): void
    {
        $r=$this->arena();
        self::assertTrue($this->invoke($r,'fireAtBoss',99999,6,24.,1.));
        $this->invoke($r,'explode',[0.,1.,5.],99999,10.);
        self::assertSame(2200,$r->boss->health);
        self::assertTrue($r->fireShotgun());self::assertSame(1640,$r->boss->health);
        self::assertFalse($r->fireShotgun());self::assertSame(1,$r->shotgunSerial);
        for($i=0;$i<24;$i++)$r->step(.05,0,0,0);
        self::assertTrue($r->fireShotgun());self::assertSame(2,$r->shotgunSerial);
    }
    public function testShotgunRequiresAimRangeWindowAndActiveEncounter(): void
    {
        $r=$this->arena();$r->yaw=M_PI;$r->fireShotgun();self::assertSame(2200,$r->boss->health);
        $r=$this->arena();$r->bossPosition['z']=8;$r->fireShotgun();self::assertSame(2200,$r->boss->health);
        $r=$this->arena();$r->boss->vulnerable=false;$r->fireShotgun();self::assertSame(2200,$r->boss->health);
        $r=$this->arena();$r->boss->active=false;self::assertTrue($r->fireShotgun());self::assertSame(2200,$r->boss->health);
        $r=$this->arena();$r->state='upgrade';self::assertFalse($r->fireShotgun());
        // Arena tool is independent of XP ladder and exclusive power selection.
        $r=$this->arena();$r->equipSecondary('emp');$r->fireShotgun();self::assertSame(-1,$r->primaryStage);self::assertSame('emp',$r->selectedSecondary());
    }
    public function testBossLaunchesVisibleMovingProjectilesWhichHitOrCanBeDodged(): void
    {
        $r=$this->arena();$this->invoke($r,'launchBossProjectiles',['x'=>0.,'y'=>.7,'z'=>0.]);
        self::assertCount(7,$r->bossProjectiles);$before=$r->bossProjectiles[0]['z'];
        $this->invoke($r,'stepBossProjectiles',.05);self::assertLessThan($before,$r->bossProjectiles[0]['z']);
        for($i=0;$i<25;$i++)$this->invoke($r,'stepBossProjectiles',.05);
        self::assertLessThan(100,$r->health);self::assertSame(1,$r->bossProjectileHits);
        $r=$this->arena();$this->invoke($r,'launchBossProjectiles',['x'=>0.,'y'=>.7,'z'=>0.]);$r->x=8;
        for($i=0;$i<130;$i++)$this->invoke($r,'stepBossProjectiles',.05);
        self::assertSame(100,$r->health);self::assertSame([],$r->bossProjectiles);
    }
    public function testProjectileVolumeCollidesWithWallAndStunPausesTimers(): void
    {
        $m=new RoomModule('test');$m->addBox(-10,-1,-10,10,0,10);$m->addBox(-2,0,2,2,3,2.1);
        $r=new Run(BrushMapFactory::fromGenerated($m->solids,[],[0,.7,0],0),42);
        $r->boss=new BossController(BossController::DYNAMIC,2200);$r->boss->activate();$r->bossPosition=['x'=>0.,'y'=>1.,'z'=>5.];
        $this->invoke($r,'launchBossProjectiles',['x'=>0.,'y'=>.7,'z'=>0.]);
        for($i=0;$i<25;$i++)$this->invoke($r,'stepBossProjectiles',.05);
        self::assertSame(100,$r->health);self::assertSame([],$r->bossProjectiles);
        $r=$this->arena();$this->invoke($r,'launchBossProjectiles',['x'=>0.,'y'=>.7,'z'=>0.]);$r->bossStun=2;
        $r->bossWarning=['x'=>0.,'y'=>.7,'z'=>0.,'time'=>.9];$p=$r->bossProjectiles;$t=$r->boss->time;
        $this->invoke($r,'updateBossCombat',.05);self::assertSame($p,$r->bossProjectiles);self::assertSame($t,$r->boss->time);self::assertSame(.9,$r->bossWarning['time']);
        $r->bossStun=0;$this->invoke($r,'updateBossCombat',.05);self::assertNotSame($p,$r->bossProjectiles);
        $r->boss->phase=3;for($i=0;$i<30;$i++)$this->invoke($r,'launchBossProjectiles',['x'=>0.,'y'=>.7,'z'=>0.]);self::assertCount(Run::MAX_BOSS_PROJECTILES,$r->bossProjectiles);
    }
    public function testGatesUseRealSeededConnectorsBlockCollisionAndOpenMonotonically(): void
    {
        $signatures=[];
        foreach([1,42,1337,65520] as $seed){
            $l=(new LevelAssembler())->assemble(new LevelGraph($seed,10));
            $g=new EncounterGates($l->rooms,4);$same=new EncounterGates($l->rooms,4);self::assertSame($g->gates,$same->gates);
            self::assertCount(4,$g->gates);$nav=new Navigation($l->map);$nav->setRooms($l->rooms);$nav->setBlockers($g->blockers());
            $last=-1;
            foreach($g->gates as $gate){
                self::assertGreaterThan($last,$gate['depth']);$last=$gate['depth'];[$x,$y,$z]=$gate['position'];
                self::assertTrue($nav->collides($x,$y+.7,$z,.28,.7));
                $room=array_values(array_filter($l->rooms,static fn($r)=>$r['id']===$gate['roomId']))[0];
                self::assertTrue($room['mainPath']);self::assertNotSame(LevelGraph::BOSS_ROOM,$room['type']);
                self::assertTrue($g->clear($gate['wave']));$nav->setBlockers($g->blockers());
                self::assertFalse($nav->collides($x,$y+.7,$z,.28,.7));
                self::assertFalse($g->clear($gate['wave']));
            }
            self::assertSame([],$g->blockers());$signatures[]=array_column($g->gates,'position');
        }
        self::assertNotSame($signatures[0],$signatures[1]);
    }
    public function testDailySpawnsRemainOnAccessibleSideAndCapRetainsBudget(): void
    {
        foreach(['2026-11-05','2026-11-06','2026-11-07'] as $date){
            $r=CampaignRun::daily($date)->run;$r->health=100000;
            for($i=0;$i<400;$i++)$r->step(.05,0,0,0);
            self::assertNotEmpty($r->enemies);self::assertLessThanOrEqual(4,count($r->enemies));
            self::assertSame(10,$r->remaining+count($r->enemies));
            foreach($r->enemies as $e)self::assertTrue($r->encounters->inZone($e['x'],$e['y'],$e['z'],1));
            self::assertSame(0,$r->encounters->opened);
        }
    }
    public function testGateNeverOpensBeforeBothSpawnBudgetAndSurvivorsAreCleared(): void
    {
        $r=CampaignRun::daily('2026-11-06')->run;$r->health=100000;
        for($i=0;$i<60;$i++)$r->step(.05,0,0,0);
        self::assertNotEmpty($r->enemies);
        $r->remaining=0;$this->invoke($r,'stepWaves',.05);self::assertSame(0,$r->encounters->opened);
        foreach(array_keys($r->enemies) as $id)$this->invoke($r,'damage',$id,100000);
        $r->remaining=1;$this->invoke($r,'stepWaves',.05);self::assertSame(0,$r->encounters->opened);
        foreach(array_keys($r->enemies) as $id)$this->invoke($r,'damage',$id,100000);
        $r->remaining=0;$this->invoke($r,'stepWaves',.05);self::assertSame(1,$r->encounters->opened);
        $fresh=CampaignRun::daily('2026-11-06')->run;self::assertSame(0,$fresh->encounters->opened);
        self::assertFalse($fresh->encounters->gates[0]['open']);
    }
    public function testProceduralEncounterInputsRemainDeterministic(): void
    {
        $a=CampaignRun::daily('2026-11-07')->run;$b=CampaignRun::daily('2026-11-07')->run;
        foreach([$a,$b] as $r){$r->health=100000;for($i=0;$i<240;$i++)$r->step(.05,.4,.1,.03);}
        self::assertSame([$a->x,$a->y,$a->z,$a->enemies,$a->remaining,$a->encounters->gates],[$b->x,$b->y,$b->z,$b->enemies,$b->remaining,$b->encounters->gates]);
    }
    public function testFastAndTankThreatsAreDistinctAndTelegraphed(): void
    {
        $fast=DifficultyDirector::enemyBaseProfile('runner');$medium=DifficultyDirector::enemyBaseProfile('guardian');$tank=DifficultyDirector::enemyBaseProfile('brute');
        self::assertGreaterThan($medium['speed']*2,$fast['speed']);self::assertGreaterThan($medium['health']*3,$tank['health']);self::assertLessThan($medium['speed'],$tank['speed']);
        $r=$this->arena();$r->boss=null;$this->invoke($r,'spawnEnemyAt',0.,.55,.7,'runner');$id=array_key_first($r->enemies);
        self::assertGreaterThan(0,$r->enemies[$id]['spawnDelay']);$r->enemies[$id]['spawnDelay']=0;$r->enemies[$id]['cooldown']=0;
        $r->step(.05,0,0,0);self::assertGreaterThan(0,$r->enemies[$id]['windup']);self::assertSame(100,$r->health);
    }
}
