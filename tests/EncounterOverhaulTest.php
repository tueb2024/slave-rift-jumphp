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
        foreach([BossController::WALL,BossController::DYNAMIC] as $kind){
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
        $r=$this->arena();$r->boss->active=false;self::assertFalse($r->fireShotgun());
        $r=$this->arena();$r->state='upgrade';self::assertFalse($r->fireShotgun());
        // Arena tool is independent of XP ladder and exclusive power selection.
        $r=$this->arena();$r->equipSecondary('emp');$r->fireShotgun();self::assertSame(0,$r->primaryStage);self::assertSame('emp',$r->selectedSecondary());
    }
    public function testBossLaunchesVisibleMovingProjectilesWhichHitOrCanBeDodged(): void
    {
        $r=$this->arena();$this->invoke($r,'launchBossProjectiles',['x'=>0.,'y'=>.7,'z'=>0.]);
        self::assertCount(1,$r->bossProjectiles);$before=$r->bossProjectiles[0]['z'];
        $this->invoke($r,'stepBossProjectiles',.05);self::assertLessThan($before,$r->bossProjectiles[0]['z']);
        for($i=0;$i<25;$i++)$this->invoke($r,'stepBossProjectiles',.05);
        self::assertLessThan(100,$r->health);self::assertSame(1,$r->bossProjectileHits);
        $r=$this->arena();$this->invoke($r,'launchBossProjectiles',['x'=>0.,'y'=>.7,'z'=>0.]);$r->x=2;
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
    public function testFastAndTankThreatsAreDistinctAndTelegraphed(): void
    {
        $fast=DifficultyDirector::enemyBaseProfile('runner');$medium=DifficultyDirector::enemyBaseProfile('guardian');$tank=DifficultyDirector::enemyBaseProfile('brute');
        self::assertGreaterThan($medium['speed']*2,$fast['speed']);self::assertGreaterThan($medium['health']*3,$tank['health']);self::assertLessThan($medium['speed'],$tank['speed']);
        $r=$this->arena();$r->boss=null;$this->invoke($r,'spawnEnemyAt',0.,.55,.7,'runner');$id=array_key_first($r->enemies);
        self::assertGreaterThan(0,$r->enemies[$id]['spawnDelay']);$r->enemies[$id]['spawnDelay']=0;$r->enemies[$id]['cooldown']=0;
        $r->step(.05,0,0,0);self::assertGreaterThan(0,$r->enemies[$id]['windup']);self::assertSame(100,$r->health);
    }
}
