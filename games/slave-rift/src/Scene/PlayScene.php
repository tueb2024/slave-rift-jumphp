<?php

namespace App\Scene;

use App\Audio\RiftMusic;
use App\Audio\RiftSfx;
use App\Game\BrushMap;
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
    private int $musicWave = 1;
    private bool $demo = false;
    private bool $paused = false;
    private bool $jumpPending = false;
    private float $restartDelay = 0.0;
    private float $pitch = 0.0;
    private ?float $mouseX = null;
    private ?float $mouseY = null;
    private float $prevMuzzle = 0.0;
    private float $prevPulse = 0.0;
    private float $prevSpecial = 0.0;
    private string $prevState = 'play';
    private int $prevXp = 0;
    private int $upgradeCursor = 0;
    // SDL/browser deltas are already relative to the previous poll; keep this
    // frame-rate independent and conservative for both pointer-lock and fallback.
    private const MOUSE_SENS_REL = 0.0010;
    private const MOUSE_SENS_ABS = 0.0015;

    public function enter(array $params = []): void
    {
        $this->demo = (bool)($params['demo'] ?? false);
        $map = BrushMap::load($this->engine->config->path('assets/maps/quake-dm1.map'));
        $this->run = new Run($map, (int)($params['seed'] ?? ($this->demo ? 1337 : random_int(1, 65520))));
        $this->world ??= new World();
        $this->step = new FixedStep(60, 8);
        $this->restartDelay = 0.0;
        $this->paused = false;
        $this->jumpPending = false;
        $this->pitch = 0.0;
        $this->mouseX = $this->mouseY = null;
        $this->prevMuzzle = $this->prevPulse = $this->prevSpecial = 0.0;
        $this->prevState = 'play';
        $this->prevXp = 0;
        $this->upgradeCursor = 0;
        $this->world->prepare($this->engine->gl(), $this->run);
        $this->loadAssetLayer();

        if (!$this->demo && function_exists('SDL_SetRelativeMouseMode')) @\SDL_SetRelativeMouseMode(true);

        if (!isset($this->music)) {
            $this->music = new RiftMusic($this->sound());
            $this->music->register();
        }
        $this->musicWave = $this->run->wave;
        $this->music->reset($this->musicWave);
    }

    private function loadAssetLayer(): void
    {
        if (!$this->sdl()->hasTexture('weapons')) {
            $this->sdl()->loadTexture('weapons', 'assets/sprites/weapons/fps-weapons-cc0.png');
        }
        if (!$this->sdl()->hasTexture('muzzle_flash')) {
            $this->sdl()->loadTexture('muzzle_flash', 'assets/sprites/effects/muzzle-large-fire.png');
        }
        RiftSfx::register($this->sound());
    }

    public function update(float $dt): void
    {
        if ($this->actions()->isPressed('pause_game')) {
            $this->paused = !$this->paused;
            $this->mouseX = $this->mouseY = null;
            if (!$this->demo && function_exists('SDL_SetRelativeMouseMode')) @\SDL_SetRelativeMouseMode(!$this->paused);
        }
        if ($this->paused) return;
        if ($this->run->state === 'upgrade') {
            $mouse = $this->engine->input->getMouse();
            if (!$this->demo && $mouse->x >= 12 && $mouse->x < $this->width() - 12) {
                foreach ($this->run->choices as $slot => $_choice) {
                    if ($mouse->y >= 68 + $slot * 22 && $mouse->y < 90 + $slot * 22) {
                        $this->upgradeCursor = $slot;
                        break;
                    }
                }
            }
            foreach (['upgrade_one', 'upgrade_two', 'upgrade_three'] as $slot => $action) {
                if ($this->actions()->isPressed($action)) {
                    $this->upgradeCursor = $slot;
                    $this->run->chooseUpgrade($slot);
                    if (function_exists('SDL_SetRelativeMouseMode')) @\SDL_SetRelativeMouseMode(true);
                    return;
                }
            }
            if ($this->demo) {
                $this->run->chooseUpgrade(0);
                return;
            }
            if ($mouse->isPressed(1)) {
                $this->run->chooseUpgrade($this->upgradeCursor);
                if (function_exists('SDL_SetRelativeMouseMode')) @\SDL_SetRelativeMouseMode(true);
            }
            return;
        }
        if ($this->run->state === 'dead') {
            $this->restartDelay += $dt;
            if (($this->demo && $this->restartDelay > 1.0) || $this->actions()->isPressed('restart')) $this->enter(['demo' => $this->demo]);
            return;
        }

        $special = false;
        if ($this->demo) {
            [$forward, $strafe, $turn, $fire] = $this->run->autopilot();
        } else {
            $forward = $this->actions()->axis('move_back', 'move_forward');
            $strafe = $this->actions()->axis('strafe_left', 'strafe_right');
            $turn = $this->actions()->axis('turn_left', 'turn_right');
            $mouse = $this->engine->input->getMouse();
            $fire = $this->actions()->isHeld('fire') || $mouse->isHeld(1);
            $special = $mouse->isHeld(3) || $this->actions()->isHeld('special_fire');
            $this->jumpPending = $this->jumpPending || $this->actions()->isPressed('jump');

            $mx = 0.0; $my = 0.0; $relativeWorked = false;
            if (function_exists('SDL_GetRelativeMouseState')) {
                $rx = 0; $ry = 0;
                @\SDL_GetRelativeMouseState($rx, $ry);
                $rx = max(-80, min(80, (int)$rx));
                $ry = max(-80, min(80, (int)$ry));
                if ($rx !== 0 || $ry !== 0) {
                    $mx = $rx * self::MOUSE_SENS_REL;
                    $my = $ry * self::MOUSE_SENS_REL;
                    $relativeWorked = true;
                }
                if (function_exists('SDL_GetRelativeMouseMode') && !@\SDL_GetRelativeMouseMode() && ($mouse->isHeld(1) || $mouse->isHeld(3))) {
                    @\SDL_SetRelativeMouseMode(true);
                }
            }
            if (!$relativeWorked) {
                if ($this->mouseX !== null && $this->mouseY !== null) {
                    $dx = max(-32.0, min(32.0, $mouse->x - $this->mouseX));
                    $dy = max(-32.0, min(32.0, $mouse->y - $this->mouseY));
                    $mx = $dx * self::MOUSE_SENS_ABS;
                    $my = $dy * self::MOUSE_SENS_ABS;
                }
                $this->mouseX = $mouse->x;
                $this->mouseY = $mouse->y;
            } else {
                $this->mouseX = $mouse->x;
                $this->mouseY = $mouse->y;
            }
            $this->run->yaw -= $mx;
            $this->pitch = max(-1.20, min(1.20, $this->pitch - $my));
        }

        $beforeState = $this->run->state;
        $beforeXp = $this->run->xp;
        $this->step->run($dt, function (float $h) use ($forward, $strafe, $turn, $fire, $special): void {
            $this->run->step($h, $forward, $strafe, $turn, $fire, $this->jumpPending, $special);
            $this->jumpPending = false;
        });

        $this->playAudioEvents($beforeState, $beforeXp);
        if (!$this->demo && $beforeState === 'play' && $this->run->state === 'upgrade' && function_exists('SDL_SetRelativeMouseMode')) {
            @\SDL_SetRelativeMouseMode(false);
        }
        if ($this->run->wave !== $this->musicWave) {
            $this->musicWave = $this->run->wave;
            $this->music->onWaveStart($this->musicWave);
        }
        $this->music->update($dt, min(1.0, 0.32 + count($this->run->enemies) / 24.0));
    }

    private function playAudioEvents(string $beforeState, int $beforeXp): void
    {
        if ($this->run->muzzle > $this->prevMuzzle + 0.01) {
            $name = $this->run->autoTier >= 3 ? 'heavy_fire' : ($this->run->autoTier > 0 ? 'sten_fire' : 'colt_fire');
            $this->sound()->playSfx($name, 0, false);
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

        $cross = $run->specialFlash > 0 ? [170,255,255] : ($run->muzzle > 0 ? [255,225,120] : [185,190,175]);
        $r->drawLine($cx - 4, $cy, $cx - 2, $cy, $cross); $r->drawLine($cx + 2, $cy, $cx + 4, $cy, $cross);
        $r->drawLine($cx, $cy - 4, $cx, $cy - 2, $cross); $r->drawLine($cx, $cy + 2, $cx, $cy + 4, $cross);

        $kick = $run->muzzle > 0 ? 5 : 0;
        $bob = (int)round(sin($run->time * 6) * 1.5);
        $weapon = $this->sdl()->getTexture('weapons');
        // CC0 sheet: rows are knife, pistol, machine gun, chain gun; five 64px animation frames each.
        $weaponRow = $run->autoTier >= 3 ? 3 : ($run->autoTier > 0 ? 2 : 1);
        $weaponFrame = $run->muzzle > 0 ? 2 : 0;
        $drawW = $run->autoTier >= 3 ? 238 : 218;
        $drawH = $drawW;
        $r->copy($weapon['texture'], [$weaponFrame * 64, $weaponRow * 64, 64, 64],
            (int)($cx - $drawW / 2), (int)($h - $drawH + 48 + $kick + $bob), $drawW, $drawH);
        if ($run->muzzle > 0) {
            $flash = $this->sdl()->getTexture('muzzle_flash');
            $flashFrame = min(2, max(0, (int)floor((0.12 - min(0.12, $run->muzzle)) / 0.04)));
            $flashSize = max(38, min(104, (int)round($h * 0.28)));
            $r->copy($flash['texture'], [$flashFrame * 80, 0, 80, 80],
                (int)($cx - $flashSize / 2), (int)($h - 177 + $bob), $flashSize, $flashSize);
        }
        if ($run->pulseUnlocked) {
            $r->fillRect($w - 41, $h - 30, 24, 23, [43,63,80]);
            $r->fillRect($w - 33, $h - 37, 8, 20, $run->pulse > 0 ? [180,255,255] : [70,180,195]);
        }

        $r->fillRect(0, $h - 10, $w, 10, [6,5,12,225]);
        $weaponLabel = 'COLT .45' . ($run->autoTier > 0 ? ' + ' . $run->autoWeapon : '') . ($run->pulseUnlocked ? ' + PULSE' : '');
        $font->draw($r, $weaponLabel, 4, $h - 8, 1, $cyan);
        $special = $run->specialCooldown <= 0.0 ? 'RMB RIFT LANCE READY' : 'RIFT ' . number_format($run->specialCooldown, 1) . 's';
        $font->draw($r, $special, $w - 4, $h - 18, 1, $run->specialCooldown <= 0 ? [170,255,255] : $cream, 'right');
        $font->draw($r, count($run->enemies) . ' HOSTILES', $w - 4, $h - 8, 1, $cream, 'right');

        if ($run->messageTime > 0 && $run->state === 'play') $font->draw($r, $run->message, $cx, 29, 1, $cream, 'center', [8,5,12]);
        if ($run->time < 8 && !$this->demo) $font->draw($r, 'ZQSD MOVE / MOUSE LOOK / LMB COLT / RMB SPECIAL / SPACE JUMP', $cx, $h - 28, 1, $cream, 'center', [8,5,12]);
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
            $font->draw($r, 'HOVER SELECT / LMB CONFIRM  (1/2/3 FALLBACK)', $cx, 135, 1, $cream, 'center');
        } elseif ($run->state === 'dead') {
            $r->fillRect(26, 56, $w - 52, 69, [8,5,12,235]);
            $font->draw($r, 'RIFT CLAIMED YOU', $cx, 65, 2, [245,90,76], 'center');
            $font->draw($r, 'WAVE ' . $run->wave . ' / ' . $run->kills . ' KILLS / ' . $this->clock($run->time), $cx, 88, 1, $cream, 'center');
            $font->draw($r, $this->demo ? 'DEMO RESTART' : 'ENTER TO TRY AGAIN', $cx, 109, 1, $cyan, 'center');
        }
        if ($this->paused) { $r->fillRect(82, 72, $w - 164, 30, [8,5,12,235]); $font->draw($r, 'PAUSED - P', $cx, 83, 1, $cream, 'center'); }
        if ($this->demo) $font->draw($r, 'DEMO', $w - 4, 27, 1, $cream, 'right');
    }

    private function clock(float $time): string
    {
        return sprintf('%02d:%02d', intdiv((int)$time, 60), (int)$time % 60);
    }
}
