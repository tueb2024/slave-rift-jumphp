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
    private float $restartDelay = 0.0;

    public function enter(array $params = []): void
    {
        $this->demo = (bool)($params['demo'] ?? false);
        $map = BrushMap::load($this->engine->config->path('assets/maps/rift01.map'));
        $this->run = new Run($map);
        $this->world = new World();
        $this->step = new FixedStep(120);
        $this->restartDelay = 0.0;
        $this->world->prepare($this->engine->gl(), $this->run);
    }

    public function update(float $dt): void
    {
        if ($this->run->state !== 'play') {
            $this->restartDelay += $dt;
            if (($this->demo && $this->restartDelay > 0.5) || $this->actions()->isPressed('restart')) {
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
            $fire = $this->actions()->isHeld('fire') || $this->actions()->isPressed('fire');
        }

        $this->step->run($dt, fn (float $h) => $this->run->step($h, $forward, $strafe, $turn, $fire));
    }

    public function render(Renderer $r): void
    {
        $this->world->render($this->engine, $r, $this->run);
        $font = $this->font();
        $w = $this->width();
        $h = $this->height();

        // HUD: intentionally chunky and close to the late-90s console-FPS feel.
        $r->fillRect(0, 0, $w, 12, [0, 0, 0, 190]);
        $font->draw($r, 'HP ' . max(0, $this->run->health), 4, 3, 1, [240, 205, 142]);
        $font->draw($r, 'GUARD ' . max(0, $this->run->guardian['health']), $w - 4, 3, 1, [210, 85, 72], 'right');
        Hud::bar($r, 4, 13, 74, 3, max(0.0, min(1.0, $this->run->health / 100)));
        Hud::bar($r, $w - 78, 13, 74, 3, max(0.0, min(1.0, $this->run->guardian['health'] / 80)));

        // Crosshair.
        $cx = intdiv($w, 2);
        $cy = intdiv($h, 2);
        $cross = $this->run->muzzle > 0 ? [255, 225, 120] : [185, 190, 175];
        $r->drawLine($cx - 5, $cy, $cx - 2, $cy, $cross);
        $r->drawLine($cx + 2, $cy, $cx + 5, $cy, $cross);
        $r->drawLine($cx, $cy - 5, $cx, $cy - 2, $cross);
        $r->drawLine($cx, $cy + 2, $cx, $cy + 5, $cross);

        // Crude original weapon silhouette: deliberately generated, no borrowed art.
        $kick = $this->run->muzzle > 0 ? 5 : 0;
        $r->fillRect($cx - 18, $h - 28 + $kick, 36, 25, [56, 48, 58]);
        $r->fillRect($cx - 7, $h - 40 + $kick, 14, 22, [110, 82, 58]);
        $r->fillRect($cx - 3, $h - 48 + $kick, 6, 12, $this->run->muzzle > 0 ? [255, 180, 60] : [80, 70, 72]);

        if ($this->run->state !== 'play') {
            $r->fillRect(48, 66, $w - 96, 46, [0, 0, 0, 210]);
            $label = $this->run->state === 'clear' ? 'RIFT CLEARED' : 'YOU FELL';
            $font->draw($r, $label, $cx, 76, 2, $this->run->state === 'clear' ? [130, 240, 190] : [245, 90, 76], 'center');
            $font->draw($r, $this->demo ? 'DEMO RESTART' : 'ENTER TO RESTART', $cx, 99, 1, [220, 210, 190], 'center');
        }
        if ($this->demo) {
            $font->draw($r, 'DEMO', $w - 4, $h - 9, 1, [230, 220, 190], 'right', [0, 0, 0]);
        }
    }
}
