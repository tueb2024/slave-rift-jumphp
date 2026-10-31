<?php

namespace App\Tests;

use App\Scene\PlayScene;
use JumPHP\Testing\GameTestCase;

final class ProceduralSceneSmokeTest extends GameTestCase
{
    public function testProceduralSceneBootsThroughRunAndWorld(): void
    {
        $this->game->start(PlayScene::class, ['demo' => true, 'procedural' => true, 'seed' => 1337, 'depth' => 8]);
        $this->game->run(120);
        $run = $this->game->peek('run');
        self::assertGreaterThan(0.5, $run->time);
        self::assertGreaterThan(20, count($run->map->solids));
        self::assertNotEmpty($run->map->spawnPoints);
        $this->game->shot('procedural-demo');
    }
}
