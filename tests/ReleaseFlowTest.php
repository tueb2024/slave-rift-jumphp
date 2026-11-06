<?php

namespace App\Tests;

use App\Game\DifficultyDirector;
use App\Game\LevelAssembler;
use App\Game\LevelGraph;
use App\Game\RhythmClock;
use App\Game\Run;
use App\Game\TreasureDirector;
use PHPUnit\Framework\TestCase;

final class ReleaseFlowTest extends TestCase
{
    private function fresh(int $seed = 2): Run
    {
        $level = (new LevelAssembler())->assemble(new LevelGraph($seed, 7));
        $run = new Run($level->map, $seed);
        $run->setObjective($level);
        // These fixtures isolate the final encounter or geometry after the exploration waves.
        $run->encounters->clear(PHP_INT_MAX);$run->navigation->setBlockers([]);
        return $run;
    }

    public function testRealRhythmicShotsCompleteBossAndTreasureLoop(): void
    {
        $run = $this->fresh();
        $b = $run->objective->finalRoomBounds;
        $run->x = ($b['min'][0] + $b['max'][0]) / 2;
        $run->z = ($b['min'][2] + $b['max'][2]) / 2;
        $run->y = $run->bossPosition['y'] - 0.5;
        $run->step(1 / 60, 0, 0, 0);
        self::assertSame(TreasureDirector::FINAL_WAVE, $run->objective->state);
        self::assertGreaterThan(0, $run->remaining);
        // Isolate the transition from mob AI; boss damage below uses actual weapon events.
        $run->remaining = 0; $run->enemies = [];
        $run->step(1 / 60, 0, 0, 0);
        self::assertSame(TreasureDirector::BOSS, $run->objective->state);
        self::assertTrue($run->boss->active);
        $p=$run->objective->treasureAnchor;$b=$run->bossPosition;
        $d=max(.001,hypot($b['x']-$p['x'],$b['z']-$p['z']));
        $run->x=$b['x']-($b['x']-$p['x'])/$d*5;$run->z=$b['z']-($b['z']-$p['z'])/$d*5;
        $clock = new RhythmClock();
        for ($i = 0; $i < 60 * 80 && !$run->boss->defeated; $i++) {
            $target = $run->bossPosition;
            if (!$run->boss->vulnerable) foreach ($run->boss->orbs as $orb) if ($orb['alive']) { $target = $orb; break; }
            $run->yaw = atan2($target['x'] - $run->x, $target['z'] - $run->z);
            // Sidestep a telegraphed blast; no invulnerability or modified health.
            $strafe = $run->bossWarning !== null ? (sin($run->time*2)>0?1.0:-1.0) : 0.0;
            $run->step(1 / 60, 0, $strafe, 0, true, false, false, $clock->advance(1 / 60, $run->level));
            $clock->drain();
            self::assertSame('play', $run->state);
        }
        self::assertTrue($run->boss->defeated, 'The guaranteed encounter shotgun must complete repeated vulnerability cycles.');
        $run->step(1 / 60, 0, 0, 0);
        self::assertSame(TreasureDirector::TREASURE_READY, $run->objective->state);
        $treasure = $run->objective->treasureAnchor;
        $run->x = $treasure['x']; $run->z = $treasure['z'];
        $run->step(1 / 60, 0, 0, 0);
        self::assertSame(TreasureDirector::COMPLETE, $run->objective->state);
        self::assertSame('won', $run->state);
        $score = $run->score; $time = $run->time;
        $run->step(1 / 60, 0, 0, 0);
        self::assertSame($score, $run->score, 'Victory bonus is awarded once.');
        self::assertSame($time, $run->time, 'Victory freezes combat.');
        $fresh = $this->fresh();
        self::assertSame('play', $fresh->state);
        self::assertSame(100, $fresh->health);
        self::assertFalse($fresh->boss->active);
        self::assertSame(TreasureDirector::EXPLORING, $fresh->objective->state);
    }

    public function testStationaryPlayerCanBeKilledByBoss(): void
    {
        $run = $this->fresh();
        $p=$run->objective->treasureAnchor;$b=$run->bossPosition;
        $d=max(.001,hypot($b['x']-$p['x'],$b['z']-$p['z']));
        $run->x=$b['x']-($b['x']-$p['x'])/$d*5;$run->z=$b['z']-($b['z']-$p['z'])/$d*5;$run->y=$b['y']-.5;
        $run->objective->state = TreasureDirector::BOSS_READY;
        $run->remaining = 0;
        for ($i = 0; $i < 60 * 35; $i++) $run->step(1 / 60, 0, 0, 0);
        self::assertSame('dead', $run->state);
        self::assertSame(0, $run->health);
        self::assertFalse($run->boss->defeated);
    }

