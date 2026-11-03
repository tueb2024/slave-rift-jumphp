<?php

namespace App\Game;

/** Device-independent menu choices and hit boxes; all inputs use the same actions. */
final class MenuFlow
{
    public string $screen = 'main';
    public string $mode = GameMode::NORMAL;
    public int $cursor = 0;

    public function open(string $screen): void
    {
        $this->screen = $screen; $this->cursor = 0;
    }

    public function items(): array
    {
        return match ($this->screen) {
            'main' => ['normal' => 'JOUER', 'daily' => 'DÉFI DU JOUR', 'horde' => 'HORDE', 'controls' => 'COMMANDES'],
            'daily' => ['daily1' => 'NIVEAU 1 : ACCESSIBLE', 'daily2' => 'NIVEAU 2 : NORMAL', 'daily3' => 'NIVEAU 3 : DIFFICILE', 'daily4' => 'NIVEAU 4 : BRUTAL', 'back' => 'RETOUR'],
            'controls' => ['back' => 'RETOUR'],
            'pause' => ['resume' => 'REPRENDRE', 'restart' => 'RECOMMENCER', 'menu' => 'MENU PRINCIPAL'],
            'dead', 'won' => match ($this->mode) {
                GameMode::DAILY => ['retry' => 'REJOUER LE DÉFI', 'daily' => 'AUTRE NIVEAU DU DÉFI', 'menu' => 'MENU PRINCIPAL'],
                GameMode::HORDE => ['retry' => 'RECOMMENCER', 'menu' => 'MENU PRINCIPAL'],
                default => ['replay' => 'REJOUER CETTE MAP', 'new' => 'NOUVELLE MAP ALÉATOIRE', 'menu' => 'MENU PRINCIPAL'],
            },
            default => [],
        };
    }

    public function rect(int $index, int $width = 320): array
    {
        [$start, $step, $height] = match ($this->screen) {
            'main' => [48,28,24], 'daily' => [48,22,20], 'controls' => [140,24,24],
            'pause' => [58,30,26], default => [78,28,24],
        };
        return [30, $start + $index * $step, $width - 60, $height];
    }

    public function hit(float $x, float $y, int $width = 320): ?int
    {
        foreach (array_values($this->items()) as $i => $_) {
            [$bx,$by,$bw,$bh] = $this->rect($i, $width);
            if ($x >= $bx && $x < $bx + $bw && $y >= $by && $y < $by + $bh) return $i;
        }
        return null;
    }

    public function move(int $direction): void
    {
        $count = count($this->items());
        if ($count > 0) $this->cursor = ($this->cursor + $direction + $count) % $count;
    }

    public function activate(?int $index = null): ?string
    {
        $action = array_keys($this->items())[$index ?? $this->cursor] ?? null;
        if ($action === null) return null;
        if (in_array($action, ['normal','horde'], true)) $this->mode = $action;
        if (str_starts_with($action, 'daily') && $action !== 'daily') $this->mode = GameMode::DAILY;
        $this->open(match ($action) {
            'daily' => 'daily', 'controls' => 'controls', 'back','menu' => 'main', default => 'play',
        });
        return $action;
    }
}
