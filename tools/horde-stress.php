<?php

declare(strict_types=1);
require_once __DIR__ . '/test-bootstrap.php';

use App\Game\GameMode;
use App\Game\HordeArena;
use App\Game\Run;

function measureHorde(int $population, int $frames = 300): array
{
    if ($population < 1 || $population > 200) throw new InvalidArgumentException('Measured cap: 200 enemies');
    $mode = GameMode::horde(42);
    $run = new Run(HordeArena::build(), 42, $mode);
    $run->wave = 45; $run->remaining = 0; $run->intermission = 3600;
    // Benchmark fixture only: preserve a fixed population and disable weapons.
    $run->health = 100000;
    $spawn = new ReflectionMethod(Run::class, 'spawnEnemyAt');
    for ($i = 0; $i < $population; $i++) {
        $x = -15.0 + ($i % 15) * 2.0; $z = -15.0 + intdiv($i, 15) * 2.0;
        $spawn->invoke($run, $x, 0.55, $z, ['runner', 'guardian', 'enforcer', 'brute'][$i % 4]);
    }
    $times = []; $started = microtime(true);
    for ($i = 0; $i < $frames; $i++) {
        $t = microtime(true); $run->step(1/60, 0, 0, 0); $times[] = (microtime(true) - $t) * 1000;
        if (count($run->enemies) !== $population) throw new RuntimeException('Benchmark population changed');
    }
    sort($times);
    return ['enemies' => $population, 'frames' => $frames, 'totalMs' => round((microtime(true)-$started)*1000, 2),
        'meanStepMs' => round(array_sum($times)/count($times), 3), 'maxStepMs' => round(max($times), 3),
        'p95StepMs' => round($times[(int)floor((count($times)-1)*0.95)], 3), 'peakPhpBytes' => memory_get_peak_usage(true), 'state' => $run->state];
}

if (realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    echo json_encode(['scope' => 'PHP CPU simulation only; no rendering/GPU measurement. Fixed-population fixture, not a human run.',
        'cap' => 200, 'results' => array_map(static fn ($n) => measureHorde($n), [50,100,150,200])], JSON_PRETTY_PRINT) . "\n";
}
