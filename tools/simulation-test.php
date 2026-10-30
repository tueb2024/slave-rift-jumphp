<?php

declare(strict_types=1);

require __DIR__ . '/../src/Game/BrushMap.php';
require __DIR__ . '/../src/Game/Run.php';
require __DIR__ . '/../src/Gfx/Meshes.php';

use App\Game\BrushMap;
use App\Game\Run;
use App\Gfx\Meshes;

set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$count = 0;
function check(bool $condition, string $name): void {
    global $count;
    if (!$condition) throw new RuntimeException('FAIL: ' . $name);
    $count++;
    echo "PASS: $name\n";
}
function fresh(): Run {
    $run = new Run(BrushMap::load(__DIR__ . '/../assets/maps/rift01.map'));
    $run->remaining = 0;
    return $run;
}
function enemy(int $id, float $x, float $z, string $type = 'runner', int $hp = 24): array {
    return ['id'=>$id, 'type'=>$type, 'x'=>$x, 'y'=>0.55, 'z'=>$z, 'health'=>$hp, 'maxHealth'=>$hp,
        'speed'=>0.0, 'damage'=>7, 'radius'=>0.23, 'flash'=>0.0, 'cooldown'=>0.0, 'age'=>0.0];
}
function frames(Run $run, int $n, float $forward = 0.0, float $strafe = 0.0): void {
    for ($i=0; $i<$n; $i++) $run->step(1/60, $forward, $strafe, 0.0);
}

$run = fresh();
check(count($run->map->solids) === 13, 'all 13 original arena brushes import');
foreach ($run->map->solids as $solid) {
    foreach ($solid['triangles'] as $vertex) {
        foreach ($vertex['p'] as $axis=>$v) {
            if (!is_finite($v) || $v < $solid['min'][$axis]-0.002 || $v > $solid['max'][$axis]+0.002) {
                throw new RuntimeException('Invalid reconstructed map geometry');
            }
        }
    }
}
check(!$run->collides($run->x, $run->y, $run->z, 0.28, 0.70), 'player starts clear of solids');
check(count(Meshes::world($run->map)) > 0 && count(Meshes::world($run->map)) % Meshes::STRIDE === 0, 'world mesh stride is valid');
check(Meshes::enemy('runner') !== Meshes::enemy('brute'), 'enemy types have distinct meshes');
foreach (['runner','guardian','brute'] as $type) {
    check(count(Meshes::enemy($type)) % Meshes::STRIDE === 0, $type . ' mesh is valid');
}
check(count(Meshes::pickup(false)) > 0 && count(Meshes::pulse()) > 0, 'reward and pulse meshes are present');

frames($run, 30);
check($run->grounded && abs($run->y-0.70)<0.01, 'gravity settles on the floor');
$run->step(1/60,0,0,0,false,true);
$peak=$run->y;
for ($i=0;$i<80;$i++) { $run->step(1/60,0,0,0); $peak=max($peak,$run->y); }
check($peak>1.5 && $peak<1.9 && $run->grounded, 'credible jump apex and landing');

$run=fresh();$run->z=0.0;$run->yaw=M_PI;$run->grounded=true;
frames($run,66,1.0);
frames($run,20);
check($run->z < -3.5 && $run->y > 1.40 && $run->grounded, 'all four steps climb without jumping');
$run=fresh();$run->x=10.8;$run->z=0;$run->yaw=M_PI/2;
frames($run,60,1.0);
check($run->x<=11.23, 'outer walls stop horizontal movement');
$a=fresh();$b=fresh();frames($a,30,1.0);frames($b,30,1.0,1.0);
check(abs(hypot($a->x,$a->z-5)-hypot($b->x,$b->z-5))<0.01,'diagonal speed is normalized');

$run=fresh();$run->x=0;$run->z=0;$run->yaw=0;
$run->enemies=[100=>enemy(100,0,2),101=>enemy(101,0,-2),102=>enemy(102,1,4)];
$run->step(1/60,0,0,0);
check($run->enemies[100]['health']===6 && $run->enemies[101]['health']===24 && $run->enemies[102]['health']===24,
    'auto fire prioritizes a close frontal threat and ignores rear enemies');
