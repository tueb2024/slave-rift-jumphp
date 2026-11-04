<?php
require __DIR__.'/test-bootstrap.php';
use App\Game\CampaignRun;
$out=[];
foreach(['2026-11-04','2026-11-05','2026-11-06']as$date){
    $c=CampaignRun::daily($date);
    foreach(range(1,4)as$stage){$r=$c->run;$r->health=100000;$peak=0;$start=microtime(true);
        for($i=0;$i<900;$i++){$r->step(1/60,0,0,0);$peak=max($peak,count($r->enemies));}
        $out[]=['date'=>$date,'map'=>$stage,'spawned15s'=>$r->spawned,'peak'=>$peak,'cap'=>$r->mode->enemyLimit(1),'waveBudget'=>$r->mode->waveBudget(1),'waves'=>$r->mode->waveCount(),'cpuMs'=>round((microtime(true)-$start)*1000,2)];
        $r->state='won';if($stage<4)$c->advance();
    }
}
echo json_encode(['scope'=>'Stationary invulnerable density fixture in real daily maps, not a player win-rate benchmark.','runs'=>$out],JSON_PRETTY_PRINT)."\n";
