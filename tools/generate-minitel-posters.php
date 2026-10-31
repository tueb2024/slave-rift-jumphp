<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Gfx/MinitelRosePoster.php';

use App\Gfx\MinitelRosePoster;

$count = max(1, (int)($argv[1] ?? 20));
$startSeed = (int)($argv[2] ?? 361500);
$out = $argv[3] ?? dirname(__DIR__) . '/generated/minitel-posters';

$generator = new MinitelRosePoster();
$paths = $generator->generateBatch($out, $count, $startSeed);
fwrite(STDOUT, "Generated " . count($paths) . " Minitel Rose posters in {$out}\n");
