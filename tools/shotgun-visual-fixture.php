<?php
/** Deterministic review fixture, never loaded by app.php or gameplay. */
function shotgunVisualFixture(\App\Scene\PlayScene $scene, bool $shotgun, int $stage = 0, float $age = -1): \App\Game\Run
{
    static $prepared = false;
    if (!$prepared) {
        $scene->enter(['playing'=>true, 'procedural'=>false, 'seed'=>1337]);
        $prepared = true;
    }
    $run=(new \ReflectionProperty($scene,'run'))->getValue($scene);
    $run->state='art-review'; // Freeze simulation, without pause-menu overlays.
    $run->time=20.; $run->enemies=[]; $run->remaining=0;
    $run->primaryStage=$stage; $run->primaryShotSerial=0;
    $run->muzzle=0.; $run->hurt=0.; $run->messageTime=0.;
    if ($shotgun) {
        $run->boss=new \App\Game\BossController('wall',1200);
        $run->boss->active=true;
        $run->bossPosition=null; // Same unobstructed room for every weapon.
        $run->shotgunReload=$age<0?0.:max(0.,\App\Game\Run::SHOTGUN_RELOAD-$age);
        $run->shotgunFlash=$age<0?0.:max(0.,.22-$age);
    } else $run->boss=null;
    return $run;
}
