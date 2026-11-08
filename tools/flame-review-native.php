<?php
/** php tools/flame-review-native.php <installed game root> <out> */
[$script,$root,$out]=$argv;require $root.'/vendor/autoload.php';
if(!is_dir($out))mkdir($out,0777,true);
$app=\JumPHP\Kernel\Application::boot($root);$engine=$app->engine;$scene=$engine->scenes->current();
$scene->enter(['gameMode'=>\App\Game\GameMode::horde(42),'playing'=>true]);
$run=(new \ReflectionProperty($scene,'run'))->getValue($scene);
$run->state='review';$run->enemies=[];$run->remaining=0;$run->x=0.;$run->z=-12.;$run->y=.75;$run->yaw=0.;$run->flameRank=1;$run->flameActive=1.;$run->primaryStage=2;
for($i=0;$i<8;$i++){
    $run->time=20+$i*.065;(new \ReflectionMethod($run,'updateFlameSegments'))->invoke($run);
    $engine->frame();$engine->sdl->saveScreenshot($out.'/native-flame-'.$i.'.png');
}
$run->z=18.;(new \ReflectionMethod($run,'updateFlameSegments'))->invoke($run);$engine->frame();$engine->sdl->saveScreenshot($out.'/native-flame-wall.png');
if(($argv[3]??'after')==='before'){$engine->shutdown();echo "PASS: native original flame rendered\n";exit;}
$scene->enter(['gameMode'=>\App\Game\GameMode::campaignStage(42,1),'playing'=>true]);
$run=(new \ReflectionProperty($scene,'run'))->getValue($scene);$run->state='review';$run->time=20.;$run->messageTime=0.;
$pack=$run->roomSupplies[0];$level=(new \ReflectionProperty($scene,'assembledLevel'))->getValue($scene);
$room=array_values(array_filter($level->rooms,static fn($r)=>$r['id']===$pack['roomId']))[0];$bounds=$room['bounds'];
$run->x=($bounds['min'][0]+$bounds['max'][0])/2;$run->z=($bounds['min'][2]+$bounds['max'][2])/2-2.2;$run->y=$pack['y']+.45;$run->yaw=0.;
$engine->frame();$engine->sdl->saveScreenshot($out.'/native-healing-room.png');
$run->equipSecondary('flame');$run->equipSecondary('rockets');$run->state='play';$run->remaining=0;$run->intermission=3600.;
$engine->frame();$engine->sdl->saveScreenshot($out.'/native-duo.png');
$run->xp=$run->nextXp;(new \ReflectionMethod($run,'checkLevel'))->invoke($run);$engine->frame();$engine->sdl->saveScreenshot($out.'/native-classic-upgrades.png');
$engine->shutdown();echo "PASS: native flame jet, wall clipping, room supplies and permanent duo rendered\n";
