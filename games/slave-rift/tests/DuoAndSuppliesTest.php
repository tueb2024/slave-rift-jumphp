<?php
namespace App\Tests;
use App\Game\{Run,GameMode,HordeArena,CampaignRun,LevelGraph,LevelAssembler,XpCurve};
use PHPUnit\Framework\TestCase;

final class DuoAndSuppliesTest extends TestCase
{
    private function call($object,$method,...$args): mixed{return (new \ReflectionMethod($object,$method))->invoke($object,...$args);}
    public function testTwoChosenPowersArePermanentAndLaterLevelsOnlyOfferClassicBonuses(): void
    {
        $run=new Run(HordeArena::build(),42);
        foreach(['FLAMETHROWER','MINI_ROCKETS'] as $choice){$run->state='upgrade';$run->choices=[$choice];self::assertTrue($run->chooseUpgrade(0));}
        self::assertSame(['flame','rockets'],$run->secondaryIds());
        $run->flameActive=1.;$run->projectiles[]=['kind'=>'rockets'];
        self::assertFalse($run->equipSecondary('emp'));
        self::assertSame(1.,$run->flameActive);self::assertCount(1,$run->projectiles);
        $run->state='upgrade';$run->choices=['EMP'];self::assertFalse($run->chooseUpgrade(0));
        self::assertSame(['flame','rockets'],$run->secondaryIds());
        for($i=0;$i<25;$i++){
            $run->state='play';$run->xp=$run->nextXp;$this->call($run,'checkLevel');
            self::assertCount(3,$run->choices);
            foreach($run->choices as $choice)self::assertContains($choice,['POWER','HASTE','MAGNET','VITALITY']);
            self::assertTrue($run->chooseUpgrade(0));self::assertCount(2,$run->secondaryInventory());
        }
    }
    public function testBothPowersAndTheirCooldownsSurviveEveryNormalAndDailyMap(): void
    {
        foreach([new CampaignRun(42,new XpCurve()),CampaignRun::daily('2026-11-08')] as $campaign){
            $run=$campaign->run;$run->equipSecondary('flame');$run->equipSecondary('rockets');
            foreach(range(1,3) as $stage){
                $run=$campaign->run;$run->flameActive=1.1;
                (new \ReflectionProperty($run,'rocketClock'))->setValue($run,1.7);
                $run->state='won';self::assertTrue($campaign->advance());
                self::assertSame(['flame','rockets'],$campaign->run->secondaryIds());
                self::assertSame(1.1,$campaign->run->flameActive);
                self::assertSame(1.7,$campaign->run->secondaryInventory()[1]['remaining']);
            }
        }
    }
    public function testRoomPacksAreSeededInSmallRoomsAndAreNeverEvictedByEnemyXp(): void
    {
        foreach([1,42,1337] as $seed){
            $level=(new LevelAssembler())->assemble(new LevelGraph($seed,10));
            foreach([GameMode::fixed($seed),GameMode::daily('2026-11-08',1,$seed)] as $mode){
                $run=new Run($level->map,$seed,$mode);$run->setObjective($level);
                $copy=new Run($level->map,$seed,$mode);$copy->setObjective($level);
                self::assertSame($run->roomSupplies,$copy->roomSupplies);self::assertNotEmpty($run->roomSupplies);
                $rooms=array_column($level->rooms,null,'id');
                foreach($run->roomSupplies as $pack){
                    self::assertContains($rooms[$pack['roomId']]['type'],[LevelGraph::SMALL_ROOM,LevelGraph::BONUS]);
                    self::assertFalse($run->collides($pack['x'],$pack['y']+.45,$pack['z'],.28,.7));
                }
                $supplies=$run->roomSupplies;
                for($i=0;$i<100;$i++){
                    $this->call($run,'spawnEnemyAt',$run->x,.55,$run->z+2,'runner');
                    $this->call($run,'damage',array_key_first($run->enemies),100000);
                }
                self::assertSame($supplies,$run->roomSupplies);
                foreach($run->pickups as $drop)self::assertSame('xp',$drop['kind']);
            }
        }
    }
    public function testRoomHealthWaitsAtFullHpAndRequiresPhysicalCollectionOnTheSameFloor(): void
    {
        $campaign=new CampaignRun(42);$run=$campaign->run;$pack=$run->roomSupplies[0];$count=count($run->roomSupplies);
        $run->x=$pack['x'];$run->z=$pack['z'];$run->y=$pack['y']+.45;
        $this->call($run,'stepRoomSupplies');self::assertCount($count,$run->roomSupplies);
        $run->health=50;$run->y+=3.;$this->call($run,'stepRoomSupplies');self::assertSame(50,$run->health);
        $run->y-=3.;$this->call($run,'stepRoomSupplies');self::assertSame(66,$run->health);self::assertCount($count-1,$run->roomSupplies);
        self::assertSame([], (new Run(HordeArena::build(),42,GameMode::horde(42)))->roomSupplies);
    }
}
