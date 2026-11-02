<?php

namespace App\Tests;

use App\Input\WheelInputManager;
use App\Scene\PlayScene;
use JumPHP\Input\InputManager;
use JumPHP\Testing\GameTestCase;

final class GameSmokeTest extends GameTestCase
{
    public function testInjectedInputManagerMatchesEngineContract(): void
    {
        $input = $this->game->engine->input;
        self::assertInstanceOf(InputManager::class, $input);
        self::assertInstanceOf(WheelInputManager::class, $input);
        foreach (['poll', 'endFrame', 'pressKey', 'getKeyboard', 'getMouse', 'getTouch', 'getGamepad', 'getActions'] as $method) {
            self::assertTrue(method_exists($input, $method), 'Injected InputManager must provide ' . $method . '().');
        }
    }

    public function testDemoSpawnsAndFires(): void
    {
        $this->game->start(PlayScene::class, ['demo'=>true, 'seed'=>1337]);
        $this->game->run(600);
        $run = $this->game->peek('run');
        self::assertGreaterThan(3.0, $run->time);
        self::assertGreaterThan(0, $run->shots);
        self::assertGreaterThan(0, $run->kills);
        self::assertGreaterThan(0, $run->score);
        self::assertLessThanOrEqual(48, count($run->enemies));
        $this->game->shot('survival-demo');
    }

    public function testUpgradeInputAndRestart(): void
    {
        $this->game->start(PlayScene::class, ['seed'=>1337, 'playing'=>true]);
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

    public function testWelcomeFreezesCombatAndAcceptsConfirm(): void
    {
        $this->game->start(PlayScene::class, ['seed' => 1337]);
        $this->game->run(60);
        self::assertNull($this->game->peek('run'));
        $this->game->press('upgrade_select', 2);
        self::assertFalse($this->game->peek('welcome'));
        $this->game->run(2);
        self::assertGreaterThan(0.0, $this->game->peek('run')->time);
    }

    public function testMouseAndRawTouchRestartDeathAndVictoryWithoutReload(): void
    {
        $this->game->start(PlayScene::class, ['seed' => 1337, 'playing' => true]);
        foreach (['dead', 'won'] as $state) {
            $old = $this->game->peek('run');
            $old->state = $state;
            $old->health = 0;
            $input = $this->game->engine->input;
            $input->getMouse()->move(160, 118);
            $input->getMouse()->down(1);
            $this->game->scene()->update(1 / 60);
            $input->getMouse()->up(1);
            $input->getMouse()->resetTransientStates();
            $this->game->run(24);
            $fresh = $this->game->peek('run');
            self::assertNotSame($old, $fresh);
            self::assertSame('play', $fresh->state);
            self::assertSame(100, $fresh->health);
            self::assertFalse($fresh->boss->active);
            self::assertFalse($this->game->peek('welcome'));
        }
        // SDL touch reports normalized window coordinates; test conversion through letterboxing.
        $r = $this->game->engine->sdl->getRenderer();
        [$ow, $oh] = $r->outputSize();
        [$lx, $ly, $lw, $lh] = $r->letterbox();
        $x = ($lx + 160 * $lw / 320) / $ow;
        $y = ($ly + 118 * $lh / 180) / $oh;
        foreach (['dead', 'won'] as $state) {
            $old = $this->game->peek('run'); $old->state = $state;
            $touch = $this->game->engine->input->getTouch();
            $touch->handle((object)['type' => SDL_FINGERDOWN, 'tfinger' => (object)['x' => $x, 'y' => $y]]);
            $touch->handle((object)['type' => SDL_FINGERUP, 'tfinger' => (object)['x' => $x, 'y' => $y]]);
            $this->game->scene()->update(1 / 60);
            $touch->resetTransientStates();
            $this->game->run(24);
            self::assertNotSame($old, $this->game->peek('run'));
            self::assertSame('play', $this->game->peek('run')->state);
        }
    }

    public function testPauseAndUpgradeAcceptPointerInput(): void
    {
        $this->game->start(PlayScene::class, ['seed' => 1337, 'playing' => true]);
        $this->game->press('pause_game', 1);
        $time = $this->game->peek('run')->time;
        $this->game->run(30);
        self::assertSame($time, $this->game->peek('run')->time);
        $mouse = $this->game->engine->input->getMouse();
        $mouse->move(160, 78); $mouse->down(1);
        $this->game->scene()->update(1 / 60);
        $mouse->up(1); $mouse->resetTransientStates();
        self::assertFalse($this->game->peek('paused'));
        $this->game->run(24);
        $run = $this->game->peek('run');
        $run->xp = $run->nextXp; $this->game->run(2);
        self::assertSame('upgrade', $run->state);
        $mouse->move(160, 100); $mouse->down(1);
        $this->game->scene()->update(1 / 60);
        $mouse->up(1); $mouse->resetTransientStates();
        self::assertSame('play', $run->state);
    }

    public function testModeMenuNormalReplayNewMapDailyAndHorde(): void
    {
        $this->game->start(PlayScene::class);
        self::assertNull($this->game->peek('run'));
        $this->game->press('upgrade_select', 1);
        $first = $this->game->peek('run');
        self::assertSame('normal',$first->mode->kind);
        $first->state = 'dead'; $this->game->run(1);
        $this->game->press('upgrade_select', 1);
        self::assertSame($first->mode->seed,$this->game->peek('run')->mode->seed);
        $this->game->peek('run')->state = 'dead'; $this->game->run(1);
        $this->game->press('upgrade_next',1); $this->game->press('upgrade_select',1);
        self::assertNotSame($first->mode->seed,$this->game->peek('run')->mode->seed);
        $this->game->start(PlayScene::class);
        $this->game->press('upgrade_next',1); $this->game->press('upgrade_select',1);
        self::assertSame('daily',$this->game->peek('menu')->screen);
        $this->game->press('upgrade_select',1);
        self::assertSame('daily',$this->game->peek('run')->mode->kind);
        self::assertSame(1,$this->game->peek('run')->mode->difficultyTier);
        $this->game->start(PlayScene::class);
        $this->game->press('upgrade_next',1); $this->game->press('upgrade_next',1); $this->game->press('upgrade_select',1);
        self::assertSame('horde',$this->game->peek('run')->mode->kind);
        self::assertNull($this->game->peek('run')->objective);
        $this->game->press('pause_game',1);
        $this->game->press('upgrade_next',1); $this->game->press('upgrade_next',1); $this->game->press('upgrade_select',1);
        self::assertNull($this->game->peek('run'));
        self::assertSame(0,$this->game->peek('levelSeed'));
    }
}
