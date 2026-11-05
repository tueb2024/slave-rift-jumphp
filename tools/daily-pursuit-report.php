<?php
/** Real daily campaign, three imperfect profiles, 30 minutes TOTAL per campaign. */
require __DIR__.'/balance-sim.php';
use App\Game\CampaignRun;
use App\Game\RhythmClock;
use App\Game\Run;
$date=$argv[1]??'2026-11-05';$rows=[];$navigationOnly=in_array('--navigation-only',$argv,true);
foreach(['faible','moyen','bon'] as $profile){
    $c=CampaignRun::daily($date);$clock=new RhythmClock();$maps=[];$wall=microtime(true);
    do{
        $r=$c->run;$start=$r->time;
        // Separate geometry/progression fixture, NEVER used as a difficulty result.
        if($navigationOnly)(new ReflectionProperty(Run::class,'invincible'))->setValue($r,3600.0);
        $result=simulateProfile($profile,$r->mode->seed,max(0,1800-$start),'route',$r->mode,$r->xpCurve,$r,$clock);
        $maps[]=['map'=>$c->stage,'duration'=>$r->time-$start,'result'=>$result];
    }while($r->time<1800&&$c->advance());
    $rows[]=['profile'=>$profile,'state'=>$c->state(),'cpuSeconds'=>microtime(true)-$wall,'maps'=>$maps];
    fwrite(STDERR,"$profile: {$c->state()} / map {$c->stage} / ".round($c->run->time,1)."s\n");
    if(isset($argv[2]))file_put_contents($argv[2],json_encode(['navigationOnly'=>$navigationOnly,'date'=>$date,'limitSeconds'=>1800,'seeds'=>$c->stageSeeds,'runs'=>$rows],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n");
}
echo json_encode(['navigationOnly'=>$navigationOnly,'date'=>$date,'limitSeconds'=>1800,'seeds'=>$c->stageSeeds,'scope'=>$navigationOnly?'Navigation/progression fixture: damage suppressed via invincibility, legal movement/weapons/upgrades. Not difficulty evidence.':'Actual daily campaign, legal inputs and upgrades, three imperfect profiles. CPU timings exclude rendering.','runs'=>$rows],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
