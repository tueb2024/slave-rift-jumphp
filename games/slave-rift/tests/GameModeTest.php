<?php

namespace App\Tests;

use App\Game\GameMode;
use App\Game\HordeArena;
use App\Game\Run;
use PHPUnit\Framework\TestCase;

final class GameModeTest extends TestCase
{
    public function testNewNormalNeverReusesPreviousSeedAndReplayIsExplicit(): void
    {
        $mode = GameMode::fixed(1337);
        for ($i = 0; $i < 100; $i++) {
            $next = GameMode::normal($mode->seed);
            self::assertNotSame($mode->seed, $next->seed);
            self::assertSame(GameMode::NORMAL, $next->kind);
            $mode = $next;
        }
        self::assertSame($mode->seed, GameMode::fixed($mode->seed)->seed);
    }

    public function testDailyContractsAreReproducibleDistinctAndProgressivelyHarder(): void
    {
        $seeds = []; $previous = null;
        for ($tier = 1; $tier <= 4; $tier++) {
            $mode = GameMode::daily('2026-11-02', $tier);
            self::assertEquals($mode, GameMode::daily('2026-11-02', $tier));
            self::assertNotSame($mode->seed, GameMode::daily('2026-11-03', $tier)->seed);
            self::assertSame('2026-11-02', $mode->dailyId);
            $seeds[] = $mode->seed;
            if ($previous !== null) {
                self::assertGreaterThan($previous->depth, $mode->depth);
                self::assertGreaterThan($previous->enemyHealthScale(7), $mode->enemyHealthScale(7));
                self::assertGreaterThan($previous->damageScale(7),$mode->damageScale(7));
                self::assertGreaterThan($previous->waveBudget(7), $mode->waveBudget(7));
                self::assertGreaterThan($previous->bossHealth, $mode->bossHealth);
            }
            $previous = $mode;
        }
        self::assertCount(4, array_unique($seeds));
        self::assertSame(42, GameMode::daily('2026-11-02', 2, 42, 'server/challenge')->seed);
        self::assertSame('server/challenge', GameMode::daily('2026-11-02', 2, 42, 'server/challenge')->dailyId);
    }

    public function testArchetypeHealthScalesOnceWithMapAndWave(): void
    {
        foreach (range(1,4) as $tier) foreach ([1,6,7,15,30] as $wave) {
            $scale = GameMode::daily('2026-11-02',$tier)->enemyHealthScale($wave);
            foreach (['runner','guardian','enforcer','brute'] as $type) {
                $base=\App\Game\DifficultyDirector::enemyBaseProfile($type)['health'];
                self::assertSame((int)round($base*$scale),\App\Game\DifficultyDirector::enemyHealthFor($type,$scale));
            }
        }
    }

    public function testDailyRejectsInvalidCalendarDates(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        GameMode::daily('2026-02-30', 1);
    }

    public function testHordeSharesSimulationButHasNoTreasureAndABoundedSeparateLimit(): void
    {
        $mode = GameMode::horde(42); $run = new Run(HordeArena::build(), 42, $mode);
        self::assertNull($run->objective); self::assertNull($run->boss);
        self::assertSame(200, $mode->enemyLimit(100));
        self::assertSame(48, GameMode::fixed(42)->enemyLimit(100));
        self::assertGreaterThan($mode->waveBudget(7), $mode->waveBudget(8));
        self::assertLessThan($mode->spawnInterval(7), $mode->spawnInterval(8));
        self::assertGreaterThan($mode->enemyHealthScale(100), $mode->enemyHealthScale(101));
        $run->wave = 10; $run->remaining = 1000; $run->health = 100000;
        for ($i = 0; $i < 900; $i++) $run->step(1/60, 0, 0, 0);
        self::assertGreaterThan(48, count($run->enemies));
        self::assertLessThanOrEqual($mode->enemyLimit(10), count($run->enemies));
        self::assertGreaterThan(48, $run->spawned);
    }
}
