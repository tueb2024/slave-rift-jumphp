<?php

namespace App\Scene;

use App\Audio\RiftMusic;
use App\Audio\RiftSfx;
use App\Game\AssembledLevel;
use App\Game\BrushMap;
use App\Game\DifficultyDirector;
use App\Game\LevelAssembler;
use App\Game\LevelGraph;
use App\Game\RhythmClock;
use App\Game\Run;
use App\Gfx\World;
use JumPHP\Loop\FixedStep;
use JumPHP\Renderer\Renderer;
use JumPHP\Scene\AbstractScene;
use JumPHP\Ui\Hud;

final class PlayScene extends AbstractScene
{
    private Run $run;
    private World $world;
    private FixedStep $step;
    private RiftMusic $music;
    private RhythmClock $rhythm;
    private DifficultyDirector $difficulty;
    private bool $demo = false;
    private bool $debug = false;
    private bool $procedural = false;
    private int $levelSeed = 1337;
    private int $proceduralDepth = 7;
    private ?AssembledLevel $assembledLevel = null;
    private float $frameMs = 16.7;
    private bool $paused = false;
    private bool $jumpPending = false;
    private float $restartDelay = 0.0;
    private float $pitch = 0.0;
    private float $prevMuzzle = 0.0;
    private float $prevPulse = 0.0;
    private float $prevSpecial = 0.0;
    private float $weaponSfxCooldown = 0.0;
    private string $prevState = 'play';
    private int $prevXp = 0;
    private int $upgradeCursor = 0;

    public function enter(array $params = []): void
    {
        $this->demo = (bool)($params['demo'] ?? false);
        $this->debug = (bool)($params['debug'] ?? false);
        $this->procedural = (bool)($params['procedural'] ?? false);
        $this->levelSeed = (int)($params['seed'] ?? ($this->demo ? 1337 : random_int(1, 65520)));
        $this->proceduralDepth = max(5, min(24, (int)($params['depth'] ?? 7)));
        if ($this->procedural) {
            $graph = new LevelGraph($this->levelSeed, $this->proceduralDepth);
            $this->assembledLevel = (new LevelAssembler())->assemble($graph);
            $map = $this->assembledLevel->map;
        } else {
            $this->assembledLevel = null;
            $map = BrushMap::load($this->engine->config->path('assets/maps/quake-dm1.map'));
        }
        $this->run = new Run($map, $this->levelSeed);
        $this->difficulty = new DifficultyDirector();
        $this->world ??= new World();
        $this->step = new FixedStep(60, 8);
        $this->rhythm = new RhythmClock();
        $this->restartDelay = 0.0;
        $this->paused = false;
        $this->jumpPending = false;
        $this->pitch = 0.0;
        $this->prevMuzzle = $this->prevPulse = $this->prevSpecial = 0.0;
        $this->weaponSfxCooldown = 0.0;
        $this->prevState = 'play';
        $this->prevXp = 0;
        $this->upgradeCursor = 0;
        $this->world->prepare($this->engine->gl(), $this->run);
        $this->loadAssetLayer();

        if (!isset($this->music)) {
            $this->music = new RiftMusic($this->sound());
            $this->music->register();
        }
    }

    private function loadAssetLayer(): void
    {
        foreach ([
            'weapon_colt' => 'assets/sprites/weapons/fps-colt.png',
            'weapon_mp44' => 'assets/sprites/weapons/fps-mp44.png',
            'weapon_bar' => 'assets/sprites/weapons/fps-bar.png',
            'weapon_gatling' => 'assets/sprites/weapons/fps-gatling.png',
        ] as $key => $path) {
            if (!$this->sdl()->hasTexture($key)) $this->sdl()->loadTexture($key, $path);
        }
        RiftSfx::register($this->sound());
    }

