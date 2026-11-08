<?php
namespace App\Tests;
use App\Game\{Run,GameMode,HordeArena,CampaignRun,RhythmClock,BossController,LevelGraph};
use PHPUnit\Framework\TestCase;
final class ManualArsenalBossTest extends TestCase
{
    public function testEveryModeStartsManualAndXpOnlyOffersWeaponsRatherThanGrantingThem(): void
    {
        foreach([GameMode::fixed(42),GameMode::horde(42),GameMode::campaignStage(42,1),GameMode::daily('2026-11-08')] as $mode){
            $r=new Run(HordeArena::build(),42,$mode);$r->remaining=0;$r->intermission=1000;
            (new \ReflectionMethod($r,'spawnEnemyAt'))->invoke($r,$r->x,.55,$r->z+3,'guardian');
            $clock=new RhythmClock();
            for($i=0;$i<30;$i++){$r->step(1/60,0,0,0,false,false,false,$clock->advance(1/60,$r->level));$clock->drain();}
            self::assertSame(-1,$r->primaryStage);self::assertSame(0,$r->shots);self::assertSame(0,$r->shotgunSerial);
            self::assertTrue($r->fireShotgun());self::assertSame(1,$r->shotgunSerial);
            $r->level=Run::PRIMARY_LEVELS[0];$r->state='upgrade';$r->choices=['PRIMARY'];
            self::assertTrue($r->chooseUpgrade(0));self::assertSame(0,$r->primaryStage);self::assertFalse($r->shotgunAvailable());
        }
    }
    public function testAllSevenAcquisitionsRespectIncreasingGatesAndPersistAcrossMaps(): void
    {
        $c=new CampaignRun(42);$r=$c->run;
        foreach(Run::PRIMARY_LEVELS as $stage=>$minimum){
            $r->level=$minimum-1;self::assertFalse($r->canUpgradePrimary());
            $r->level=$minimum;self::assertTrue($r->canUpgradePrimary());
            $r->state='upgrade';$r->choices=['PRIMARY'];self::assertTrue($r->chooseUpgrade(0));self::assertSame($stage,$r->primaryStage);
        }
        for($i=0;$i<3;$i++){$c->run->state='won';$c->advance();self::assertSame(6,$c->run->primaryStage);self::assertSame(6,$c->run->unlockedPrimaryStage);}
        self::assertSame(-1,(new CampaignRun(42))->run->primaryStage);
    }
    public function testHordeBossHealthKeepsGrowingBeyondTheSixthEncounter(): void
    {
        $r=new Run(HordeArena::build(),42,GameMode::horde(42));
        $step=new \ReflectionMethod($r,'stepHordeBoss');
        $r->wave=30;$step->invoke($r,.01);$sixth=$r->boss->maxHealth;
        $r->boss->defeated=true;$r->wave=35;$step->invoke($r,.01);
        self::assertGreaterThan($sixth,$r->boss->maxHealth);
        $p=$r->bossCoverPosition;
        self::assertFalse($r->collides($p['x'],$p['y'],$p['z'],.28,.7));
        self::assertFalse($r->lineClear([$p['x'],$p['y']+.3,$p['z']],array_values($r->bossPosition)));
    }
    public function testAllDamageSourcesHitMobileBossAndEachArenaHasAWalkableOccludedRefuge(): void
    {
        foreach(['primary','shotgun','flame','rockets','sniper','artillery','pulse'] as $source){
            $boss=new BossController(BossController::DYNAMIC,9000);$boss->activate();self::assertSame(100,$boss->damage(100,$source));
        }
        foreach([1,42,1337,65520] as $seed){
            $c=new CampaignRun($seed);
            foreach(range(1,4) as $stage){
                $r=$c->run;$r->encounters->clear(PHP_INT_MAX);$r->navigation->setBlockers([]);
                $cover=$r->bossCoverPosition;$peek=$r->bossPeekPosition;
                self::assertNotNull($cover);self::assertFalse($r->collides($cover['x'],$cover['y'],$cover['z'],.28,.7));
                self::assertFalse($r->lineClear([$cover['x'],$cover['y']+.3,$cover['z']],array_values($r->bossPosition)));
                self::assertNotEmpty($r->navigation->path([$peek['x'],$peek['y']-.7,$peek['z']],[$cover['x'],$cover['y']-.7,$cover['z']],.28,.7));
                $r->state='won';if($stage<4)$c->advance();
            }
        }
    }
}
