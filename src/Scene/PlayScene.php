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
use App\Game\TreasureDirector;
use App\Gfx\WeaponRenderer;
use App\Gfx\World;
use JumPHP\Loop\FixedStep;
use JumPHP\Renderer\Renderer;
use JumPHP\Scene\AbstractScene;
use JumPHP\Ui\Hud;

final class PlayScene extends AbstractScene
{
    private Run $run;
    private World $world;
    private WeaponRenderer $weaponRenderer;
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
    private ?TreasureDirector $objective = null;
    private float $frameMs = 16.7;
    private bool $paused = false;
    private bool $welcome = true;
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
        $this->procedural = (bool)($params['procedural'] ?? true);
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
        if ($this->assembledLevel !== null) $this->run->setObjective($this->assembledLevel);
        $this->objective = $this->run->objective;
        $this->difficulty = new DifficultyDirector();
        $this->world ??= new World();
        $this->weaponRenderer ??= new WeaponRenderer();
        $this->weaponRenderer->reset($this->run->time);
        $this->step = new FixedStep(60, 8);
        $this->rhythm = new RhythmClock();
        $this->restartDelay = 0.0;
        $this->paused = false;
        $this->welcome = !$this->demo && !($params['playing'] ?? false);
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
        $tap = $this->pointerPress();
        $confirm = $this->actions()->isPressed('upgrade_select') || $this->actions()->isPressed('restart');
        if ($this->welcome) {
            if ($confirm || $this->buttonHit($tap, 78)) $this->welcome = false;
            return;
        }
        if (in_array($this->run->state, ['dead', 'won'], true)) {
            $this->restartDelay += $dt;
            if (($this->demo && $this->restartDelay > 1.0) || $confirm || $this->buttonHit($tap, 105)) $this->restartRun();
            return;
        }
        if ($this->actions()->isPressed('pause_game') || ($tap !== null && $tap[0] >= $this->width() - 45 && $tap[1] < 24)) $this->paused = !$this->paused;
        if ($this->paused) {
            if ($confirm || $this->buttonHit($tap, 65)) $this->paused = false;
            elseif ($this->actions()->isPressed('menu_restart') || $this->buttonHit($tap, 105)) $this->restartRun();
            return;
        }

