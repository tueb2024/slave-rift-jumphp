<?php
namespace Tests;
use App\Game\{Run,BrushMapFactory,RoomModule,LevelAssembler,LevelGraph,CampaignRun,Navigation,TreasureDirector};
use PHPUnit\Framework\TestCase;

final class PursuitNavigationTest extends TestCase
{
    private function fixture(): Run
    {
        $m=new RoomModule('pursuit');$m->addBox(-10,-1,-10,10,0,10);
        // Two rooms joined by the opening beyond the north end of this wall.
        $m->addBox(-.3,0,-10,.3,3,5);
        return new Run(BrushMapFactory::fromGenerated($m->solids,[[-4,.7,0]],[-4,.7,0],0));
    }
    private function enemy(Run $r,float $x,float $z,string $type='runner',float $y=.55): int
    {
        (new \ReflectionMethod(Run::class,'spawnEnemyAt'))->invoke($r,$x,$y,$z,$type);
        $id=array_key_last($r->enemies);$r->enemies[$id]['spawnDelay']=0;return $id;
    }
    private function tick(Run $r,int $frames): void
    {
        $method=new \ReflectionMethod(Run::class,'stepEnemies');
        for($i=0;$i<$frames;$i++){$r->time+=1/30;$method->invoke($r,1/30);
            foreach($r->enemies as $e)$this->assertFalse($r->collides($e['x'],$e['y'],$e['z'],$e['radius'],.55));
        }
    }
    public function testSurvivorRemembersPlayerAndFindsOpeningAfterLosingSight(): void
    {
        $r=$this->fixture();$id=$this->enemy($r,-4,3);$this->tick($r,12);
        self::assertTrue($r->enemies[$id]['pursuing']);
        $r->x=4;$r->z=0;
        self::assertFalse($r->lineClear([-4,.8,3],[4,1,0]));
        $this->tick($r,900);$e=$r->enemies[$id];
        self::assertLessThan(1,hypot($e['x']-$r->x,$e['z']-$r->z));
        self::assertTrue($e['pursuing']);self::assertLessThan(200,$r->navigation->searches);
    }
    public function testEnforcerDoesNotParkWithinRangeBehindWallOrShootThroughIt(): void
    {
        $r=$this->fixture();$id=$this->enemy($r,-4,3,'enforcer');$this->tick($r,12);
        $r->x=4;$r->z=0;$health=$r->health;$this->tick($r,60);
        self::assertSame($health,$r->health);$this->tick($r,900);
        $e=$r->enemies[$id];self::assertTrue($r->lineClear([$e['x'],$e['y']+.28,$e['z']],[$r->x,$r->y+.35,$r->z]));
    }
    public function testCrowdPassesDoorAndNoSurvivorIsLost(): void
    {
        $r=$this->fixture();
        for($i=0;$i<12;$i++)$this->enemy($r,-7+($i%3)*1.4,1+intdiv($i,3)*1.2);
        $this->tick($r,12);$r->x=4;$r->z=0;$this->tick($r,2400);
        self::assertCount(12,$r->enemies);
        foreach($r->enemies as $e)self::assertGreaterThan(.3,$e['x']);
    }
    public function testEnemiesClimbAndDescendRealProceduralStairs(): void
    {
        foreach([true,false] as $up){
            $m=\App\Game\ModuleLibrary::get($up?LevelGraph::STAIR_UP:LevelGraph::STAIR_DOWN);
            $r=new Run(BrushMapFactory::fromGenerated($m->solids,[[0,($up?2.7:-2.7)+.7,5.2]],[0,.7,0],0));
            $id=$this->enemy($r,0,-4,'brute',($up?.45:-.45)+.55);
            $r->enemies[$id]['pursuing']=true;$this->tick($r,1200);
            self::assertGreaterThan(3,$r->enemies[$id]['z']);
            self::assertEqualsWithDelta(($up?2.7:-2.7)+.55,$r->enemies[$id]['y'],.01);
        }
    }
    public function testSearchCannotInventPathAcrossGapOrExceedBudget(): void
    {
        $m=new RoomModule('gap');$m->addBox(-4,-1,-4,-1,0,4);$m->addBox(1,-1,-4,4,0,4);
        $n=new Navigation(BrushMapFactory::fromGenerated($m->solids,[],[-3,.7,0],0));
        self::assertSame([],$n->path([-3,0,0],[3,0,0],.4));
        self::assertLessThanOrEqual(Navigation::MAX_EXPANSIONS,$n->expansions);
    }
    public function testFinalRoomKeepsSurvivorsAndWaitsForTheirElimination(): void
    {
        $level=(new LevelAssembler)->assemble(new LevelGraph(33771,6));$r=new Run($level->map);$r->setObjective($level,1);$r->encounters->clear(PHP_INT_MAX);$r->navigation->setBlockers([]);
        $p=$level->map->spawnPoints[0];$id=$this->enemy($r,$p[0],$p[2],'runner',$p[1]-.2);
        $r->enemies[$id]['pursuing']=true;
        $anchor=$level->bossAnchor;$r->x=$anchor['x'];$r->y=$anchor['y'];$r->z=$anchor['z'];
        (new \ReflectionMethod(Run::class,'stepObjective'))->invoke($r,.03);
        self::assertArrayHasKey($id,$r->enemies);self::assertSame(TreasureDirector::FINAL_WAVE,$r->objective->state);
        $r->enemies=[];$r->remaining=0;(new \ReflectionMethod(Run::class,'stepObjective'))->invoke($r,.03);
        self::assertTrue($r->boss->active);
    }
    public function testEveryDailyMapRemainsReachableInBothDirectionsForLargestMob(): void
    {
        $c=CampaignRun::daily('2026-11-05');
        for($stage=1;$stage<=4;$stage++){
            $r=$c->run;$r->encounters->clear(PHP_INT_MAX);$r->navigation->setBlockers([]);$start=$r->map->spawnPoints[0];$end=$c->level->bossAnchor;
            foreach([[[$start[0],$start[1]-.75,$start[2]],[$end['x'],$end['y']-.75,$end['z']]],
                     [[$end['x'],$end['y']-.75,$end['z']],[$start[0],$start[1]-.75,$start[2]]]] as [$from,$to]){
                $visited=0;
                while(hypot($from[0]-$to[0],$from[2]-$to[2])>1 && $visited++<120){
                    $path=$r->navigation->path($from,$to,.425);
                    self::assertNotEmpty($path,"Map $stage: unreachable portal from ".json_encode($from));
                    foreach($path as $p){
                        self::assertNotNull($r->navigation->traverse($from,$p,.425));$from=$p;
                    }
                }
                self::assertLessThan(120,$visited,"Map $stage: portal cycle");
            }
            $r->state='won';if($stage<4)$c->advance();
        }
    }

