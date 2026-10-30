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
function fresh(int $seed = 1337): Run {
    $run = new Run(BrushMap::load(__DIR__ . '/../assets/maps/rift01.map'), $seed);
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
check(count($run->map->solids) >= 10, 'multi-zone arena imports a useful brush set');
foreach ($run->map->solids as $solid) {
    foreach ($solid['triangles'] as $vertex) {
        foreach ($vertex['p'] as $axis=>$v) {
            if (!is_finite($v) || $v < $solid['min'][$axis]-0.002 || $v > $solid['max'][$axis]+0.002) {
                throw new RuntimeException('Invalid reconstructed map geometry');
            }
        }
    }
}
check(!$run->collides($run->x, $run->y, $run->z, Run::PLAYER_RADIUS, Run::PLAYER_HALF_HEIGHT), 'player starts clear of solids');
check(count(Meshes::world($run->map)) > 0 && count(Meshes::world($run->map)) % Meshes::STRIDE === 0, 'world mesh stride is valid');
foreach (['runner','guardian','brute'] as $type) check(count(Meshes::enemy($type)) % Meshes::STRIDE === 0, $type . ' mesh is valid');
check(count(Meshes::pickup(false)) > 0 && count(Meshes::pulse()) > 0, 'reward and pulse meshes are present');

frames($run, 30);
check($run->grounded, 'gravity settles on arena support');
$startY = $run->y;
$run->step(1/60,0,0,0,false,true);
$peak = $run->y;
for ($i=0;$i<80;$i++) { $run->step(1/60,0,0,0); $peak=max($peak,$run->y); }
check($peak > $startY + 0.75 && $peak < $startY + 1.35 && $run->grounded, 'credible jump apex and landing');

$a=fresh();$b=fresh();
$ax=$a->x;$az=$a->z;$bx=$b->x;$bz=$b->z;
frames($a,30,1.0);frames($b,30,1.0,1.0);
$da=hypot($a->x-$ax,$a->z-$az);$db=hypot($b->x-$bx,$b->z-$bz);
check(abs($da-$db)<0.02,'diagonal speed is normalized');

$run=fresh();$run->x=0;$run->y=0.70;$run->z=-8;$run->yaw=0;
$run->enemies=[100=>enemy(100,0,-5, 'guardian', 100)];
$run->step(1/60,0,0,0,true);
check($run->shots===1 && $run->enemies[100]['health']<100,'Colt .45 remains manual and aimed');
check($run->autoTier===0 && $run->arsenalLabel()==='COLT .45 ONLY','autofire starts locked');

$run=fresh();
$run->pickups=[['x'=>$run->x,'y'=>$run->y,'z'=>$run->z,'kind'=>'xp','value'=>6,'age'=>0.0]];
frames($run,1);
check($run->state==='upgrade' && $run->level===2 && $run->autoTier===1,'first level unlocks Sten autofire');
check($run->chooseUpgrade(0) && $run->state==='play','valid upgrade resumes play');

$run->x=0;$run->y=0.70;$run->z=-8;$run->yaw=0;
$run->enemies=[100=>enemy(100,0,-5,'runner',50)];
$before=$run->enemies[100]['health'];
frames($run,20);
check(!isset($run->enemies[100]) || $run->enemies[100]['health'] < $before,'Sten autofire damages a frontal target');

$run=fresh();$run->x=0;$run->y=0.70;$run->z=-8;$run->yaw=0;$run->pulseUnlocked=true;
$run->enemies=[100=>enemy(100,0,-9,'runner',24)];
frames($run,65);
check($run->kills===1 && count($run->pickups)===1 && $run->shots===0,'radial pulse remains independent of guns');

$run=fresh();$run->x=0;$run->y=0.70;$run->z=-8;$run->yaw=0;
$run->enemies=[100=>enemy(100,0,-4,'brute',135)];
$run->step(1/60,0,0,0,false,false,true);
check($run->specialCooldown>0 && $run->enemies[100]['health']<135,'active special weapon fires independently');

$one=fresh(42);$two=fresh(42);
for($i=0;$i<120;$i++){ $one->step(1/60,0.2,0.3,0.1); $two->step(1/60,0.2,0.3,0.1); }
check($one->x===$two->x && $one->z===$two->z && $one->enemies===$two->enemies,'identical seeds and controls remain deterministic');

echo json_encode(['checks'=>$count,'brushes'=>count($run->map->solids)],JSON_PRETTY_PRINT)."\n";