    public function update(float $dt): void
    {
        $this->frameMs = $this->frameMs * 0.9 + $dt * 1000.0 * 0.1;
        if ($this->actions()->isPressed('pause_game')) $this->paused = !$this->paused;
        if ($this->paused) return;

        if ($this->run->state === 'upgrade') {
            if ($this->demo) {
                $this->run->chooseUpgrade(0);
                return;
            }
            $count = count($this->run->choices);
            if ($count > 0) {
                if ($this->actions()->isPressed('upgrade_prev')) {
                    $this->upgradeCursor = ($this->upgradeCursor - 1 + $count) % $count;
                }
                if ($this->actions()->isPressed('upgrade_next')) {
                    $this->upgradeCursor = ($this->upgradeCursor + 1) % $count;
                }
                if ($this->actions()->isPressed('upgrade_select')) {
                    $this->run->chooseUpgrade($this->upgradeCursor);
                    return;
                }
            }
            foreach (['upgrade_one', 'upgrade_two', 'upgrade_three'] as $slot => $action) {
                if ($this->actions()->isPressed($action)) {
                    $this->upgradeCursor = $slot;
                    $this->run->chooseUpgrade($slot);
                    return;
                }
            }
            return;
        }

        if ($this->run->state === 'dead') {
            $this->restartDelay += $dt;
            if (($this->demo && $this->restartDelay > 1.0) || $this->actions()->isPressed('restart')) {
                $this->enter([
                    'demo' => $this->demo,
                    'debug' => $this->debug,
                    'procedural' => $this->procedural,
                    'seed' => $this->levelSeed,
                    'depth' => $this->proceduralDepth,
                ]);
            }
            return;
        }

        if ($this->demo) {
            [$forward, $strafe, $turn, $fire] = $this->run->autopilot();
        } else {
            $forward = $this->actions()->axis('move_back', 'move_forward');
            $strafe = $this->actions()->axis('strafe_left', 'strafe_right');
            $turn = $this->actions()->axis('turn_left', 'turn_right');
            $fire = false;
            $this->jumpPending = $this->jumpPending || $this->actions()->isPressed('jump');
        }

        $beforeState = $this->run->state;
        $beforeXp = $this->run->xp;
        $this->step->run($dt, function (float $h) use ($forward, $strafe, $turn, $fire): void {
            $this->difficulty->beforeStep($this->run);
            $rhythmSteps = $this->rhythm->advance($h, $this->run->level);
            $this->run->step($h, $forward, $strafe, $turn, $fire, $this->jumpPending, false, $rhythmSteps);
            $this->difficulty->afterStep($this->run);
            $this->jumpPending = false;
        });

        $this->playAudioEvents($beforeState, $beforeXp, $dt);
        $this->music->playSteps($this->rhythm->drain(), $this->run->level);
    }

    private function playAudioEvents(string $beforeState, int $beforeXp, float $dt): void
    {
        foreach ($this->run->drainPrimaryAudioEvents() as $stage) {
            $this->sound()->playSfx('rift_hat', 0, false);
            $name = $stage <= 1 ? 'colt_fire' : ($stage <= 3 ? 'sten_fire' : 'heavy_fire');
            $this->sound()->playSfx($name, 0, true);
        }
        if ($this->run->pulse > $this->prevPulse + 0.01) $this->sound()->playSfx('pulse_sfx', 0, false);
        if ($this->run->specialFlash > $this->prevSpecial + 0.01) $this->sound()->playSfx('special_sfx', 0, false);
        if ($beforeState === 'play' && $this->run->state === 'upgrade') {
            $this->upgradeCursor = 0;
            $this->sound()->playSfx('level_up', 0, false);
        }
        if ($this->run->xp > $beforeXp) $this->sound()->playSfx('pickup_xp', 0, false);
        $this->prevMuzzle = $this->run->muzzle;
        $this->prevPulse = $this->run->pulse;
        $this->prevSpecial = $this->run->specialFlash;
        $this->prevState = $this->run->state;
        $this->prevXp = $this->run->xp;
    }

