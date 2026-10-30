<?php

namespace JumPHP\Input;

use Evenement\EventEmitter;
use JumPHP\Renderer\Renderer;

/**
 * JumPHP InputManager with one local addition: SDL_MOUSEWHEEL is forwarded to Mouse::$wheelY.
 * Keep this file close to upstream so it can be deleted once JumPHP exposes wheel input itself.
 */
class InputManager extends EventEmitter
{
    private const SDLK_AC_BACK = 0x4000010E;

    public function __construct(
        private Keyboard $keyboard,
        private Mouse $mouse,
        private Touch $touch,
        private Gamepad $gamepad,
        private ActionMap $actions,
    ) {
    }

    private ?TouchControls $controls = null;

    public function setControls(?TouchControls $controls): void { $this->controls = $controls; }
    public function getControls(): ?TouchControls { return $this->controls; }

    public function pressKey(int $key): void
    {
        $this->keyboard->down($key);
        $this->emit('key', [$key]);
    }

    public function poll(?Renderer $renderer = null): void
    {
        $this->controls?->beforeEvents();
        $event = new \SDL_Event();
        while (\SDL_PollEvent($event)) {
            switch ($event->type) {
                case \SDL_QUIT:
                    $this->emit('quit');
                    break;
                case \SDL_KEYDOWN:
                case \SDL_KEYUP:
                    $sym = $event->key->keysym->sym;
                    if ($sym === self::SDLK_AC_BACK) $sym = \SDLK_ESCAPE;
                    if ($event->type === \SDL_KEYUP) {
                        $this->keyboard->up($sym);
                    } elseif ($sym === $event->key->keysym->sym) {
                        $this->keyboard->handle($event);
                        $this->emit('key', [$sym]);
                    } elseif (!$this->keyboard->isKeyHeld($sym)) {
                        $this->pressKey($sym);
                    }
                    break;
                case \SDL_MOUSEMOTION:
                    if ($event->motion !== null) $this->moveMouse($renderer, $event->motion->x, $event->motion->y);
                    else $this->mouseFromState($renderer, null);
                    break;
                case \SDL_MOUSEBUTTONDOWN:
                case \SDL_MOUSEBUTTONUP:
                    $down = $event->type === \SDL_MOUSEBUTTONDOWN;
                    if ($event->button !== null) {
                        if ($down) $this->moveMouse($renderer, $event->button->x, $event->button->y);
                        $down ? $this->mouse->down($event->button->button) : $this->mouse->up($event->button->button);
                    } else {
                        $this->mouseFromState($renderer, $down);
                    }
                    break;
                case \SDL_MOUSEWHEEL:
                    if (isset($event->wheel) && $event->wheel !== null) {
                        $this->mouse->wheel((int)$event->wheel->y);
                    }
                    break;
                case \SDL_FINGERDOWN:
                case \SDL_FINGERUP:
                case \SDL_FINGERMOTION:
                    if ($event->tfinger !== null && !($renderer !== null && $this->controls?->handle($event, $renderer))) {
                        $this->touch->handle($event);
                    }
                    break;
                case \SDL_JOYBUTTONDOWN:
                case \SDL_JOYBUTTONUP:
                case \SDL_JOYAXISMOTION:
                    $this->gamepad->handle($event);
                    break;
                case 0x605:
                    $this->gamepad->open();
                    break;
                case 0x606:
                    for ($i = $this->gamepad->count() - 1; $i >= 0; $i--) $this->gamepad->close($i);
                    $this->gamepad->open();
                    break;
                case \SDL_WINDOWEVENT:
                    if ($event->window !== null) {
                        $w = $event->window;
                        $this->emit('window', [(int)$w->event, (int)$w->data1, (int)$w->data2]);
                        if ($w->event === \SDL_WINDOWEVENT_SIZE_CHANGED) $this->emit('resize', [$w->data1, $w->data2]);
                    }
                    break;
            }
        }
        $this->gamepad->update();
    }

    private function mouseFromState(?Renderer $renderer, ?bool $down): void
    {
        $x = 0; $y = 0;
        $mask = (int)\SDL_GetMouseState($x, $y);
        if ($down !== false) $this->moveMouse($renderer, (float)$x, (float)$y);
        if ($down === null) return;
        $changed = false;
        for ($button = 1; $button <= 5; $button++) {
            $isDown = ($mask & (1 << ($button - 1))) !== 0;
            if ($down && $isDown && !$this->mouse->isHeld($button)) {
                $this->mouse->down($button); $changed = true;
            } elseif (!$down && !$isDown && $this->mouse->isHeld($button)) {
                $this->mouse->up($button); $changed = true;
            }
        }
        if (!$changed) $down ? $this->mouse->down(1) : $this->mouse->up(1);
    }

    private function moveMouse(?Renderer $renderer, float $x, float $y): void
    {
        if ($renderer !== null) [$x, $y] = $renderer->windowToView($x, $y);
        $this->mouse->move($x, $y);
    }

    public function endFrame(): void
    {
        $this->keyboard->resetTransientStates();
        $this->mouse->resetTransientStates();
        $this->touch->resetTransientStates();
        $this->gamepad->resetTransientStates();
        $this->actions->endFrame();
    }

    public function getKeyboard(): Keyboard { return $this->keyboard; }
    public function getMouse(): Mouse { return $this->mouse; }
    public function getTouch(): Touch { return $this->touch; }
    public function getGamepad(): Gamepad { return $this->gamepad; }
    public function getActions(): ActionMap { return $this->actions; }
}
