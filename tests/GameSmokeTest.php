<?php

namespace App\Tests;

use App\Input\WheelInputManager;
use App\Scene\PlayScene;
use JumPHP\Input\InputManager;
use JumPHP\Testing\GameTestCase;

final class GameSmokeTest extends GameTestCase
{
    public function testAnimatedArsenalAndPowerAtlasesRender(): void
    {
        $this->game->start(PlayScene::class,['gameMode'=>\App\Game\GameMode::horde(42),'playing'=>true]);
        $r=$this->game->peek('run');$r->remaining=0;$r->intermission=1000;$r->health=100000;
        foreach(range(0,6)as$stage){$r->primaryStage=$stage;$r->primaryShotSerial++;$this->game->run(1);$this->game->shot('arsenal-stage-'.$stage);}
        foreach(['flame','emp','rockets','artillery','sniper']as$id){
            $r->equipSecondary($id);$field=match($id){'flame'=>'flameRank','emp'=>'empRank','rockets'=>'rocketRank','artillery'=>'artilleryRank',default=>'sniperRank'};$r->$field=1;
            $x=$r->x+sin($r->yaw)*3;$z=$r->z+cos($r->yaw)*3;
            if($id==='flame')$r->flameActive=.8;
            if($id==='emp')$r->empFx=.6;
            if($id==='rockets')$r->projectiles[]=['x'=>$x,'y'=>$r->y+.3,'z'=>$z,'tx'=>$x,'ty'=>$r->y+.3,'tz'=>$z+4,'kind'=>'rockets','age'=>.1,'damage'=>90,'radius'=>2.6,'speed'=>18];
            if($id==='artillery')$r->explosions[]=['x'=>$x,'y'=>$r->y,'z'=>$z,'radius'=>3.6,'life'=>.45,'totalLife'=>.7];
            if($id==='sniper')$r->traces[]=['startX'=>$r->x,'startY'=>$r->y+.35,'startZ'=>$r->z,'endX'=>$x,'endY'=>$r->y+.3,'endZ'=>$z,'x'=>$x,'y'=>$r->y+.3,'z'=>$z,'type'=>'sniper','life'=>.2,'totalLife'=>.22];
            $this->game->run(1);self::assertCount(1,$r->secondaryInventory());$this->game->shot('power-'.$id);
        }
    }
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
            // Each iteration starts a legacy single-map fixture: campaign victories use next_map.
            $this->game->start(PlayScene::class,['seed'=>1337,'playing'=>true]);
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
            // Each iteration starts a legacy single-map fixture: campaign victories use next_map.
            $this->game->start(PlayScene::class,['seed'=>1337,'playing'=>true]);
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
        $campaign = $this->game->peek('campaign');
        self::assertNotNull($campaign);
        self::assertContains($campaign->totalStages,[3,4]);
        self::assertCount($campaign->totalStages,array_unique($campaign->stageSeeds));
        $first->state = 'dead'; $this->game->run(1);
        $this->game->press('upgrade_select', 1); // NOUVELLE CAMPAGNE
        self::assertNotSame($first->mode->seed,$this->game->peek('run')->mode->seed);
        self::assertNotNull($this->game->peek('campaign'));
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

    public function testExplicitLegacyFixtureKeepsItsMapOnReplay(): void
    {
        $this->game->start(PlayScene::class,['seed'=>1337,'playing'=>true,'procedural'=>false]);
        $old = $this->game->peek('run');
        self::assertNull($old->objective);
        $old->state='dead'; $this->game->run(1);
        $this->game->press('upgrade_select',1);
        self::assertFalse($this->game->peek('procedural'));
        self::assertNull($this->game->peek('run')->objective);
        self::assertSame(1337,$this->game->peek('run')->mode->seed);
    }

    public function testFrameMetricsRetainStallsBeyondPhysicsDeltaCap(): void
    {
        $this->game->start(PlayScene::class);
        $scene=$this->game->scene();
        (new \ReflectionProperty($scene,'lastFrameAt'))->setValue($scene,microtime(true)-0.25);
        $scene->update(1/60);
        $frames=$this->game->peek('recentFrames');
        self::assertGreaterThanOrEqual(240.0,$frames[array_key_last($frames)]);
    }
}
