<?php
require __DIR__.'/test-bootstrap.php';
use App\Game\{CampaignRun,GameMode,Run};
$rows=[];
foreach([25,50] as $population){
    $c=CampaignRun::daily('2026-11-05');$r=new Run($c->level->map,33771,GameMode::horde(33771));
    $r->setObjective($c->level);$r->objective=null;$r->remaining=0;$r->intermission=3600;$r->health=100000;
    $spawn=new ReflectionMethod(Run::class,'spawnEnemyAt');
    // Real collision-safe positions across the first rooms. Weapons disabled only in this CPU fixture.
    foreach($c->level->rooms as $room){
        if(!$room['mainPath']||$room['depth']>3)continue;$b=$room['bounds'];
        for($x=$b['min'][0]+1;$x<$b['max'][0]-1;$x+=1.2)for($z=$b['min'][2]+1;$z<$b['max'][2]-1;$z+=1.2){
            if(count($r->enemies)>=$population)break 2;
            $h=$r->navigation->support($x,$z,.42,.58);
            if($h===null||$r->collides($x,$h+.55,$z,.42,.55))continue;
            $spawn->invoke($r,$x,$h+.55,$z,['runner','guardian','enforcer','brute'][count($r->enemies)%4]);
        }
    }
    if(count($r->enemies)!==$population)throw new RuntimeException('Cannot place benchmark population');
    foreach($r->enemies as &$e)$e['pursuing']=true;unset($e);
    $end=$c->level->bossAnchor;$r->x=$end['x'];$r->y=$end['y'];$r->z=$end['z'];$times=[];$maxExpanded=0;
    for($i=0;$i<60*90;$i++){
        $expanded=$r->navigation->expansions;$t=microtime(true);$r->step(1/60,0,0,0);$times[]=(microtime(true)-$t)*1000;
        $maxExpanded=max($maxExpanded,$r->navigation->expansions-$expanded);
        if(count($r->enemies)!==$population)throw new RuntimeException('Population changed');
    }
    foreach($r->enemies as $e){
        if($e['x']<=15)throw new RuntimeException('Crowd stranded before first staircase: enemy '.$e['id']);
        if($r->collides($e['x'],$e['y'],$e['z'],$e['radius'],.55))throw new RuntimeException('Mob entered solid geometry');
    }
    if($r->navigation->failed>0)throw new RuntimeException('Pursuit search failed in connected daily map');
    sort($times);
    $rows[]=['enemies'=>$population,'frames'=>count($times),'meanStepMs'=>array_sum($times)/count($times),
        'p95StepMs'=>$times[(int)floor((count($times)-1)*.95)],'maxStepMs'=>max($times),'maxExpansionsPerFrame'=>$maxExpanded,
        'failedSearches'=>$r->navigation->failed,'recoveries'=>$r->navigationRecoveries,'peakPhpBytes'=>memory_get_peak_usage(true),
        'positions'=>array_map(static fn($e)=>array_intersect_key($e,array_flip(['id','type','x','y','z','pursuing'])) ,array_values($r->enemies))];
}
echo json_encode(['scope'=>'90s pursuit across real daily rooms, doors and stairs; fixed population, no weapons, high-health stationary target. CPU only, not difficulty or GPU benchmark.','seed'=>33771,'runs'=>$rows],JSON_PRETTY_PRINT)."\n";
