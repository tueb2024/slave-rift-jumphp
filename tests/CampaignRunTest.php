<?php
namespace App\Tests;
use App\Game\CampaignRun;
use App\Game\XpCurve;
use PHPUnit\Framework\TestCase;

final class CampaignRunTest extends TestCase
{
    public function testFourLevelProgressionKeepsArsenalAndOnlyFinishesAfterLevelFour(): void
    {
        $c=new CampaignRun(42,new XpCurve(XpCurve::ACCESSIBLE));$seeds=[];
        for($stage=1;$stage<=4;$stage++){
            self::assertSame($stage,$c->stage);$seeds[]=$c->run->mode->seed;
            self::assertNotNull($c->run->boss);self::assertNotNull($c->run->objective);
            self::assertEqualsWithDelta($c->baseArea*(2**($stage-1)),$c->geometry['footprint'],.001);
            self::assertLessThanOrEqual(32,$c->geometry['mainRooms']);
            $state=$c->run->progressionSnapshot();$state['level']=12;$state['xp']=3;$state['flameRank']=2;$state['rocketRank']=3;$state['pulseUnlocked']=true;$state['health']=20;
            $c->run->restoreProgression($state,0);
            self::assertSame(3,$c->run->primaryStage);
            $c->run->state='won';
            self::assertSame($stage===4?'won':'level_complete',$c->state());
            if($stage<4){self::assertTrue($c->advance());self::assertSame(12,$c->run->level);self::assertSame(3,$c->run->xp);
                self::assertSame(2,$c->run->flameRank);self::assertSame(3,$c->run->rocketRank);self::assertTrue($c->run->pulseUnlocked);
                self::assertSame(48,$c->run->health);self::assertSame(3,$c->run->primaryStage);}
            else self::assertFalse($c->advance());
        }
        self::assertCount(4,array_unique($seeds));
    }

    public function testCannotSkipABossByAdvancingWhilePlayingOrDead(): void
    {
        $c=new CampaignRun(42);self::assertFalse($c->advance());$c->run->state='dead';self::assertFalse($c->advance());
    }
    public function testPressureUsesBoundedIndependentAxes(): void
    {
        $previous=null;
        foreach(range(1,4)as$stage){
            $mode=\App\Game\GameMode::campaignStage(42,$stage);
            self::assertLessThanOrEqual(1.4,$mode->enemyHealthScale(1));
            self::assertLessThanOrEqual(4000,$mode->bossHealth);
            if($previous!==null){
                self::assertGreaterThan($previous->compositionWave(1),$mode->compositionWave(1));
                self::assertGreaterThan($previous->waveBudget(1),$mode->waveBudget(1));
                self::assertGreaterThan($previous->damageScale(1),$mode->damageScale(1));
                self::assertLessThan($previous->spawnInterval(1),$mode->spawnInterval(1));
                self::assertGreaterThan($previous->bossPressure(),$mode->bossPressure());
            }
            $previous=$mode;
        }
        $campaign=new CampaignRun(42);
        self::assertSame(XpCurve::CAMPAIGN_MILESTONES,$campaign->xpCurve->milestones);
    }
}
