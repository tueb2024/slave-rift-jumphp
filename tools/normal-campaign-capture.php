<?php
/** Native in-game map-entry captures; transitions are explicit review fixtures. */
[$script,$root,$out]=$argv;
require $root.'/vendor/autoload.php';
$app=\JumPHP\Kernel\Application::boot($root);$engine=$app->engine;$scene=$engine->scenes->current();
$scene->enter(['gameMode'=>\App\Game\GameMode::campaignStage(42,1),'playing'=>true]);
if(!is_dir($out))mkdir($out,0777,true);
for($stage=1;$stage<=4;$stage++){
    $run=(new \ReflectionProperty($scene,'run'))->getValue($scene);
    $run->state='review';$run->time=20.;$run->messageTime=0.;
    $target=$run->routeTarget();$run->yaw=atan2($target['x']-$run->x,$target['z']-$run->z);
    for($i=0;$i<3;$i++)$engine->frame();
    if(!$engine->sdl->saveScreenshot($out.'/native-map-'.$stage.'.png'))throw new \RuntimeException('Capture failed');
    $run->state='won';if($stage<4)(new \ReflectionMethod($scene,'nextCampaignMap'))->invoke($scene);
}
$engine->shutdown();echo "PASS: four native campaign entries\n";