        if ($this->run->state === 'upgrade') {
            if ($this->demo) {
                $this->run->chooseUpgrade(0);
                return;
            }
            if ($tap !== null) {
                foreach ($this->run->choices as $slot => $_) {
                    if ($tap[0] >= 12 && $tap[0] <= $this->width() - 12 && $tap[1] >= 68 + $slot * 22 && $tap[1] < 90 + $slot * 22) {
                        $this->run->chooseUpgrade($slot);
                        return;
                    }
                }
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
            if ($this->run->state !== 'play') return;
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
        $font->draw($r, 'PV ' . $run->health . '/' . $run->maxHealth, 4, 3, 1, $cream);
        $font->draw($r, 'V' . $run->wave . '  E' . $run->kills . '  ' . $this->clock($run->time), $w - 48, 3, 1, $cream, 'right');
        Hud::bar($r, 4, 13, 82, 4, max(0.0, $run->health / $run->maxHealth));
        $font->draw($r, 'NIV ' . $run->level . ' XP ' . $run->xp . '/' . $run->nextXp, 96, 13, 1, $cyan);
        if ($run->combo > 0) $font->draw($r, 'SÉRIE ' . $run->combo, 226, 13, 1, [255,155,90]);


        $cross = $run->flameActive > 0 ? [255,145,75] : ($run->muzzle > 0 ? [255,225,120] : [185,190,175]);
        $r->drawLine($cx - 4, $cy, $cx - 2, $cy, $cross); $r->drawLine($cx + 2, $cy, $cx + 4, $cy, $cross);
        $r->drawLine($cx, $cy - 4, $cx, $cy - 2, $cross); $r->drawLine($cx, $cy + 2, $cx, $cy + 4, $cross);

        $weaponKey = $this->weaponRenderer->textureKey($run->primaryStage);
        $weapon = $this->sdl()->getTexture($weaponKey);
        $this->weaponRenderer->render($r, $run, $weapon);
        $font->draw($r, 'SCORE ' . number_format($run->score, 0, '.', ' '), $cx, $h - 20, 1, [255,205,90], 'center');

        if ($run->flameActive > 0) $font->draw($r, 'BALAYAGE DE FLAMMES', $cx, $h - 40, 1, [255,170,90], 'center');

        $r->fillRect(0, $h - 10, $w, 10, [6,5,12,225]);
        $arsenal = $run->primaryWeaponLabel();
        $font->draw($r, $arsenal, 4, $h - 8, 1, $cyan);
        $font->draw($r, count($run->enemies) . ' ENNEMIS', $w - 4, $h - 8, 1, $cream, 'right');

        if ($run->boss !== null && $run->boss->active && !$run->boss->defeated) {
            $boss = $run->boss;
            $label = $boss->vulnerable ? 'COEUR VULNERABLE : TIREZ !' : 'DETRUISEZ LES ORBES';
            $font->draw($r, $label . ' / PHASE ' . $boss->phase, $cx, 40, 1, $boss->vulnerable ? [100,255,130] : [255,160,90], 'center');
            Hud::bar($r, 60, 51, $w - 120, 5, $boss->health / $boss->maxHealth);
            if ($run->bossWarning !== null) $font->draw($r, 'DANGER AU SOL : BOUGEZ !', $cx, 61, 1, [255,100,65], 'center');
        }
        $messageY = $run->boss?->active && !$run->boss->defeated ? 72 : 38;
        if ($this->procedural && $this->assembledLevel !== null && $this->assembledLevel->treasureAnchor !== null && $run->state === 'play') {
            if ($this->objective !== null && $this->objective->state !== TreasureDirector::EXPLORING) {
                $objectiveText = $this->objective->objectiveLabel();
                if ($this->objective->state === TreasureDirector::FINAL_WAVE) {
                    $objectiveText .= '  ' . ($run->remaining + count($run->enemies));
                }
                $font->draw($r, $objectiveText, $cx, 27, 1, [255,205,90], 'center', [8,5,12]);
            } else {
                $treasure = $run->routeTarget() ?? $this->assembledLevel->treasureAnchor;
                $dx = $treasure['x'] - $run->x;
                $dz = $treasure['z'] - $run->z;
                $distance = hypot($dx, $dz);
                $delta = fmod(atan2($dx, $dz) - $run->yaw + M_PI, 2 * M_PI);
                if ($delta < 0) $delta += 2 * M_PI;
                $delta -= M_PI;
                $marker = abs($delta) < 0.22 ? '^' : ($delta > 0 ? '>' : '<');
                $metres = (int)round($distance);
                $label = $marker === '<' ? '<  OBJECTIF ' . $metres . 'm' : ($marker === '>' ? 'OBJECTIF ' . $metres . 'm  >' : '^ OBJECTIF ' . $metres . 'm');
                $font->draw($r, $label, $cx, 27, 1, [255,205,90], 'center', [8,5,12]);
            }

        }
        if ($run->messageTime > 0 && $run->state === 'play') $font->draw($r, $run->message, $cx, $messageY, 1, $cream, 'center', [8,5,12]);
        if ($run->time < 6 && !$this->demo && $run->state === 'play') {
            $font->draw($r, 'NETTOIE LE NIVEAU / TROUVE LE OBJECTIF / AMELIORE TON SCORE', $cx, $cy - 42, 1, $cream, 'center', [8,5,12]);
        }
        if ($run->time < 8 && !$this->demo) $font->draw($r, 'Z/S : AVANCER / FLÈCHES : TOURNER / ESPACE : SAUT', $cx, $h - 28, 1, $cream, 'center', [8,5,12]);
        if ($run->hurt > 0) { $r->fillRect(0, 23, 3, $h - 33, [240,55,55]); $r->fillRect($w - 3, 23, 3, $h - 33, [240,55,55]); }

        if ($run->state === 'upgrade') {
            $r->fillRect(12, 42, $w - 24, 104, [8,6,16,245]);
            $font->draw($r, 'NIVEAU ' . $run->level . ' - CHOISISSEZ', $cx, 49, 2, $cyan, 'center');
            foreach ($run->choices as $slot => $choice) {
                $selected = $slot === $this->upgradeCursor;
                $prefix = $selected ? '> ' : '  ';
                $font->draw($r, $prefix . ($slot + 1) . '  ' . $run->upgradeLabel($choice), 24, 72 + $slot * 22, 1, $selected ? $cyan : $cream);
                $font->draw($r, $run->upgradeDescription($choice), 24, 81 + $slot * 22, 1, $cyan);
            }
            $font->draw($r, 'FLÈCHES : CHOIX / ENTRÉE : VALIDER', $cx, 135, 1, $cream, 'center');
        }
        if ($this->welcome || $this->paused || in_array($run->state, ['dead', 'won'], true)) {
            $r->fillRect(12, 35, $w - 24, 117, [8,5,12,245]);
            $title = $this->welcome ? 'SLAVE RIFT' : ($this->paused ? 'PAUSE' : ($run->state === 'won' ? 'VICTOIRE !' : 'VOUS ETES MORT'));
            $font->draw($r, $title, $cx, 43, 2, $cyan, 'center');
            if ($this->welcome) {
                $this->drawButton($r, 'JOUER', 78);
                $font->draw($r, 'AVANCEZ / VISEZ / TIRS AUTOMATIQUES', $cx, 121, 1, $cream, 'center');
            } elseif ($this->paused) {
                $this->drawButton($r, 'REPRENDRE', 65);
                $this->drawButton($r, 'RECOMMENCER', 105);
            } else {
                $font->draw($r, 'VAGUE ' . $run->wave . ' / ' . $run->kills . ' ELIM. / ' . $this->clock($run->time), $cx, 69, 1, $cream, 'center');
                $font->draw($r, 'SCORE ' . $run->score, $cx, 83, 1, $cream, 'center');
                $this->drawButton($r, $run->state === 'won' ? 'REJOUER' : 'RECOMMENCER', 105);
            }
            $font->draw($r, ($this->paused ? 'ENTRÉE : REPRENDRE / R OU X : REPARTIR' : 'TOUCHER / ENTRÉE / A'), $cx, 140, 1, $cream, 'center');
        } else {
            $r->fillRect($w - 44, 0, 44, 23, [8,5,12,235]);
            $font->draw($r, 'PAUSE', $w - 22, 7, 1, $cyan, 'center');
        }
        if ($this->demo) $font->draw($r, 'DEMO', $w - 4, 27, 1, $cream, 'right');
        if ($this->debug) {
            $balance = $this->difficulty->snapshot($run);
            $font->draw($r, sprintf('FPS %.0f  %.1f MS  E %d/%d  RLOS %d  SLOS %d  MAP %d  NEI %d',
                1000.0 / max(1.0, $this->frameMs), $this->frameMs, $this->world->visibleEnemies,
                count($run->enemies), $this->world->renderLosTests, $run->losTests,
                $this->world->mapChunksDrawn, $run->neighborChecks), 4, 28, 1, $cyan);
            $font->draw($r, sprintf('TR %d  COR %d  IMP %d  COL %d  PICK %d', count($run->traces), count($run->corpses),
                min(count($run->traces), World::MAX_IMPACTS), $run->collisionTests, count($run->pickups)), 4, 38, 1, $cyan);
            $font->draw($r, sprintf('INPUT KEYBOARD  TURN %.2f RAD/S  %s  BPM %.0f  WPN %s', Run::TURN_SPEED, $arsenal, $this->rhythm->bpm($run->level), $this->weaponRenderer->state()), 4, 48, 1, $cyan);
            $font->draw($r, sprintf('BAL PWR x%.2f  DPS %.0f  ENEMY PV x%.2f  DMG x%.2f',
                $balance['powerIndex'], $balance['primaryDps'], $balance['healthScale'], $balance['damageScale']), 4, 58, 1, $cyan);
            if ($this->procedural && $this->assembledLevel !== null) {
                $objectiveState = $this->objective?->state ?? 'none';
                $font->draw($r, sprintf('PROC SEED %d  ROOMS %d  DEPTH %d  OBJ %s', $this->levelSeed, count($this->assembledLevel->rooms), $this->proceduralDepth, $objectiveState), 4, 68, 1, $cyan);
            }
        }
    }

    private function restartRun(): void
    {
        $this->enter(['demo' => $this->demo, 'debug' => $this->debug,
            'procedural' => $this->procedural, 'seed' => $this->levelSeed,
            'depth' => $this->proceduralDepth, 'playing' => true]);
    }

    /** Mouse coordinates are already converted by InputManager, including letterboxing. */
    private function pointerPress(): ?array
    {
        $mouse = $this->engine->input->getMouse();
        if ($mouse->isPressed(1)) return [$mouse->x, $mouse->y];
        $touch = $this->engine->input->getTouch();
        if (!$touch->isPressed('touch:tap')) return null;
        $r = $this->sdl()->getRenderer();
        [$ow, $oh] = $r->outputSize();
        [$lx, $ly, $lw, $lh] = $r->letterbox();
        if ($lw <= 0 || $lh <= 0) return null;
        return [($touch->x * $ow - $lx) * $this->width() / $lw,
            ($touch->y * $oh - $ly) * $this->height() / $lh];
    }

    private function buttonHit(?array $point, int $y): bool
    {
        return $point !== null && $point[0] >= 60 && $point[0] <= $this->width() - 60
            && $point[1] >= $y && $point[1] < $y + 28;
    }

    private function drawButton(Renderer $r, string $label, int $y): void
    {
        $r->fillRect(60, $y, $this->width() - 120, 28, [35,65,75,255]);
        $this->font()->draw($r, $label, intdiv($this->width(), 2), $y + 10, 1, [100,255,235], 'center');
    }

    private function clock(float $time): string
    {
        return sprintf('%02d:%02d', intdiv((int)$time, 60), (int)$time % 60);
    }
}
