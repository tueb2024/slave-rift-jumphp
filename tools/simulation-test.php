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
    return new Run(BrushMap::load(__DIR__ . '/../assets/maps/rift01.map'), $seed);
}
function frames(Run $run, int $n, float $forward = 0.0, float $strafe = 0.0, float $turn = 0.0): void {
    for ($i=0; $i<$n; $i++) $run->step(1/60, $forward, $strafe, $turn);
}

$run = fresh();
check(count($run->map->solids) >= 10, 'arena imports a useful brush set');
foreach ($run->map->solids as $solid) {
    foreach ($solid['triangles'] as $vertex) {
        foreach ($vertex['p'] as $axis => $v) {
            if (!is_finite($v) || $v < $solid['min'][$axis] - 0.002 || $v > $solid['max'][$axis] + 0.002) {
                throw new RuntimeException('Invalid reconstructed map geometry');
            }
        }
    }
}
check(!$run->collides($run->x, $run->y, $run->z, Run::PLAYER_RADIUS, Run::PLAYER_HALF_HEIGHT), 'player starts clear of solids');
check(count(Meshes::world($run->map)) > 0 && count(Meshes::world($run->map)) % Meshes::STRIDE === 0, 'world mesh stride is valid');
foreach (['runner','guardian','brute'] as $type) {
    check(count(Meshes::enemy($type)) > 0 && count(Meshes::enemy($type)) % Meshes::STRIDE === 0, $type . ' mesh is valid');
}
check(count(Meshes::pickup(false)) > 0 && count(Meshes::pickup(true)) > 0 && count(Meshes::pulse()) > 0, 'pickup and pulse meshes are present');

// Basic physics must settle without NaNs or leaving the arena immediately.
$run->remaining = 0;
frames($run, 90);
check($run->grounded && is_finite($run->x) && is_finite($run->y) && is_finite($run->z), 'player physics settles deterministically');

$ledge = new BrushMap();
$ledge->solids = [['min' => [-1.0, -0.3, -1.0], 'max' => [1.0, 0.0, 1.0], 'triangles' => []]];
$ledge->playerStart = [0.0, 0.7, 0.0];
$ledgeRun = new Run($ledge);
$ledgeRun->remaining = 0;
frames($ledgeRun, 300, 1.0);
check($ledgeRun->y > 0.6 && $ledgeRun->z < 1.3, 'player stays on the floor at an open ledge');

$a = fresh(42); $b = fresh(42);
for ($i=0; $i<180; $i++) {
    $forward = $i < 60 ? 0.3 : ($i < 120 ? -0.2 : 0.1);
    $strafe = ($i % 80) < 40 ? 0.25 : -0.15;
    $turn = ($i % 50) < 25 ? 0.12 : -0.08;
    $a->step(1/60, $forward, $strafe, $turn);
    $b->step(1/60, $forward, $strafe, $turn);
}
check($a->x === $b->x && $a->y === $b->y && $a->z === $b->z && $a->yaw === $b->yaw && $a->enemies === $b->enemies,
    'identical seeds and controls remain deterministic');

check($run->arsenalLabel() === 'COLT .45 ONLY', 'run starts with manual Colt arsenal');

// DM1's info_player_start is an intermission camera above empty space. The
// demo must start at a deathmatch point and keep making combat progress there.
$dm1 = new Run(BrushMap::load(__DIR__ . '/../assets/maps/quake-dm1.map'), 1337);
$spawnProbe = new Run($dm1->map, 1337);
frames($spawnProbe, 90);
check($spawnProbe->enemies !== [] && count(array_filter($spawnProbe->enemies,
    static fn (array $enemy): bool => abs($enemy['y'] - $spawnProbe->y) > 1.5)) === 0,
    'DM1 opening enemies stay on the player floor');
$sawDeathAnimation = false;
for ($i = 0; $i < 600 && $dm1->state !== 'dead'; $i++) {
    if ($dm1->state === 'upgrade') {
        $dm1->chooseUpgrade(0);
        continue;
    }
    [$forward, $strafe, $turn, $fire] = $dm1->autopilot();
    $dm1->step(1 / 60, $forward, $strafe, $turn, $fire);
    if ($dm1->corpses !== []) $sawDeathAnimation = true;
}
check($dm1->grounded && $dm1->y > -10 && $dm1->y < 20, 'DM1 player spawn stays on the arena floor');
check($dm1->shots > 0 && $dm1->hits > 0 && $dm1->kills > 0, 'DM1 demo makes combat progress');
check($dm1->score > 0 && $dm1->multiplier >= 1.0, 'DM1 demo rewards kills with score');
check($sawDeathAnimation, 'DM1 enemy kills leave a short visual death animation');

$rangedWave = new Run($dm1->map, 1);
$rangedWave->wave = 2;
$rangedWave->remaining = 100;
for ($i = 0; $i < 120; $i++) $rangedWave->step(1 / 60, 0, 0, 0);
check(count(array_filter($rangedWave->enemies, static fn (array $enemy): bool => $enemy['type'] === 'enforcer')) > 0,
    'DM1 wave two can spawn the ranged enforcer');

// Dense-wave operation counts stay tied to local neighbors, not every pair.
$stressMap = new BrushMap();
$stressMap->solids = [['min' => [-50.0, -0.5, -50.0], 'max' => [50.0, 0.0, 50.0], 'triangles' => []]];
$stressMap->playerStart = [0.0, 0.7, 0.0];
foreach ([8, 16, 32, 48] as $population) {
    $stress = new Run($stressMap, 42);
    $stress->remaining = 0;
    for ($id = 1; $id <= $population; $id++) {
        $stress->enemies[$id] = ['id' => $id, 'type' => 'runner', 'x' => 5.0 + ($id % 8) * 1.6,
            'y' => 0.55, 'z' => 5.0 + intdiv($id, 8) * 1.6, 'health' => 24, 'maxHealth' => 24,
            'speed' => 2.05, 'damage' => 7, 'radius' => 0.23, 'flash' => 0.0,
            'cooldown' => 0.6, 'age' => 0.0, 'yaw' => 0.0, 'attackTime' => 0.0, 'moving' => false, 'animState' => 'idle'];
    }
    $stress->step(1 / 60, 0, 0, 0);
    check($stress->neighborChecks < $population * 20, "dense wave {$population} keeps neighbor checks bounded");
}

echo json_encode(['checks'=>$count,'brushes'=>count($run->map->solids)], JSON_PRETTY_PRINT) . "\n";
