<?php

namespace App\Tests;

use App\Scene\PlayScene;
use JumPHP\Testing\GameTestCase;

final class GameSmokeTest extends GameTestCase
{
    public function testDemoSpawnsAndFires(): void
    {
        $this->game->start(PlayScene::class, ['demo'=>true, 'seed'=>1337]);
        $this->game->run(600);
        $run = $this->game->peek('run');
        self::assertGreaterThan(3.0, $run->time);
        self::assertGreaterThan(0, $run->shots);
        self::assertGreaterThan(0, $run->kills);
        self::assertLessThanOrEqual(48, count($run->enemies));
        $this->game->shot('survival-demo');
    }

    public function testUpgradeInputAndRestart(): void
    {
        $this->game->start(PlayScene::class, ['seed'=>1337]);
        $run = $this->game->peek('run');
        $run->xp = 6;
        $this->game->run(2);
        self::assertSame('upgrade', $run->state);
        $this->game->press('upgrade_one',2);
        self::assertSame('play', $run->state);
        $run->health = 0;
        $this->game->run(2);
        self::assertSame('dead', $run->state);
        $this->game->press('restart',2);
        self::assertSame('play',$this->game->peek('run')->state);
        self::assertSame(100,$this->game->peek('run')->health);
    }
}
