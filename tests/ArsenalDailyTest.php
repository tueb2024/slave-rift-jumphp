<?php
namespace App\Tests;
use App\Game\{Run,HordeArena,GameMode,CampaignRun,TreasureDirector};
use App\Gfx\{WeaponRenderer,PowerRenderer};
use PHPUnit\Framework\TestCase;
final class ArsenalDailyTest extends TestCase
{
    private function invoke($run,$method,...$args): mixed{return (new \ReflectionMethod($run,$method))->invoke($run,...$args);}
    public function testRuntimeSpritesHaveDeclaredTransparentFrames(): void
    {
        foreach(WeaponRenderer::textures()+PowerRenderer::textures()as$path){
            $p=dirname(__DIR__).'/'.$path;self::assertFileExists($p);$info=getimagesize($p);self::assertSame([768,128],array_slice($info,0,2));
            $bytes=file_get_contents($p);self::assertContains(ord($bytes[25]),[3,6]);if(ord($bytes[25])===3)self::assertStringContainsString('tRNS',$bytes);
        }
        self::assertCount(8,WeaponRenderer::textures());self::assertCount(5,PowerRenderer::textures());
    }
    public function testDailyHasFiniteIncreasingBudgetsAndKillBasedWeapons(): void
    {
        $total=0;
        foreach(range(1,4)as$stage){$m=GameMode::daily('2026-11-04',$stage);self::assertSame($stage+2,$m->waveCount());
            self::assertGreaterThanOrEqual(.55,$m->spawnInterval(1));self::assertLessThanOrEqual(2,$m->spawnBatch(1));
            for($w=1;$w<=$m->waveCount();$w++)$total+=$m->waveBudget($w);$total+=$m->finalWaveSize;
        }
        self::assertSame(520,$total);$m=GameMode::daily('2026-11-04');$r=new Run(HordeArena::build(),42,$m);
        foreach([0=>0,8=>1,26=>2,63=>3,125=>4,218=>5,354=>6]as$kills=>$stage){$r->restoreProgression(['kills'=>$kills,'level'=>1],0);self::assertSame($stage,$r->primaryStage);}
        self::assertSame(12,$r->nextXp);self::assertSame(69,$m->xpThreshold(20));
    }
    public function testDailySpawnsSustainedPressureWithoutExceedingCap(): void
    {
        foreach([1,4]as$stage){$m=GameMode::daily('2026-11-04',$stage);$r=new Run(HordeArena::build(),42,$m);$r->health=100000;
            for($i=0;$i<300;$i++)$r->step(.05,0,0,0);
            self::assertGreaterThanOrEqual($m->enemyLimit(1),$r->spawned);self::assertLessThanOrEqual($m->enemyLimit(1),count($r->enemies));
        }
    }
    public function testEmpStopsNearbyEnemiesThenTheyRecoverAndSharesTheDuo(): void
    {
        $r=new Run(HordeArena::build(),42);$r->remaining=0;$r->intermission=1000;
        $r->equipSecondary('emp');$r->empRank=1;
        $this->invoke($r,'spawnEnemyAt',$r->x+2,.55,$r->z,'guardian');$id=array_key_first($r->enemies);
        self::assertTrue($this->invoke($r,'fireEmp'));$before=[$r->enemies[$id]['x'],$r->enemies[$id]['z']];
        for($i=0;$i<20;$i++)$r->step(.05,0,0,0);
        self::assertSame($before,[$r->enemies[$id]['x'],$r->enemies[$id]['z']]);
        for($i=0;$i<40;$i++)$r->step(.05,0,0,0);
        self::assertNotSame($before,[$r->enemies[$id]['x'],$r->enemies[$id]['z']]);
        $r->equipSecondary('rockets');$r->rocketRank=1;self::assertSame(1,$r->empRank);self::assertSame(['emp','rockets'],array_column($r->secondaryInventory(),'id'));self::assertFalse($r->equipSecondary('sniper'));
    }
    public function testDailyCannotSkipExplorationWavesByRushingFinalRoom(): void
    {
        $c=CampaignRun::daily('2026-11-04');$r=$c->run;$b=$r->objective->finalRoomBounds;
        $r->x=($b['min'][0]+$b['max'][0])/2;$r->z=($b['min'][2]+$b['max'][2])/2;$r->y=$b['min'][1]+.75;
        $this->invoke($r,'stepObjective',.05);self::assertSame(TreasureDirector::EXPLORING,$r->objective->state);
        $r->remaining=0;$r->enemies=[];$r->wave=$r->mode->waveCount();$r->encounters->clear($r->wave);$r->navigation->setBlockers([]);
        $this->invoke($r,'stepObjective',.05);self::assertSame(TreasureDirector::FINAL_WAVE,$r->objective->state);self::assertSame(8,$r->remaining);
    }
    public function testHomingRocketUpdatesTargetAndHitsGeometry(): void
    {
        $r=new Run(HordeArena::build(),42);$r->equipSecondary('rockets');$r->rocketRank=1;
        $this->invoke($r,'spawnEnemyAt',$r->x,.55,$r->z+7,'brute');$id=array_key_first($r->enemies);$r->yaw=0;
        self::assertTrue($this->invoke($r,'fireExplosive','rockets'));
        $r->enemies[$id]['x']+=1;$this->invoke($r,'stepProjectiles',.05);
        self::assertSame($r->enemies[$id]['x'],$r->projectiles[0]['tx']);self::assertGreaterThan(0,$r->projectiles[0]['age']);
    }
}

