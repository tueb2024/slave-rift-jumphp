<?php
/** Full simulations use legal upgrades, aiming and navigation. No combat stat cheats. */
require __DIR__.'/balance-sim.php';
use App\Game\{CampaignRun,GameMode,XpCurve,RhythmClock};
$rows=[];$seeds=isset($argv[1])?array_map('intval',explode(',',$argv[1])):[1,42,1337];
foreach($seeds as $seed)foreach(['moyen','bon'] as $profile){
    $c=new CampaignRun($seed,new XpCurve());$clock=new RhythmClock();$maps=[];
    do{
        $r=$c->run;$start=$r->time;
        $result=simulateProfile($profile,$r->mode->seed,1200.,'route',$r->mode,$r->xpCurve,$r,$clock);
        $maps[]=['map'=>$c->stage,'duration'=>$r->time-$start,'bossHp'=>$r->mode->bossHealth,'bossMoved'=>$r->bossDistanceMoved,'coverSeconds'=>$r->bossCoverTime,'result'=>$result];
    }while($c->advance());
    $rows[]=['seed'=>$seed,'profile'=>$profile,'state'=>$c->state(),'maps'=>$maps];
    fwrite(STDERR,"$seed $profile {$c->state()} map {$c->stage}\n");
    if(isset($argv[2]))file_put_contents($argv[2],json_encode($rows,JSON_PRETTY_PRINT)."\n");
}
echo json_encode(['scope'=>'Legal-input policies, 1200 simulated seconds per map; not human win-rate predictions.','runs'=>$rows],JSON_PRETTY_PRINT)."\n";
