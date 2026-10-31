<?php

namespace App\Game;

/** Small local RNG for deterministic procedural generation on native PHP and WASM. */
final class LevelRandom
{
    private int $state;

    public function __construct(int $seed)
    {
        $this->state = max(1, abs($seed) % 65521);
    }

    public function nextFloat(): float
    {
        // Same bounded integer arithmetic already proven in Run::random().
        $this->state = ($this->state * 25173 + 13849) % 65521;
        return $this->state / 65521;
    }

    public function nextInt(int $maxExclusive): int
    {
        if ($maxExclusive <= 1) return 0;
        return min($maxExclusive - 1, (int)floor($this->nextFloat() * $maxExclusive));
    }

    public function chance(float $probability): bool
    {
        return $this->nextFloat() < max(0.0, min(1.0, $probability));
    }
}
