<?php

namespace App\Scene;

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
    private bool $demo = false;
    private bool $paused = false;
    private bool $jumpPending = false;
    private float $restartDelay = 0.0;

    public function enter(array $params = []): void
    {
        $this->demo = (bool)($params['demo'] ?? false);
        $map = BrushMap::load($this->engine->config->path('assets/maps/rift01.map'));
        $this->run = new Run($map, (int)($params['seed'] ?? ($this->demo ? 1337 : random_int(1, 65520))));
        // Reuse GPU buffers on restart: do not leak a world mesh every time the player dies.
        $this->world ??= new World();
        $this->step = new FixedStep(60, 8);
        $this->restartDelay = 0.0;
        $this->paused = false;
        $this->jumpPending = false;
        $this->world->prepare($this->engine->gl(), $this->run);
    }

    public function update(float $dt): void
    {
        if ($this->actions()->isPressed('pause_game')) $this->paused = !$this->paused;
        if ($this->paused) return;
        if ($this->run->state === 'upgrade') {
            foreach (['upgrade_one', 'upgrade_two', 'upgrade_three'] as $slot => $action) {
                if (($this->demo && $slot === 0) || $this->actions()->isPressed($action)) {
                    $this->run->chooseUpgrade($slot);
                    break;
                }
            }
            return;
        }
        if ($this->run->state === 'dead') {
            $this->restartDelay += $dt;
            if (($this->demo && $this->restartDelay > 1.0) || $this->actions()->isPressed('restart')) {
                $this->enter(['demo' => $this->demo]);
            }
            return;
        }
        if ($this->demo) {
            [$forward, $strafe, $turn, $fire] = $this->run->autopilot();
        } else {
            $forward = $this->actions()->axis('move_back', 'move_forward');
            $strafe = $this->actions()->axis('strafe_left', 'strafe_right');
            $turn = $this->actions()->axis('turn_left', 'turn_right');
            $fire = $this->actions()->isHeld('fire');
            $this->jumpPending = $this->jumpPending || $this->actions()->isPressed('jump');
        }
        $this->step->run($dt, function (float $h) use ($forward, $strafe, $turn, $fire): void {
            $this->run->step($h, $forward, $strafe, $turn, $fire, $this->jumpPending);
            $this->jumpPending = false;
        });
    }

    public function render(Renderer $r): void
    {
        $run = $this->run;
        $this->world->render($this->engine, $r, $run);
        $font = $this->font();
        $w = $this->width(); $h = $this->height(); $cx = intdiv($w, 2); $cy = intdiv($h, 2);
        $cream = [240,215,170]; $cyan = [90,235,225];
        $r->fillRect(0, 0, $w, 23, [6,5,12,225]);
        $font->draw($r, 'HP ' . $run->health . '/' . $run->maxHealth, 4, 3, 1, $cream);
        $font->draw($r, 'W' . $run->wave . '  K' . $run->kills . '  ' . $this->clock($run->time), $w - 4, 3, 1, $cream, 'right');
        Hud::bar($r, 4, 13, 82, 4, max(0.0, $run->health / $run->maxHealth));
        $font->draw($r, 'LV' . $run->level . ' XP ' . $run->xp . '/' . $run->nextXp, 96, 13, 1, $cyan);
        Hud::bar($r, $w - 70, 14, 66, 3, min(1.0, $run->xp / $run->nextXp));
        $cross = $run->muzzle > 0 ? [255,225,120] : [185,190,175];
        $r->drawLine($cx - 4, $cy, $cx - 2, $cy, $cross);
        $r->drawLine($cx + 2, $cy, $cx + 4, $cy, $cross);
        $r->drawLine($cx, $cy - 4, $cx, $cy - 2, $cross);
        $r->drawLine($cx, $cy + 2, $cx, $cy + 4, $cross);

        $kick = $run->muzzle > 0 ? 4 : 0;
        $bob = (int)round(sin($run->time * 6) * 1.5);
        $r->fillRect($cx - 21, $h - 29 + $kick + $bob, 42, 28, [48,41,54]);
        $r->fillRect($cx - 12, $h - 40 + $kick + $bob, 24, 22, [95,95,101]);
        $r->fillRect($cx - 7, $h - 46 + $kick + $bob, 14, 24, [138,100,65]);
        $r->fillRect($cx - 3, $h - 47 + $kick + $bob, 6, 7, [30,30,35]);
        if ($run->muzzle > 0) {
            $r->fillRect($cx - 6, $h - 53 + $kick + $bob, 12, 8, [255,190,55]);
            $r->fillRect($cx - 2, $h - 57 + $kick + $bob, 4, 14, [255,245,175]);
        }
        if ($run->pulseUnlocked) {
            $r->fillRect($w - 41, $h - 30, 24, 23, [43,63,80]);
            $r->fillRect($w - 33, $h - 37, 8, 20, $run->pulse > 0 ? [180,255,255] : [70,180,195]);
        }
        $r->fillRect(0, $h - 10, $w, 10, [6,5,12,225]);
        $font->draw($r, 'RIVETER' . ($run->pulseUnlocked ? ' + PULSE' : ' / PULSE AT W3'), 4, $h - 8, 1, $cyan);
        $font->draw($r, count($run->enemies) . ' HOSTILES', $w - 4, $h - 8, 1, $cream, 'right');
        if ($run->messageTime > 0 && $run->state === 'play') {
            $font->draw($r, $run->message, $cx, 29, 1, $cream, 'center', [8,5,12]);
        }
        if ($run->time < 8 && !$this->demo) {
            $font->draw($r, 'WASD MOVE / ARROWS TURN / SPACE JUMP', $cx, $h - 21, 1, $cream, 'center', [8,5,12]);
        }
        if ($run->hurt > 0) {
            $r->fillRect(0, 23, 3, $h - 33, [240,55,55]);
            $r->fillRect($w - 3, 23, 3, $h - 33, [240,55,55]);
        }
        if ($run->state === 'upgrade') {
            $r->fillRect(12, 42, $w - 24, 104, [8,6,16,245]);
            $font->draw($r, 'LEVEL ' . $run->level . ' - CHOOSE', $cx, 49, 2, $cyan, 'center');
            foreach ($run->choices as $slot => $choice) {
                $font->draw($r, ($slot + 1) . '  ' . $choice, 24, 72 + $slot * 22, 1, $cream);
                $font->draw($r, $run->upgradeDescription($choice), 24, 81 + $slot * 22, 1, $cyan);
            }
            $font->draw($r, '1 / 2 / 3  (OR R / T / Y)', $cx, 135, 1, $cream, 'center');
        } elseif ($run->state === 'dead') {
            $r->fillRect(26, 56, $w - 52, 69, [8,5,12,235]);
            $font->draw($r, 'RIFT CLAIMED YOU', $cx, 65, 2, [245,90,76], 'center');
            $font->draw($r, 'WAVE ' . $run->wave . ' / ' . $run->kills . ' KILLS / ' . $this->clock($run->time), $cx, 88, 1, $cream, 'center');
            $font->draw($r, $this->demo ? 'DEMO RESTART' : 'ENTER TO TRY AGAIN', $cx, 109, 1, $cyan, 'center');
        }
        if ($this->paused) {
            $r->fillRect(82, 72, $w - 164, 30, [8,5,12,235]);
            $font->draw($r, 'PAUSED - P', $cx, 83, 1, $cream, 'center');
        }
        if ($this->demo) $font->draw($r, 'DEMO', $w - 4, 27, 1, $cream, 'right');
    }

    private function clock(float $time): string
    {
        return sprintf('%02d:%02d', intdiv((int)$time, 60), (int)$time % 60);
    }
}
