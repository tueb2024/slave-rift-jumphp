<?php

namespace App\Scene;

use App\Audio\RiftMusic;
use App\Audio\RiftSfx;
use App\Game\AssembledLevel;
use App\Game\CampaignRun;
use App\Game\BrushMap;
use App\Game\DifficultyDirector;
use App\Game\GameMode;
use App\Game\HordeArena;
use App\Game\MenuFlow;
use App\Game\LevelAssembler;
use App\Game\LevelGraph;
use App\Game\RhythmClock;
use App\Game\Run;
use App\Game\TreasureDirector;
use App\Gfx\WeaponRenderer;
use App\Gfx\SecondaryHud;
use App\Gfx\World;
use JumPHP\Loop\FixedStep;
use JumPHP\Renderer\Renderer;
use JumPHP\Scene\AbstractScene;
use JumPHP\Ui\Hud;

final class PlayScene extends AbstractScene
{
    private ?Run $run = null;
    private ?CampaignRun $campaign = null;
    private ?GameMode $mode = null;
    private MenuFlow $menu;
    private array $recentFrames = [];
    private ?float $lastFrameAt = null;
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
    private float $pointerCooldown = 0.0;
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
        $this->campaign = null;
        $this->demo = (bool)($params['demo'] ?? false);
        $this->debug = (bool)($params['debug'] ?? false);
        $this->menu = new MenuFlow();
        $this->pointerCooldown = 0.0;
        $this->recentFrames = [];
        $this->lastFrameAt = null;
        $this->paused = false;
        $this->welcome = !$this->demo && !($params['playing'] ?? false);
        if ($this->welcome) {
            $this->run = null; $this->mode = null; $this->objective = null; $this->assembledLevel = null;
            $this->levelSeed = 0;
            return;
        }
        $this->mode = $params['gameMode'] ?? GameMode::fixed((int)($params['seed'] ?? ($this->demo ? 1337 : GameMode::normal()->seed)), (int)($params['depth'] ?? 7));
        $this->menu->mode = $this->mode->kind;
        $this->menu->open('play');
        $this->procedural = $this->mode->kind !== GameMode::HORDE && (bool)($params['procedural'] ?? true);
        $this->levelSeed = $this->mode->seed;
        $this->proceduralDepth = $this->mode->depth;
        if ($this->procedural) {
            $graph = new LevelGraph($this->levelSeed, $this->proceduralDepth);
            $this->assembledLevel = (new LevelAssembler())->assemble($graph);
            $map = $this->assembledLevel->map;
        } else {
            $this->assembledLevel = null;
            $map = $this->mode->kind === GameMode::HORDE ? HordeArena::build() : BrushMap::load($this->engine->config->path('assets/maps/quake-dm1.map'));
        }
        $this->run = new Run($map, $this->levelSeed, $this->mode);
        if ($this->assembledLevel !== null) $this->run->setObjective($this->assembledLevel, $this->mode->finalWaveSize);
        if($this->mode->kind===GameMode::DAILY){
            $this->campaign=CampaignRun::daily(substr($this->mode->dailyId,0,10),$params['dailySeeds']??null,$params['dailyServerId']??null);
            $this->run=$this->campaign->run;$this->assembledLevel=$this->campaign->level;$this->mode=$this->run->mode;
            $this->levelSeed=$this->mode->seed;$this->proceduralDepth=$this->mode->depth;
        }
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
        $this->music->reset($this->campaign?->seed??$this->levelSeed);
    }

    private function loadAssetLayer(): void
    {
        foreach ([
            'weapon_colt' => 'assets/generated/weapons/pistol.png',
            'weapon_mp44' => 'assets/generated/weapons/smg.png',
            'weapon_bar' => 'assets/generated/weapons/assault.png',
            'weapon_gatling' => 'assets/generated/weapons/gatling.png',
        ] as $key => $path) {
            if (!$this->sdl()->hasTexture($key)) $this->sdl()->loadTexture($key, $path);
        }
        foreach (SecondaryHud::textures() as $key=>$path) {
            if (!$this->sdl()->hasTexture($key)) $this->sdl()->loadTexture($key,$path);
        }
        RiftSfx::register($this->sound());
    }

    public function update(float $dt): void
    {
        // Engine caps physics dt at 100 ms; benchmarking must retain real stalls.
        $now = microtime(true);
        $elapsedMs = $this->lastFrameAt === null ? $dt * 1000 : max(0.0, ($now - $this->lastFrameAt) * 1000);
        $this->lastFrameAt = $now;
        $this->frameMs = $this->frameMs * 0.9 + $elapsedMs * 0.1;
        $this->recentFrames[] = $elapsedMs;
        if (count($this->recentFrames) > 120) array_shift($this->recentFrames);
        $this->pointerCooldown = max(0.0, $this->pointerCooldown - $dt);
        $tap = $this->pointerCooldown > 0 ? null : $this->pointerPress();
        if ($this->run !== null && in_array($this->run->state, ['dead','won'], true)) {
            $end=$this->campaign?->state()??$this->run->state;
            if ($this->menu->screen !== $end) $this->menu->open($end);
            $this->restartDelay += $dt;
            if ($this->demo && $this->restartDelay > 1.0) { if($end==='level_complete')$this->nextCampaignMap();else $this->restartRun(true); return; }
        }
        if ($this->run !== null && $this->menu->screen === 'play' && ($this->actions()->isPressed('pause_game')
            || ($tap !== null && $tap[0] >= $this->width()-45 && $tap[1] >= 0 && $tap[1] < 24))) {
            $this->paused = true; $this->menu->open('pause'); $tap = null; $this->pointerCooldown = 0.35;
        } elseif ($this->menu->screen === 'pause' && $this->actions()->isPressed('pause_game')) {
            $this->paused = false; $this->menu->open('play'); return;
        }
        if ($this->menu->screen !== 'play') {
            if ($this->actions()->isPressed('upgrade_prev')) $this->menu->move(-1);
            if ($this->actions()->isPressed('upgrade_next')) $this->menu->move(1);
            if ($this->menu->screen === 'pause' && $this->actions()->isPressed('menu_restart')) { $this->restartRun(false); return; }
            $index = $tap === null ? null : $this->menu->hit($tap[0], $tap[1], $this->width());
            if ($index !== null || $this->actions()->isPressed('upgrade_select') || $this->actions()->isPressed('restart')) {
                $this->handleMenuAction($this->menu->activate($index));
                $this->pointerCooldown = 0.35;
            }
            return;
        }
        if ($this->run === null) return;

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
        $this->music->playSteps($this->rhythm->drain(), $this->run->level, $this->run->boss?->active && !$this->run->boss->defeated, count($this->run->enemies));
        if(($this->campaign?->state()??$this->run->state)==='won')$this->music->resolve();
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
        if ($run === null) { $r->clear([8,7,14]); $this->renderMenu($r); return; }
        $this->world->render($this->engine, $r, $run, $this->pitch);
        $font = $this->font();
        $w = $this->width(); $h = $this->height(); $cx = intdiv($w, 2); $cy = intdiv($h, 2);
        $hasSecondaries = $run->secondaryInventory() !== [];
        $objectiveCx = $hasSecondaries ? intdiv($w-60,2) : $cx;
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
            $liveOrbs = count(array_filter($boss->orbs, static fn(array $orb): bool => $orb['alive']));
            $label = $boss->vulnerable ? 'COEUR OUVERT '.(int)ceil($boss->vulnerableTimer).' S : TIREZ !' : 'ORBES RESTANTES : '.$liveOrbs.'/3';
            $font->draw($r, $label . ' / PHASE ' . $boss->phase, $objectiveCx, 40, 1, $boss->vulnerable ? [100,255,130] : [255,160,90], 'center');
            Hud::bar($r, $hasSecondaries ? 30 : 60, 51, $w - 120, 5, $boss->health / $boss->maxHealth);
            if ($run->bossWarning !== null) $font->draw($r, 'DANGER AU SOL : BOUGEZ !', $objectiveCx, 61, 1, [255,100,65], 'center');
        }
        $messageY = $run->boss?->active && !$run->boss->defeated ? 84 : 38;
        if ($this->procedural && $this->assembledLevel !== null && $this->assembledLevel->treasureAnchor !== null && $run->state === 'play') {
            if ($this->objective !== null && $this->objective->state !== TreasureDirector::EXPLORING) {
                $objectiveText = $this->objective->objectiveLabel();
                if ($this->objective->state === TreasureDirector::FINAL_WAVE) {
                    $objectiveText .= '  ' . ($run->remaining + count($run->enemies));
                }
                $font->draw($r, $objectiveText, $objectiveCx, 27, 1, [255,205,90], 'center', [8,5,12]);
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
                $font->draw($r, $label, $objectiveCx, 27, 1, [255,205,90], 'center', [8,5,12]);
            }

        }
        if ($run->messageTime > 0 && $run->state === 'play' && !($run->boss?->active && !$run->boss->defeated)) $font->draw($r, $run->message, $objectiveCx, $messageY, 1, $cream, 'center', [8,5,12]);
        if (!$hasSecondaries && $run->time < 6 && !$this->demo && $run->state === 'play' && !($run->boss?->active ?? false)) {
            $font->draw($r, 'SUIVEZ LA FLÈCHE / RAMASSEZ L\'XP', $cx, $cy - 42, 1, $cream, 'center', [8,5,12]);
        }
        if (!$hasSecondaries && $run->time < 8 && !$this->demo && !($run->boss?->active ?? false)) $font->draw($r, 'Z/S : AVANCER / FLÈCHES : TOURNER / ESPACE : SAUT', $cx, $h - 28, 1, $cream, 'center', [8,5,12]);
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
        if ($this->menu->screen === 'play' && $run->state === 'play') SecondaryHud::render($r,$this->sdl(),$font,$run,$w);
        if ($this->menu->screen !== 'play') $this->renderMenu($r);
        else {
            $r->fillRect($w - 44, 0, 44, 23, [8,5,12,235]);
            $font->draw($r, 'PAUSE', $w - 22, 7, 1, $cyan, 'center');
            if ($this->mode?->kind !== GameMode::NORMAL) $font->draw($r, $this->mode->label(), $cx, $h-31, 1, $cyan, 'center', [8,5,12]);
        }
        if ($this->demo) $font->draw($r, 'DEMO', $w - 4, 27, 1, $cream, 'right');
        if ($this->debug && $this->menu->screen === 'play') {
            $balance = $this->difficulty->snapshot($run);
            $font->draw($r, sprintf('FPS %.0f  %.1f MS  E %d/%d  RLOS %d  SLOS %d  MAP %d  NEI %d',
                1000.0 / max(1.0, $this->frameMs), $this->frameMs, $this->world->visibleEnemies,
                count($run->enemies), $this->world->renderLosTests, $run->losTests,
                $this->world->mapChunksDrawn, $run->neighborChecks), 4, 28, 1, $cyan);
            $font->draw($r, sprintf('TR %d  COR %d  IMP %d  COL %d  PICK %d', count($run->traces), count($run->corpses),
                min(count($run->traces), World::MAX_IMPACTS), $run->collisionTests, count($run->pickups)), 4, 38, 1, $cyan);
            $font->draw($r, sprintf('CLAVIER  ROTATION %.2f RAD/S  %s  BPM %.0f  ARME %s', Run::TURN_SPEED, $arsenal, $this->rhythm->bpm($run->level), $this->weaponRenderer->state()), 4, 48, 1, $cyan);
            $font->draw($r, sprintf('PUISS. x%.2f  DPS %.0f  PV x%.2f  DÉGÂTS x%.2f',
                $balance['powerIndex'], $balance['primaryDps'], $balance['healthScale'], $balance['damageScale']), 4, 58, 1, $cyan);
            if ($this->mode?->kind === GameMode::HORDE) {
                $avg = array_sum($this->recentFrames) / max(1, count($this->recentFrames));
                $font->draw($r, sprintf('HORDE V%d / ACTIFS %d / GÉN. %d / ÉLIM. %d', $run->wave, count($run->enemies), $run->spawned, $run->kills), 4, 78, 1, $cyan);
                $font->draw($r, sprintf('MOY %.1f MS / MAX %.1f MS / %.0f FPS', $avg, max($this->recentFrames ?: [0]), 1000/max(0.1,$avg)), 4, 88, 1, $cyan);
                $font->draw($r, sprintf('OBJETS %d / PHP %.1f MO', count($run->pickups), memory_get_usage(true)/1048576), 4, 98, 1, $cyan);
            }
            if ($this->procedural && $this->assembledLevel !== null) {
                $objectiveState = $this->objective?->state ?? 'none';
                $font->draw($r, sprintf('PROC SEED %d  ROOMS %d  DEPTH %d  OBJ %s', $this->levelSeed, count($this->assembledLevel->rooms), $this->proceduralDepth, $objectiveState), 4, 68, 1, $cyan);
            }
        }
    }

    private function nextCampaignMap(): void
    {
        if($this->campaign===null||!$this->campaign->advance())return;
        $this->world->release($this->engine->gl());
        $this->run=$this->campaign->run;$this->assembledLevel=$this->campaign->level;$this->objective=$this->run->objective;
        $this->mode=$this->run->mode;$this->levelSeed=$this->mode->seed;$this->proceduralDepth=$this->mode->depth;
        $this->world->prepare($this->engine->gl(),$this->run);$this->weaponRenderer->reset($this->run->time);
        $this->menu->open('play');$this->paused=false;$this->restartDelay=0;$this->jumpPending=false;
        // Keep RhythmClock and music phrase counters: this is the same run.
    }

    private function startMode(GameMode $mode): void
    {
        $this->enter(['demo'=>$this->demo, 'debug'=>$this->debug, 'playing'=>true, 'gameMode'=>$mode]);
    }

    private function restartRun(bool $sameMap = false): void
    {
        if ($this->mode === null) return;
        $mode = $this->mode;
        if (!$sameMap && $mode->kind === GameMode::NORMAL && !$this->demo) $mode = GameMode::normal($mode->seed);
        if (!$sameMap && $mode->kind === GameMode::HORDE) $mode = GameMode::horde();
        $this->enter(['demo'=>$this->demo,'debug'=>$this->debug,'playing'=>true,
            'gameMode'=>$mode,'procedural'=>$this->procedural,'dailySeeds'=>$this->campaign?->stageSeeds]);
    }

    private function handleMenuAction(?string $action): void
    {
        switch ($action) {
            case 'normal': case 'new': $this->startMode(GameMode::normal($this->mode?->seed)); break;
            case 'horde': $this->startMode(GameMode::horde()); break;
            case 'replay': case 'retry': $this->restartRun(true); break;
            case 'restart': $this->restartRun(false); break;
            case 'resume': $this->paused = false; break;
            case 'daily_start': $this->startMode(GameMode::daily(GameMode::today())); break;
            case 'next_map': $this->nextCampaignMap();break;
            case 'menu': case 'back': case 'daily': case 'controls':
                if ($this->run !== null && isset($this->world)) $this->world->release($this->engine->gl());
                $this->run = null; $this->mode = null; $this->objective = null; $this->assembledLevel = null;
                $this->levelSeed = 0; $this->paused = false; $this->welcome = true;
                break;
        }
    }

    private function renderMenu(Renderer $r): void
    {
        $font = $this->font(); $w = $this->width(); $cx = intdiv($w,2);
        $r->fillRect(8, 4, $w-16, $this->height()-8, [8,7,14,255]);
        $title = match ($this->menu->screen) {
            'daily'=>'DÉFI DU JOUR', 'controls'=>'COMMANDES', 'pause'=>'PAUSE', 'dead'=>'VOUS ÊTES MORT', 'won'=>'VICTOIRE !', 'level_complete'=>'MAP TERMINÉE', default=>'SLAVE RIFT',
        };
        $font->draw($r, $title, $cx, 12, 2, [90,235,225], 'center');
        if ($this->menu->screen === 'daily') $font->draw($r, GameMode::dateLabel(GameMode::today()), $cx, 32, 1, [240,215,170], 'center');
        if (in_array($this->menu->screen,['dead','won','level_complete'],true) && $this->run !== null) {
            $font->draw($r, 'SCORE '.$this->run->score.' / '.$this->clock($this->run->time), $cx, 40, 1, [240,215,170], 'center');
            $font->draw($r, 'VAGUE '.$this->run->wave.' / '.$this->run->kills.' ÉLIMINATIONS', $cx, 54, 1, [240,215,170], 'center');
        }
        if ($this->menu->screen === 'controls') {
            foreach (['Z/W, S : AVANCER / RECULER','Q/A, D : DÉPLACEMENT LATÉRAL','FLÈCHES : TOURNER / ESPACE : SAUT','TIRS AUTOMATIQUES : VISEZ DEVANT','TACTILE : JOYSTICK / SAUT / PAUSE','MANETTE : CROIX / A : VALIDER','MENUS : TOUCHER OU FLÈCHES + ENTRÉE'] as $i=>$line) $font->draw($r,$line,$cx,40+$i*12,1,[240,215,170],'center');
        }
        foreach (array_values($this->menu->items()) as $i=>$label) {
            [$x,$y,$bw,$bh] = $this->menu->rect($i,$w);
            $r->fillRect($x,$y,$bw,$bh,$i===$this->menu->cursor ? [45,85,95,255] : [25,42,55,255]);
            $font->draw($r,($i===$this->menu->cursor ? '> ' : '').$label,$cx,$y+intdiv($bh-7,2),1,[110,255,235],'center');
        }
        $font->draw($r,'TOUCHER / FLÈCHES / ENTRÉE / A',$cx,$this->height()-10,1,[240,215,170],'center');
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

    private function clock(float $time): string
    {
        return sprintf('%02d:%02d', intdiv((int)$time, 60), (int)$time % 60);
    }
}
