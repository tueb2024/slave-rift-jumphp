<?php
namespace App\Tests;

use App\Game\{CampaignRun,GameMode,HordeArena,Run,TreasureDirector,XpCurve};
use PHPUnit\Framework\TestCase;

final class NormalCampaignTest extends TestCase
{
    public function testPlayContractGrowsGeometryEncountersAndBudgetsOnEveryMap(): void
    {
        foreach ([1,42,1337,65520] as $seed) {
            $campaign=new CampaignRun($seed,new XpCurve());$area=0.;$budget=0;$cap=0;
            foreach (range(1,4) as $stage) {
                $run=$campaign->run;$mode=$run->mode;
                self::assertTrue($mode->isProgressiveNormal());
                self::assertSame($stage,$mode->campaignStage);
                self::assertSame([12,20,30,44][$stage-1],$campaign->geometry['mainRooms']);
                self::assertGreaterThan($area,$campaign->geometry['footprint']);$area=$campaign->geometry['footprint'];
                self::assertSame($mode->waveCount(),count($run->encounters->gates));
                self::assertSame([3,4,5,6][$stage-1],$mode->waveCount());
                $total=$mode->finalWaveSize;
                foreach (range(1,$mode->waveCount()) as $wave) $total+=$mode->waveBudget($wave);
                self::assertGreaterThan($budget,$total);$budget=$total;
                self::assertGreaterThan($cap,$mode->enemyLimit(1));$cap=$mode->enemyLimit(1);
                self::assertLessThanOrEqual(28,$mode->enemyLimit(100));
                self::assertSame(XpCurve::LEGACY_MILESTONES,$run->xpCurve->milestones);
                self::assertSame(XpCurve::threshold(12),$mode->xpThreshold(12));
                $run->state='won';
                if ($stage<4) self::assertTrue($campaign->advance());
                else self::assertSame('won',$campaign->state());
            }
        }
    }

    public function testProgressiveNormalCanSpawnNearbyAndCannotRushPastEncounters(): void
    {
        foreach (range(1,4) as $stage) {
            $campaign=new CampaignRun(42,new XpCurve());
            for($i=1;$i<$stage;$i++){$campaign->run->state='won';$campaign->advance();}
            $run=$campaign->run;$mode=$run->mode;$run->health=100000;
            // Spawn through the actual procedural-room eligibility and collision checks.
            for($i=0;$i<100;$i++)$run->step(.05,0,0,0);
            self::assertGreaterThan(0,$run->spawned,'No local threats on map '.$stage);
            self::assertLessThanOrEqual($mode->enemyLimit(1),count($run->enemies));
            foreach($run->enemies as $enemy){
                self::assertTrue($run->encounters->inZone($enemy['x'],$enemy['y'],$enemy['z'],1));
            }
            $bounds=$run->objective->finalRoomBounds;
            $run->x=($bounds['min'][0]+$bounds['max'][0])/2;
            $run->z=($bounds['min'][2]+$bounds['max'][2])/2;
            $run->y=$bounds['min'][1]+.75;
            (new \ReflectionMethod($run,'stepObjective'))->invoke($run,.05);
            self::assertSame(TreasureDirector::EXPLORING,$run->objective->state);
            $run->remaining=0;$run->enemies=[];$run->wave=$mode->waveCount();
            $run->encounters->clear($run->wave);$run->navigation->setBlockers([]);
            (new \ReflectionMethod($run,'stepWaves'))->invoke($run,.05);
            self::assertSame(0.,$run->intermission,'No ghost wave after the last encounter');
            (new \ReflectionMethod($run,'stepObjective'))->invoke($run,.05);
            self::assertSame(TreasureDirector::FINAL_WAVE,$run->objective->state);
            self::assertSame($mode->finalWaveSize,$run->remaining);
        }
    }

    public function testDailyHordeAndExplicitLegacyFixturesKeepTheirRules(): void
    {
        self::assertTrue(GameMode::normal()->isProgressiveNormal());
        self::assertFalse(GameMode::fixed(42)->hasFiniteEncounters());
        self::assertSame(48,GameMode::fixed(42)->enemyLimit(1));
        self::assertSame(200,GameMode::horde(42)->enemyLimit(100));
        $daily=CampaignRun::daily('2026-11-08');$total=0;
        foreach(range(1,4) as $stage){
            $mode=$daily->run->mode;self::assertFalse($mode->isProgressiveNormal());
            $total+=$mode->finalWaveSize;
            foreach(range(1,$mode->waveCount()) as $wave)$total+=$mode->waveBudget($wave);
            $daily->run->state='won';if($stage<4)$daily->advance();
        }
        self::assertSame(520,$total);
    }
}
