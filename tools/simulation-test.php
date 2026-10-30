<?php

declare(strict_types=1);

require __DIR__ . '/../src/Game/BrushMap.php';
require __DIR__ . '/../src/Game/Run.php';
require __DIR__ . '/../src/Gfx/Meshes.php';
require __DIR__ . '/../src/Gfx/EnemyAtlas.php';
require __DIR__ . '/../src/Gfx/World.php';

use App\Game\BrushMap;
use App\Game\Run;
use App\Gfx\Meshes;
use App\Gfx\EnemyAtlas;
use App\Gfx\World;

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

foreach (EnemyAtlas::SHEETS as $type => $sheet) {
    [$width, $height] = getimagesize(__DIR__ . '/../' . $sheet['path']);
    check($width === $sheet['width'] && $height === $sheet['height'], "$type atlas dimensions match the image");
    foreach ($sheet['frames'] as $row => $frames) {
        $expected = $row === $sheet['death'] ? ($type === 'runner' ? 7 : 6) : 8;
        check(count($frames) === $expected, "$type row $row has its declared frames");
        foreach ($frames as $column => $rect) {
            if (!EnemyAtlas::valid($sheet, $rect)) throw new RuntimeException("$type row $row has an out-of-bounds source rect");
            if ($column > 0 && $frames[$column - 1][0] + $frames[$column - 1][2] > $rect[0]) {
                throw new RuntimeException("$type row $row has overlapping frame rectangles");
            }
        }
    }
    foreach ($sheet['clips'] as $state => $clip) foreach ($clip as $tick => $row) {
        if ($row === $sheet['death'] || !isset($sheet['frames'][$row])) throw new RuntimeException("$type clip references an invalid direction row");
        for ($direction = 0; $direction < 8; $direction++) {
            if (EnemyAtlas::frame($type, $state, $tick, $direction) !== $sheet['frames'][$row][$direction]) {
                throw new RuntimeException("$type $state tick $tick direction $direction selects the wrong frame");
            }
        }
    }
    foreach ($sheet['frames'][$sheet['death']] as $frame => $rect) {
        if (EnemyAtlas::frame($type, 'death', $frame, 0) !== $rect) {
            throw new RuntimeException("$type death frame $frame selects the wrong rectangle");
        }
    }
    check(true, "$type declared frames stay inside the texture");
}

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

$stairs = new BrushMap();
$stairs->solids = [
    ['min' => [-2.0, -0.3, -2.0], 'max' => [2.0, 0.0, 0.0], 'triangles' => []],
    ['min' => [-2.0, 0.0, 0.0], 'max' => [2.0, 0.5, 2.0], 'triangles' => []],
];
$stairs->playerStart = [0.0, 0.7, -1.0];
$stairRun = new Run($stairs);
$stairRun->remaining = 0;
frames($stairRun, 90, 1.0);
check($stairRun->z > 0.5 && $stairRun->y > 1.1, 'player climbs a half-unit step without jumping');

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
$sawTracer = false;
for ($i = 0; $i < 600 && $dm1->state !== 'dead'; $i++) {
    if ($dm1->state === 'upgrade') {
        $dm1->chooseUpgrade(0);
        continue;
    }
    [$forward, $strafe, $turn, $fire] = $dm1->autopilot();
    $dm1->step(1 / 60, $forward, $strafe, $turn, $fire);
    if ($dm1->corpses !== []) $sawDeathAnimation = true;
    if ($dm1->traces !== []) {
        $trace = reset($dm1->traces);
        $sawTracer = isset($trace['startX'], $trace['startY'], $trace['startZ'], $trace['endX'], $trace['endY'], $trace['endZ']);
        if (count($dm1->traces) > Run::MAX_VISUAL_TRACES) throw new RuntimeException('Visual tracer budget exceeded');
    }
}
check($dm1->grounded && $dm1->y > -10 && $dm1->y < 20, 'DM1 player spawn stays on the arena floor');
check($dm1->shots > 0 && $dm1->hits > 0 && $dm1->kills > 0, 'DM1 demo makes combat progress');
check($dm1->score > 0 && $dm1->multiplier >= 1.0, 'DM1 demo rewards kills with score');
check($sawDeathAnimation, 'DM1 enemy kills leave a short visual death animation');
check($sawTracer, 'DM1 demo produces a muzzle-to-impact tracer');

$rangedWave = new Run($dm1->map, 1);
$rangedWave->wave = 2;
$rangedWave->remaining = 100;
$rangedWave->health = 100000;
for ($i = 0; $i < 900; $i++) $rangedWave->step(1 / 60, 0, 0, 0);
check(count(array_filter($rangedWave->enemies, static fn (array $enemy): bool => $enemy['type'] === 'enforcer')) > 0,
    'DM1 wave two can spawn the ranged enforcer');

$spreadMap = new BrushMap();
$spreadMap->solids = [['min' => [-50.0, -0.5, -50.0], 'max' => [50.0, 0.0, 50.0], 'triangles' => []]];
$spreadMap->playerStart = [0.0, 0.7, 0.0];
foreach (range(-2, 2) as $gx) foreach ([-1, 1] as $gz) $spreadMap->spawnPoints[] = [$gx * 4.0, 0.75, $gz * 10.0];
$spread = new Run($spreadMap, 7);
$spread->wave = 9;
$spread->remaining = 40;
frames($spread, 25);
$newEnemies = array_values($spread->enemies);
check(count($newEnemies) >= 3, 'wide wave uses multiple valid spawn points');
foreach ($newEnemies as $i => $one) foreach (array_slice($newEnemies, $i + 1) as $two) {
    $minimum = ($one['type'] === 'brute' || $two['type'] === 'brute' ? 1.95 : 1.60)
        + $one['radius'] + $two['radius'];
    if (hypot($one['x'] - $two['x'], $one['z'] - $two['z']) < $minimum - 0.1) {
        throw new RuntimeException('Freshly spawned enemies violate comfort spacing');
    }
}
check(true, 'freshly spawned enemies keep comfort spacing');

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
    if ($population >= 32) {
        $world = new World();
        $visibility = new ReflectionMethod($world, 'enemyVisible');
        $renderLos = 0;
        for ($frame = 0; $frame < 60; $frame++) {
            $stress->time = $frame / 60;
            $world->renderLosTests = 0;
            foreach ($stress->enemies as $enemy) $visibility->invoke($world, $stress, $enemy, 10.0);
            $renderLos += $world->renderLosTests;
        }
        check($renderLos < $population * 13, "dense wave {$population} amortizes render LOS ($renderLos per second)");
    }
}

echo json_encode(['checks'=>$count,'brushes'=>count($run->map->solids)], JSON_PRETTY_PRINT) . "\n";