$run=fresh();$run->x=0;$run->z=0;$run->yaw=0;
$run->map->solids[]=['min'=>[-1,-0.1,0.95],'max'=>[1,2,1.0]];
$run->enemies=[100=>enemy(100,0,2)];
$run->step(1/60,0,0,0);
check($run->shots===0 && !$run->lineClear([0,1,0],[0,1,2]), 'thin walls block auto fire exactly');

$run=fresh();$run->x=0;$run->z=0;$run->yaw=0;$run->pulseUnlocked=true;
$run->enemies=[100=>enemy(100,0,-2)];
frames($run,65);
check($run->kills===1 && count($run->pickups)===1 && $run->shots===0, 'pulse hits rear targets and drops XP independently');
$run=fresh();
$run->pickups=[['x'=>$run->x,'y'=>0.25,'z'=>$run->z,'kind'=>'xp','value'=>6,'age'=>0.0]];
frames($run,1);
check($run->state==='upgrade' && $run->level===2 && count(array_unique($run->choices))===3, 'XP opens three distinct upgrades');
$time=$run->time;frames($run,30);
check($run->time===$time, 'upgrade choice freezes simulation');
check(!$run->chooseUpgrade(9) && $run->chooseUpgrade(0) && $run->state==='play', 'only valid upgrade choices resume play');

$run=fresh();$run->x=0;$run->z=-4;$run->y=1.42;$run->yaw=0;$run->grounded=true;$run->health=100000;$run->maxHealth=100000;
$run->enemies=[100=>enemy(100,0,0,'guardian',1000)];
$run->enemies[100]['speed']=1.25;
frames($run,240, -0.0);
check($run->enemies[100]['y']>1.2,'enemies climb the staircase to pursue a player on the dais');

$run = new Run(BrushMap::load(__DIR__ . '/../assets/maps/rift01.map'), 42);
$twin = new Run($run->map,42);
$run->health=$run->maxHealth=$twin->health=$twin->maxHealth=1000000;
$seen=[];$peakEnemies=0;$peakPickups=0;
for ($i=0;$i<60*200;$i++) {
    if ($run->state==='upgrade') $run->chooseUpgrade(0);
    if ($twin->state==='upgrade') $twin->chooseUpgrade(0);
    [$f,$s,$t,$fire]=$run->autopilot();
    $run->step(1/60,$f,$s,$t,$fire);
    $twin->step(1/60,$f,$s,$t,$fire);
    foreach ($run->enemies as $e) {
        $seen[$e['type']]=true;
        if ($run->collides($e['x'],$e['y'],$e['z'],$e['radius'],0.55)) throw new RuntimeException('Enemy embedded in solid');
    }
    $peakEnemies=max($peakEnemies,count($run->enemies));$peakPickups=max($peakPickups,count($run->pickups));
    if ($peakEnemies>Run::MAX_ENEMIES || $peakPickups>Run::MAX_PICKUPS || !is_finite($run->x+$run->y+$run->z)) {
        throw new RuntimeException('Simulation exceeded bounds');
    }
}
check($run->wave>=6 && count($seen)===3, 'long simulation reaches escalating waves and all three enemy types');
check($run->pulseUnlocked && $run->kills>20 && $run->level>1, 'long simulation exercises arsenal, kills and progression');
check($run->enemies===$twin->enemies && $run->kills===$twin->kills && $run->pickups===$twin->pickups,'identical seeds and controls produce identical runs');
check($peakEnemies<=48 && $peakPickups<=80,'entity budgets remain bounded over 200 seconds');
echo json_encode(['checks'=>$count,'wave'=>$run->wave,'kills'=>$run->kills,'level'=>$run->level,
    'peakEnemies'=>$peakEnemies,'peakPickups'=>$peakPickups],JSON_PRETTY_PRINT)."\n";
