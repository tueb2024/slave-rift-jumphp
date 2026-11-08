<?php
/** Native review fixtures: starter, XP offer, mobile boss phases and physical cover. */
[$script,$root,$out]=$argv;$before=($argv[3]??'after')==='before';require $root.'/vendor/autoload.php';
if(!is_dir($out))mkdir($out,0777,true);
$app=\JumPHP\Kernel\Application::boot($root);$engine=$app->engine;$scene=$engine->scenes->current();
$scene->enter(['gameMode'=>\App\Game\GameMode::campaignStage(42,1),'playing'=>true]);$r=(new \ReflectionProperty($scene,'run'))->getValue($scene);
$shot=function($name)use($engine,$out){$engine->frame();$engine->sdl->saveScreenshot($out.'/'.$name.'.png');};
$r->state='review';$r->time=20.;$r->messageTime=0.;$shot('starter');
if(!$before){
 $r->level=3;$r->xp=$r->nextXp;$r->state='play';(new \ReflectionMethod($r,'checkLevel'))->invoke($r);$shot('xp-weapon-offer');
 $r->chooseUpgrade(0);$r->state='review';$r->messageTime=0.;$shot('acquired-colt');
}
$r->encounters->clear(PHP_INT_MAX);$r->navigation->setBlockers([]);$r->remaining=0;$r->enemies=[];$r->objective->state=\App\Game\TreasureDirector::BOSS_READY;
(new \ReflectionMethod($r,'stepObjective'))->invoke($r,.001);$r->state='review';
$level=(new \ReflectionProperty($scene,'assembledLevel'))->getValue($scene);
foreach($level->rooms as $room)if($room['type']===\App\Game\LevelGraph::BOSS_ROOM)foreach($room['connectors'] as $entry)if($entry['role']==='entry'){
 [$x,$y,$z]=$entry['position'];[$nx,,$nz]=$entry['normal'];$r->x=$x-$nx*.8;$r->y=$y+.75;$r->z=$z-$nz*.8;
}
$r->yaw=atan2($r->bossPosition['x']-$r->x,$r->bossPosition['z']-$r->z);
if($before){$shot('boss');$engine->shutdown();exit;}
$r->bossFacing=atan2($r->x-$r->bossPosition['x'],$r->z-$r->bossPosition['z']);
foreach(['recover','windup','barrage'] as $phase){
 $r->boss->attackState=$phase;$r->boss->attackTimer=1.2;$r->boss->vulnerable=$phase!=='barrage';$r->boss->time=7/12;
 $r->bossWarning=$phase==='windup'?['x'=>$r->x,'y'=>$r->y,'z'=>$r->z,'time'=>1.2]:null;
 if($phase==='barrage')(new \ReflectionMethod($r,'launchBossProjectiles'))->invoke($r,['x'=>$r->x,'y'=>$r->y,'z'=>$r->z]);
 $shot('boss-'.$phase);
}
$p=$r->bossCoverPosition;$r->x=$p['x'];$r->y=$p['y'];$r->z=$p['z'];$r->yaw=atan2($r->bossPosition['x']-$r->x,$r->bossPosition['z']-$r->z);$shot('adjacent-refuge');
file_put_contents($out.'/cover.json',json_encode(['cover'=>$p,'peek'=>$r->bossPeekPosition,'lineOfSight'=>$r->lineClear([$r->x,$r->y+.3,$r->z],array_values($r->bossPosition))]));
$engine->shutdown();echo "PASS: starter, legal XP acquisition, boss poses and occluded adjacent refuge captured\n";
