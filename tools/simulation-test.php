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

echo json_encode(['checks'=>$count,'brushes'=>count($run->map->solids)], JSON_PRETTY_PRINT) . "\n";