    public function render(Renderer $r): void
    {
        $run = $this->run;
        $this->world->render($this->engine, $r, $run, $this->pitch);
        $font = $this->font();
        $w = $this->width(); $h = $this->height(); $cx = intdiv($w, 2); $cy = intdiv($h, 2);
        $cream = [240,215,170]; $cyan = [90,235,225];
        $r->fillRect(0, 0, $w, 23, [6,5,12,225]);
        $font->draw($r, 'HP ' . $run->health . '/' . $run->maxHealth, 4, 3, 1, $cream);
        $font->draw($r, 'W' . $run->wave . '  K' . $run->kills . '  ' . $this->clock($run->time), $w - 4, 3, 1, $cream, 'right');
        $font->draw($r, 'SCORE ' . number_format($run->score, 0, '.', ' ') . '  x' . number_format($run->multiplier, 1), $cx, 3, 1, [255,205,90], 'center');
        Hud::bar($r, 4, 13, 82, 4, max(0.0, $run->health / $run->maxHealth));
        $font->draw($r, 'LV' . $run->level . ' XP ' . $run->xp . '/' . $run->nextXp, 96, 13, 1, $cyan);
        if ($run->combo > 0) $font->draw($r, 'CHAIN ' . $run->combo, 170, 13, 1, [255,155,90]);
        Hud::bar($r, $w - 70, 14, 66, 3, min(1.0, $run->xp / $run->nextXp));

        $cross = $run->flameActive > 0 ? [255,145,75] : ($run->muzzle > 0 ? [255,225,120] : [185,190,175]);
        $r->drawLine($cx - 4, $cy, $cx - 2, $cy, $cross); $r->drawLine($cx + 2, $cy, $cx + 4, $cy, $cross);
        $r->drawLine($cx, $cy - 4, $cx, $cy - 2, $cross); $r->drawLine($cx, $cy + 2, $cx, $cy + 4, $cross);

        $weaponKey = match (true) {
            $run->primaryStage <= 1 => 'weapon_colt',
            $run->primaryStage <= 3 => 'weapon_mp44',
            $run->primaryStage === 4 => 'weapon_bar',
            default => 'weapon_gatling',
        };
        $weaponFrame = $run->muzzle <= 0.0 ? 0 : ($run->muzzle > 0.08 ? 1 : ($run->muzzle > 0.04 ? 2 : 3));
        $weapon = $this->sdl()->getTexture($weaponKey);
        $drawH = (int)round($h * ($run->primaryStage <= 1 ? 0.27 : 0.29));
        $drawW = (int)round($drawH * 1.5);
        $bob = (int)round(sin($run->time * 6) * $h * 0.004);
        $weaponY = $h - $drawH + $bob;
        $copies = in_array($run->primaryStage, [1, 3, 6], true) ? 2 : 1;
        $spread = (int)round($drawW * 0.20);
        for ($gun = 0; $gun < $copies; $gun++) {
            $offset = (int)round(($gun - ($copies - 1) / 2) * $spread);
            $weaponX = (int)round($cx - $drawW * 0.18 + $offset);
            $r->copy($weapon['texture'], [$weaponFrame * 144, 0, 144, 96], $weaponX, $weaponY, $drawW, $drawH);
        }

        if ($run->pulseUnlocked) {
            $r->fillRect($w - 41, $h - 30, 24, 23, [43,63,80]);
            $r->fillRect($w - 33, $h - 37, 8, 20, $run->pulse > 0 ? [180,255,255] : [70,180,195]);
        }
        if ($run->flameActive > 0) $font->draw($r, 'FLAME SWEEP', $cx, $h - 40, 1, [255,170,90], 'center');

        $r->fillRect(0, $h - 10, $w, 10, [6,5,12,225]);
        $arsenal = $run->arsenalLabel();
        $font->draw($r, $arsenal, 4, $h - 8, 1, $cyan);
        $font->draw($r, count($run->enemies) . ' HOSTILES', $w - 4, $h - 8, 1, $cream, 'right');

        $messageY = 29;
        if ($this->procedural && $this->assembledLevel !== null && $this->assembledLevel->treasureAnchor !== null && $run->state === 'play') {
            $treasure = $this->assembledLevel->treasureAnchor;
            $dx = $treasure['x'] - $run->x;
            $dz = $treasure['z'] - $run->z;
            $distance = hypot($dx, $dz);
            $delta = fmod(atan2($dx, $dz) - $run->yaw + M_PI, 2 * M_PI);
            if ($delta < 0) $delta += 2 * M_PI;
            $delta -= M_PI;
            $marker = abs($delta) < 0.22 ? '^' : ($delta > 0 ? '>' : '<');
            $metres = (int)round($distance);
            $label = $marker === '<' ? '<  TRESOR ' . $metres . 'm' : ($marker === '>' ? 'TRESOR ' . $metres . 'm  >' : '^ TRESOR ' . $metres . 'm');
            $font->draw($r, $label, $cx, 27, 1, [255,205,90], 'center', [8,5,12]);
            $messageY = 38;
        }
        if ($run->messageTime > 0 && $run->state === 'play') $font->draw($r, $run->message, $cx, $messageY, 1, $cream, 'center', [8,5,12]);
        if ($run->time < 6 && !$this->demo && $run->state === 'play') {
            $font->draw($r, 'NETTOIE LE NIVEAU / TROUVE LE TRESOR / AMELIORE TON SCORE', $cx, $cy - 42, 1, $cream, 'center', [8,5,12]);
        }
        if ($run->time < 8 && !$this->demo) $font->draw($r, 'Z/S MOVE / Q/D STRAFE / LEFT-RIGHT TURN / WEAPONS AUTO / SPACE JUMP', $cx, $h - 28, 1, $cream, 'center', [8,5,12]);
        if ($run->hurt > 0) { $r->fillRect(0, 23, 3, $h - 33, [240,55,55]); $r->fillRect($w - 3, 23, 3, $h - 33, [240,55,55]); }

        if ($run->state === 'upgrade') {
            $r->fillRect(12, 42, $w - 24, 104, [8,6,16,245]);
            $font->draw($r, 'LEVEL ' . $run->level . ' - CHOOSE', $cx, 49, 2, $cyan, 'center');
            foreach ($run->choices as $slot => $choice) {
                $selected = $slot === $this->upgradeCursor;
                $prefix = $selected ? '> ' : '  ';
                $font->draw($r, $prefix . ($slot + 1) . '  ' . $choice, 24, 72 + $slot * 22, 1, $selected ? $cyan : $cream);
                $font->draw($r, $run->upgradeDescription($choice), 24, 81 + $slot * 22, 1, $cyan);
            }
            $font->draw($r, 'UP-DOWN CHOOSE / ENTER OR SPACE CONFIRM', $cx, 135, 1, $cream, 'center');
        } elseif ($run->state === 'dead') {
            $r->fillRect(26, 56, $w - 52, 69, [8,5,12,235]);
            $font->draw($r, 'RIFT CLAIMED YOU', $cx, 65, 2, [245,90,76], 'center');
            $font->draw($r, 'WAVE ' . $run->wave . ' / ' . $run->kills . ' KILLS / ' . $this->clock($run->time), $cx, 88, 1, $cream, 'center');
            $font->draw($r, $this->demo ? 'DEMO RESTART' : 'ENTER TO TRY AGAIN', $cx, 109, 1, $cyan, 'center');
        }
        if ($this->paused) { $r->fillRect(82, 72, $w - 164, 30, [8,5,12,235]); $font->draw($r, 'PAUSED - P', $cx, 83, 1, $cream, 'center'); }
        if ($this->demo) $font->draw($r, 'DEMO', $w - 4, 27, 1, $cream, 'right');
        if ($this->debug) {
            $balance = $this->difficulty->snapshot($run);
            $font->draw($r, sprintf('FPS %.0f  %.1f MS  E %d/%d  RLOS %d  SLOS %d  MAP %d  NEI %d',
                1000.0 / max(1.0, $this->frameMs), $this->frameMs, $this->world->visibleEnemies,
                count($run->enemies), $this->world->renderLosTests, $run->losTests,
                $this->world->mapChunksDrawn, $run->neighborChecks), 4, 28, 1, $cyan);
            $font->draw($r, sprintf('TR %d  COR %d  IMP %d  COL %d  PICK %d', count($run->traces), count($run->corpses),
                min(count($run->traces), World::MAX_IMPACTS), $run->collisionTests, count($run->pickups)), 4, 38, 1, $cyan);
            $font->draw($r, sprintf('INPUT KEYBOARD  TURN %.2f RAD/S  %s  BPM %.0f', Run::TURN_SPEED, $arsenal, $this->rhythm->bpm($run->level)), 4, 48, 1, $cyan);
            $font->draw($r, sprintf('BAL PWR x%.2f  DPS %.0f  ENEMY HP x%.2f  DMG x%.2f',
                $balance['powerIndex'], $balance['primaryDps'], $balance['healthScale'], $balance['damageScale']), 4, 58, 1, $cyan);
            if ($this->procedural && $this->assembledLevel !== null) {
                $font->draw($r, sprintf('PROC SEED %d  ROOMS %d  DEPTH %d', $this->levelSeed, count($this->assembledLevel->rooms), $this->proceduralDepth), 4, 68, 1, $cyan);
            }
        }
    }

    private function clock(float $time): string
    {
        return sprintf('%02d:%02d', intdiv((int)$time, 60), (int)$time % 60);
    }
}
