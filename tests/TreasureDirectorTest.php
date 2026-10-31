<?php

namespace App\Tests;

use App\Game\AssembledLevel;
use App\Game\BrushMap;
use App\Game\TreasureDirector;
use PHPUnit\Framework\TestCase;

final class TreasureDirectorTest extends TestCase
{
    public function testEnteringTerminalRoomStartsFinalWaveExactlyOnce(): void
    {
        $director = new TreasureDirector(
            ['min' => [10.0, 0.0, 20.0], 'max' => [18.0, 4.0, 28.0]],
            ['x' => 16.0, 'y' => 1.0, 'z' => 26.0],
            14,
        );

        self::assertFalse($director->updatePlayer(4.0, 1.0, 4.0));
        self::assertSame(TreasureDirector::EXPLORING, $director->state);
        self::assertTrue($director->updatePlayer(12.0, 1.0, 22.0));
        self::assertSame(TreasureDirector::FINAL_WAVE, $director->state);
        self::assertTrue($director->finalRoomReached);
        self::assertFalse($director->updatePlayer(13.0, 1.0, 23.0));
    }

    public function testBossAndTreasureSequenceIsStrict(): void
    {
        $director = new TreasureDirector(
            ['min' => [0.0, 0.0, 0.0], 'max' => [10.0, 4.0, 10.0]],
            ['x' => 9.0, 'y' => 1.0, 'z' => 9.0],
        );
        $director->updatePlayer(2.0, 1.0, 2.0);

        self::assertFalse($director->notifyBossDefeated());
        self::assertTrue($director->notifyFinalWaveCleared());
        self::assertSame(TreasureDirector::BOSS_READY, $director->state);
        self::assertTrue($director->startBoss());
        self::assertSame(TreasureDirector::BOSS, $director->state);
        self::assertTrue($director->notifyBossDefeated());
        self::assertSame(TreasureDirector::TREASURE_READY, $director->state);

        $director->updatePlayer(7.0, 1.0, 7.0);
        self::assertSame(TreasureDirector::TREASURE_READY, $director->state);
        $director->updatePlayer(8.2, 1.0, 8.2);
        self::assertSame(TreasureDirector::COMPLETE, $director->state);
    }

    public function testFactoryUsesBossRoomInsteadOfEuclideanGuess(): void
    {
        $map = new BrushMap();
        $level = new AssembledLevel(
            $map,
            [],
            [],
            [
                ['id' => 0, 'type' => 'START', 'mainPath' => true, 'depth' => 0, 'bounds' => ['min' => [0.0,0.0,0.0], 'max' => [8.0,4.0,8.0]], 'connectors' => []],
                ['id' => 1, 'type' => 'BOSS_ROOM', 'mainPath' => true, 'depth' => 7, 'bounds' => ['min' => [40.0,0.0,20.0], 'max' => [52.0,5.0,32.0]], 'connectors' => []],
            ],
            ['x' => 49.0, 'y' => 1.0, 'z' => 29.0],
            ['x' => 46.0, 'y' => 1.0, 'z' => 27.0],
            1337,
        );

        $director = TreasureDirector::fromLevel($level, 16);
        self::assertNotNull($director);
        self::assertSame(16, $director->finalWaveSize);
        self::assertTrue($director->updatePlayer(45.0, 1.0, 25.0));
    }
}