    public function testFinalSpawnsStayInFinalRoom(): void
    {
        $run = $this->fresh(); $p = $run->objective->treasureAnchor;
        $run->x = $p['x']; $run->z = $p['z']; $run->y = $run->bossPosition['y'] - 0.5;
        for ($i = 0; $i < 60 * 12; $i++) $run->step(1 / 60, 0, 0, 0);
        self::assertNotEmpty($run->enemies);
        $b = $run->objective->finalRoomBounds;
        foreach ($run->enemies as $e) {
            self::assertGreaterThanOrEqual($b['min'][0], $e['x']);
            self::assertLessThanOrEqual($b['max'][0], $e['x']);
            self::assertGreaterThanOrEqual($b['min'][2], $e['z']);
            self::assertLessThanOrEqual($b['max'][2], $e['z']);
        }
    }

    public function testGeneratedRouteCanBeWalkedWithoutJumpingOrTeleporting(): void
    {
        for ($seed = 1; $seed <= 16; $seed++) {
            $run = $this->fresh($seed);
            $destination = $run->objective->treasureAnchor;
            $run->objective = null; // Navigation-only test: isolate collision from combat.
            $run->remaining = 0; $run->intermission = 3600;
            for ($i = 0; $i < 60 * 160; $i++) {
                $p = $run->routeTarget();
                $dx = $p['x'] - $run->x; $dz = $p['z'] - $run->z;
                if (hypot($destination['x'] - $run->x, $destination['z'] - $run->z) < 0.65) break;
                $run->yaw = atan2($dx, $dz);
                $run->step(1 / 60, 1, 0, 0);
            }
            self::assertLessThan(0.7, hypot($destination['x'] - $run->x, $destination['z'] - $run->z), 'Unwalkable route, seed ' . $seed);
            self::assertTrue($run->grounded);
        }
    }

    public function testDualMp44DealsTwiceTheDamageOnTheSameRhythmicBeat(): void
    {
        $damage = [];
        foreach ([2, 3] as $stage) {
            $run = $this->fresh();
            $run->objective = null; $run->remaining = 0; $run->intermission = 3600;
            $run->primaryStage = $stage;
            $run->yaw = 0.0;
            $spawn = new \ReflectionMethod(Run::class, 'spawnEnemyAt');
            self::assertTrue($spawn->invoke($run, $run->x, $run->y - 0.15, $run->z + 3, 'guardian'));
            $id = array_key_first($run->enemies);
            $run->enemies[$id]['health'] = $run->enemies[$id]['maxHealth'] = 10000;
            $run->enemies[$id]['speed'] = 0.0;
            $clock = new RhythmClock();
            for ($frame = 0; $frame < 30; $frame++) {
                $run->step(1 / 60, 0, 0, 0, false, false, false, $clock->advance(1 / 60, 1));
                $clock->drain();
            }
            $damage[$stage] = 10000 - $run->enemies[$id]['health'];
        }
        self::assertGreaterThan(0, $damage[2]);
        self::assertSame($damage[2] * 2, $damage[3], 'Dual MP44 must improve actual shots, not only an estimated DPS number.');
    }

    public function testVitalityCannotMakeHealthGrowWithoutBound(): void
    {
        $run = $this->fresh();
        for ($i = 0; $i < 20; $i++) {
            $run->state = 'upgrade'; $run->choices = ['VITALITY'];
            self::assertTrue($run->chooseUpgrade(0));
        }
        self::assertSame(Run::HEALTH_CAP, $run->maxHealth);
        self::assertSame($run->maxHealth, $run->health);
        self::assertSame('SOINS', $run->upgradeLabel('VITALITY'));
    }

    public function testDifficultyCurveHasReadableWeightsAndNoPowerPenalty(): void
    {
        foreach (['runner' => 18, 'guardian' => 54, 'enforcer' => 72, 'brute' => 216] as $type => $weight) {
            self::assertSame($weight, DifficultyDirector::enemyBaseProfile($type)['health']);
        }
        for ($wave = 1; $wave <= 20; $wave++) {
            self::assertSame(DifficultyDirector::healthScaleFor(1, $wave), DifficultyDirector::healthScaleFor(20, $wave));
            self::assertLessThanOrEqual(1.025, DifficultyDirector::healthScaleFor(1, $wave + 1) / DifficultyDirector::healthScaleFor(1, $wave));
        }
        self::assertGreaterThan(1.7, DifficultyDirector::primaryDpsEstimate(12) / DifficultyDirector::primaryDpsEstimate(8));
    }
}
