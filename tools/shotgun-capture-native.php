<?php
/** php tools/shotgun-capture-native.php <installed game root> <output directory> */
[$script,$root,$out]=$argv;
require $root.'/vendor/autoload.php';
require __DIR__.'/shotgun-visual-fixture.php';
if (!is_dir($out)) mkdir($out,0777,true);
$app=\JumPHP\Kernel\Application::boot($root);
$engine=$app->engine; $scene=$engine->scenes->current();
$poses=['ready'=>-1.,'ignition'=>.01,'fire'=>.04,'recoil'=>.12,'open'=>.30,'insert'=>.60,'close'=>.90,'settle'=>1.10];
foreach ($poses as $name=>$age) {
    $run=shotgunVisualFixture($scene,true,0,$age);
    for($i=0;$i<3;$i++)$engine->frame();
    if (!$engine->sdl->saveScreenshot($out.'/shotgun-'.$name.'.png')) throw new \RuntimeException('Capture failed: '.$name);
}
file_put_contents($out.'/runtime.json',json_encode(['gl'=>$engine->gl()->isAvailable(),'textureWidth'=>$engine->sdl->getTexture('weapon_shotgun')['width'],'position'=>[$run->x,$run->y,$run->z],'yaw'=>$run->yaw],JSON_PRETTY_PRINT));
$engine->shutdown();
echo "PASS: eight native in-game poses\n";