    public function testSearchWorkIsSharedAcrossFramesAndDeadActorsAreCancelled(): void
    {
        $r=$this->fixture();$n=$r->navigation;$live=[];
        for($id=1;$id<=12;$id++){$live[$id]=true;$n->request($id,[-5,0,0],[5,0,0],.4);}
        $before=$n->expansions;$n->pump($live);
        self::assertLessThanOrEqual(Navigation::EXPANSIONS_PER_FRAME,$n->expansions-$before);
        $before=$n->expansions;$n->pump([]);
        self::assertSame($before,$n->expansions,'Removed actors must not consume search work');
    }

    public function testLargeMobCanReachPlayerStandingTightAgainstDoorFrame(): void
    {
        $c=CampaignRun::daily('2026-11-05');$r=$c->run;
        $path=$r->navigation->path([2.445,0,8.894],[-.6875,-.001,13.8345],.365);
        self::assertNotEmpty($path);self::assertSame(0,$r->navigation->failed);
        $end=$path[array_key_last($path)];self::assertLessThan(.65,hypot($end[0]+.6875,$end[2]-13.8345));
    }

    public function testAnActualSurvivorTraversesAllFourDailyMaps(): void
    {
        $c=CampaignRun::daily('2026-11-05');
        for($stage=1;$stage<=4;$stage++){
            $r=$c->run;$r->encounters->clear(PHP_INT_MAX);$r->navigation->setBlockers([]);$r->objective=null;$start=$r->map->spawnPoints[0];
            $id=$this->enemy($r,$start[0],$start[2],'runner',$start[1]-.2);$r->enemies[$id]['pursuing']=true;
            $end=$c->level->bossAnchor;$r->x=$end['x'];$r->y=$end['y'];$r->z=$end['z'];
            $step=new \ReflectionMethod(Run::class,'stepEnemies');
            for($i=0;$i<12000;$i++){
                $r->time+=.05;$step->invoke($r,.05);$e=$r->enemies[$id];
                self::assertFalse($r->collides($e['x'],$e['y'],$e['z'],$e['radius'],.55));
                if(hypot($e['x']-$r->x,$e['z']-$r->z)<1)break;
            }
            self::assertLessThan(12000,$i,"Survivor stranded on daily map $stage");
            self::assertSame(0,$r->navigation->failed);self::assertArrayHasKey($id,$r->enemies);
            $r->state='won';if($stage<4)$c->advance();
        }
    }

    public function testSlowReactionBotPassesWaypointsWithoutOscillating(): void
    {
        $r=$this->fixture();$r->remaining=0;$r->intermission=1000;$r->yaw=0;
        $forward=$strafe=0.0;
        for($i=0;$i<1800;$i++){
            if($i%20===0){
                $p=$r->navigationTarget(['x'=>4,'y'=>.7,'z'=>0]);
                $dx=$p['x']-$r->x;$dz=$p['z']-$r->z;$norm=max(1,hypot($dx,$dz));
                $strafe=-$dx/$norm*.55;$forward=$dz/$norm*.55;
            }
            $r->step(1/30,$forward,$strafe,0);
            if(hypot($r->x-4,$r->z)<.6)break;
        }
        self::assertLessThan(.6,hypot($r->x-4,$r->z));
    }

    public function testImmobileMobReplansAndResumesWhenUnblocked(): void
    {
        $r=$this->fixture();$id=$this->enemy($r,-4,3);$r->enemies[$id]['speed']=0;
        $this->tick($r,120);self::assertGreaterThan(0,$r->navigationRecoveries);
        $r->enemies[$id]['speed']=2.38;$r->x=4;$r->z=0;$this->tick($r,900);
        self::assertLessThan(1,hypot($r->enemies[$id]['x']-4,$r->enemies[$id]['z']));
    }

}
