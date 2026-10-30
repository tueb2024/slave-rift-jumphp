<?php

namespace App\Input;

use JumPHP\Input\InputManager;
use JumPHP\Renderer\Renderer;

/**
 * Game-local InputManager extension adding SDL_MOUSEWHEEL support without
 * shadowing JumPHP classes in Composer. It remains type-compatible with Engine.
 */
final class WheelInputManager extends InputManager
{
    private const SDLK_AC_BACK = 0x4000010E;

    public int $wheelY = 0;

    public function poll(?Renderer $renderer = null): void
    {
        // TouchControls is optional in JumPHP's browser runtime; it exists in
        // the pinned desktop engine but not in every hosted runtime build.
        $controls = method_exists($this, 'getControls') ? $this->getControls() : null;
        $controls?->beforeEvents();
        $keyboard = $this->getKeyboard();
        $mouse = $this->getMouse();
        $touch = $this->getTouch();
        $gamepad = $this->getGamepad();

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
                        $keyboard->up($sym);
                    } elseif ($sym === $event->key->keysym->sym) {
                        $keyboard->handle($event);
                        $this->emit('key', [$sym]);
                    } elseif (!$keyboard->isKeyHeld($sym)) {
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
                        $down ? $mouse->down($event->button->button) : $mouse->up($event->button->button);
                    } else {
                        $this->mouseFromState($renderer, $down);
                    }
                    break;
                case \SDL_MOUSEWHEEL:
                    if ($event->wheel !== null) $this->wheelY += (int)$event->wheel->y;
                    break;
                case \SDL_FINGERDOWN:
                case \SDL_FINGERUP:
                case \SDL_FINGERMOTION:
                    if ($event->tfinger !== null && !($renderer !== null && $controls?->handle($event, $renderer))) {
                        $touch->handle($event);
                    }
                    break;
                case \SDL_JOYBUTTONDOWN:
                case \SDL_JOYBUTTONUP:
                case \SDL_JOYAXISMOTION:
                    $gamepad->handle($event);
                    break;
                case 0x605:
                    $gamepad->open();
                    break;
                case 0x606:
                    for ($i = $gamepad->count() - 1; $i >= 0; $i--) $gamepad->close($i);
                    $gamepad->open();
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
        $gamepad->update();
    }

    public function consumeWheelY(): int
    {
        $value = $this->wheelY;
        $this->wheelY = 0;
        return $value;
    }

    public function endFrame(): void
    {
        parent::endFrame();
        $this->wheelY = 0;
    }

    private function mouseFromState(?Renderer $renderer, ?bool $down): void
    {
        $mouse = $this->getMouse();
        $x = 0; $y = 0;
        $mask = (int)\SDL_GetMouseState($x, $y);
        if ($down !== false) $this->moveMouse($renderer, (float)$x, (float)$y);
        if ($down === null) return;
        $changed = false;
        for ($button = 1; $button <= 5; $button++) {
            $isDown = ($mask & (1 << ($button - 1))) !== 0;
            if ($down && $isDown && !$mouse->isHeld($button)) {
                $mouse->down($button); $changed = true;
            } elseif (!$down && !$isDown && $mouse->isHeld($button)) {
                $mouse->up($button); $changed = true;
            }
        }
        if (!$changed) $down ? $mouse->down(1) : $mouse->up(1);
    }

    private function moveMouse(?Renderer $renderer, float $x, float $y): void
    {
        if ($renderer !== null) [$x, $y] = $renderer->windowToView($x, $y);
        $this->getMouse()->move($x, $y);
    }
}
