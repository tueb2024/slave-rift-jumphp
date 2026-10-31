<?php

namespace App\Game;

/**
 * Deterministic musical transport advanced by the same fixed step as gameplay.
 * It owns phase only; music and weapons may consume the emitted sixteenth notes.
 */
final class RhythmClock
{
    public const BPM_BASE = 116.0;
    public const BPM_PER_LEVEL = 2.0;
    public const STEPS_PER_BEAT = 4;

    private float $clock = 0.0;
    private int $step = 0;
    /** @var list<int> */
    private array $pending = [];

    public function bpm(int $level): float
    {
        return self::BPM_BASE + max(0, $level - 1) * self::BPM_PER_LEVEL;
    }

    /** @return list<int> sixteenth-note positions in a 16-step bar */
    public function advance(float $dt, int $level): array
    {
        if ($dt <= 0.0) return [];
        $stepSeconds = 60.0 / $this->bpm($level) / self::STEPS_PER_BEAT;
        $this->clock += min($dt, 0.05);
        $emitted = [];
        $guard = 0;
        while ($this->clock + 1e-12 >= $stepSeconds && $guard++ < 8) {
            $this->clock -= $stepSeconds;
            $emitted[] = $this->step;
            $this->pending[] = $this->step;
            $this->step = ($this->step + 1) % 16;
        }
        return $emitted;
    }

    /** @return list<int> */
    public function drain(): array
    {
        $steps = $this->pending;
        $this->pending = [];
        return $steps;
    }

    public function reset(): void
    {
        $this->clock = 0.0;
        $this->step = 0;
        $this->pending = [];
    }
}
