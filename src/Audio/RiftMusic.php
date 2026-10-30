<?php

namespace App\Audio;

use JumPHP\Audio\SoundManager;
use JumPHP\Audio\Synth;

/**
 * Lightweight reactive music layer for Slave Rift.
 *
 * It pre-generates a tiny drum/lead bank with JumPHP's PHP synth, then
 * schedules it on a 16th-note grid. No external music file is required.
 * A real guitar stab can later replace rift_wave_sting without changing the
 * sequencing API.
 */
final class RiftMusic
{
    private const BPM_BASE = 96.0;
    private const BPM_STEP = 4.0;
    private const BPM_MAX = 144.0;

    private SoundManager $sound;
    private bool $registered = false;
    private float $clock = 0.0;
    private int $step = 0;
    private int $wave = 1;

    /** @var float[] */
    private const LEAD = [
        220.00, // A3
        261.63, // C4
        293.66, // D4
        329.63, // E4
        392.00, // G4
        440.00, // A4
    ];

    public function __construct(SoundManager $sound)
    {
        $this->sound = $sound;
    }

    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        if (!$this->sound->hasSfx('rift_kick')) {
            $this->sound->synth('rift_kick', Synth::sweep(120, 46, 0.18, 'sine', 0.85));
            $this->sound->synth('rift_snare', Synth::noise(0.11, 0.42));
            $this->sound->synth('rift_hat', Synth::noise(0.035, 0.18));
            $this->sound->synth('rift_wave_sting', Synth::sequence([
                [220, 0.055], [330, 0.055], [440, 0.11],
            ], 'saw', 0.45));

            foreach (self::LEAD as $i => $freq) {
                $this->sound->synth('rift_lead_' . $i, Synth::tone($freq, 0.09, 'saw', 0.20));
            }
        }

        $this->registered = true;
    }

    public function reset(int $wave = 1): void
    {
        $this->wave = max(1, $wave);
        $this->clock = 0.0;
        $this->step = 0;
    }

    public function onWaveStart(int $wave): void
    {
        $this->wave = max(1, $wave);
        $this->clock = 0.0;
        $this->step = 0;
        $this->sound->playSfx('rift_wave_sting', 0, false);
    }

    /**
     * Advance the music clock.
     *
     * Intensity is intentionally generic: later we can feed enemy pressure,
     * weapon tier or remaining hostiles into it without changing the rhythm
     * engine. 0 = sparse, 1 = full pattern.
     */
    public function update(float $dt, float $intensity = 1.0): void
    {
        if (!$this->registered || $dt <= 0.0) {
            return;
        }

        $intensity = max(0.0, min(1.0, $intensity));
        $stepSeconds = 60.0 / $this->bpm() / 4.0; // sixteenth note
        $this->clock += $dt;

        // Keep catch-up bounded after a long frame/pause.
        $guard = 0;
        while ($this->clock >= $stepSeconds && $guard++ < 8) {
            $this->clock -= $stepSeconds;
            $this->triggerStep($this->step % 16, $intensity);
            $this->step = ($this->step + 1) % 16;
        }
    }

    public function bpm(): float
    {
        return min(self::BPM_MAX, self::BPM_BASE + ($this->wave - 1) * self::BPM_STEP);
    }

    private function triggerStep(int $step, float $intensity): void
    {
        // 808-like backbone: four-on-the-floor becomes denser with pressure.
        if ($step === 0 || $step === 8 || ($intensity > 0.72 && ($step === 6 || $step === 14))) {
            $this->sound->playSfx('rift_kick', 0, false);
        }

        if ($step === 4 || $step === 12) {
            $this->sound->playSfx('rift_snare', 0, false);
        }

        if (($step % 2) === 0 || ($intensity > 0.82 && ($step % 2) === 1)) {
            $this->sound->playSfx('rift_hat', 0, false);
        }

        // Lead enters progressively; deterministic pattern keeps every wave tight.
        if ($this->wave >= 2 && in_array($step, [2, 7, 10, 15], true)) {
            $degree = ($this->wave + intdiv($step, 2)) % count(self::LEAD);
            $this->sound->playSfx('rift_lead_' . $degree, 0, false);
        }
    }
}
