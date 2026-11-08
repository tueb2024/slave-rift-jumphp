<?php
/** Representative-build combat fixtures. No invincibility or damage changes during a fight. */
require __DIR__.'/test-bootstrap.php';
use App\Game\{CampaignRun,Run,RhythmClock,TreasureDirector,XpCurve};
$rows=[];
foreach([1,42,1337] as $seed)foreach(range(1,4) as $stage)foreach(['exposed','strafe','cover'] as $policy){
    $campaign=new CampaignRun($seed,new XpCurve());
    for($i=1;$i<$stage;$i++){$campaign->run->state='won';$campaign->advance();}
    $r=$campaign->run;$r->encounters->clear(PHP_INT_MAX);$r->navigation->setBlockers([]);$r->enemies=[];$r->remaining=0;
    $r->objective->state=TreasureDirector::BOSS_READY;
    $r->level=[14,26,42,62][$stage-1];$r->primaryStage=$r->unlockedPrimaryStage=[1,3,4,6][$stage-1];(new ReflectionProperty($r,'damageRank'))->setValue($r,[3,7,12,18][$stage-1]);
    $r->health=$r->maxHealth=[100,130,160,180][$stage-1];
    $clock=new RhythmClock();$initial=$r->health;
    (new ReflectionMethod($r,'stepObjective'))->invoke($r,.01);
    $peek=$r->bossPeekPosition;$r->x=$peek['x'];$r->z=$peek['z'];$r->y=$peek['y'];$r->grounded=true;
    for($i=0;$i<30*240 && $r->state==='play' && !$r->boss->defeated;$i++){
        $goal=$peek;
        if($policy==='cover' && $r->boss->attackState!=='recover')$goal=$r->bossCoverPosition;
        if($policy==='strafe'){$goal['x']+=sin($r->time*1.4)*5;$goal['z']+=cos($r->time*1.4)*2;}
        $r->yaw=atan2($r->bossPosition['x']-$r->x,$r->bossPosition['z']-$r->z);
        $dx=$policy==='exposed'?0:$goal['x']-$r->x;$dz=$policy==='exposed'?0:$goal['z']-$r->z;$n=max(1.,hypot($dx,$dz));
        $f=(sin($r->yaw)*$dx+cos($r->yaw)*$dz)/$n;$s=(-cos($r->yaw)*$dx+sin($r->yaw)*$dz)/$n;
        $r->step(1/30,$f,$s,0,false,false,false,$clock->advance(1/30,$r->level));$clock->drain();
    }
    $row=['seed'=>$seed,'stage'=>$stage,'policy'=>$policy,'bossHp'=>$r->boss->maxHealth,'weapon'=>$r->primaryWeaponLabel(),'result'=>$r->boss->defeated?'won':$r->state,'seconds'=>round($r->time,2),'health'=>$r->health,'hits'=>$r->bossProjectileHits,'coverSeconds'=>round($r->bossCoverTime,2),'bossRemaining'=>$r->boss->health];$rows[]=$row;
    fwrite(STDERR,json_encode($row)."\n");
}
echo json_encode(['scope'=>'Representative-build isolated boss fixtures, 240-second cap. Real movement, collisions, rhythmic weapons; no defensive stat changes during combat.','runs'=>$rows],JSON_PRETTY_PRINT)."\n";
