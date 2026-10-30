<?php

namespace App\Audio;

use JumPHP\Audio\SoundManager;
use JumPHP\Audio\Synth;

/** Reactive soundtrack: sample-based drums, lightweight PHP lead sequencer. */
final class RiftMusic
{
    private const BPM_BASE = 116.0;
    private const BPM_STEP = 4.5;
    private const BPM_MAX = 154.0;

    private SoundManager $sound;
    private bool $registered = false;
    private float $clock = 0.0;
    private int $step = 0;
    private int $wave = 1;

    /** @var float[] */
    private const LEAD = [220.00, 261.63, 293.66, 329.63, 392.00, 440.00];

    public function __construct(SoundManager $sound)
    {
        $this->sound = $sound;
    }

    public function register(): void
    {
        if ($this->registered) return;
        RiftSfx::register($this->sound);
        if (!$this->sound->hasSfx('rift_wave_sting')) {
            // Temporary wave sting: cleaner triangle voicing until a licensed guitar stab is chosen.
            $this->sound->synth('rift_wave_sting', Synth::sequence([
                [220, 0.045], [329.63, 0.045], [440, 0.085], [659.25, 0.12],
            ], 'triangle', 0.48));
            foreach (self::LEAD as $i => $freq) {
                $this->sound->synth('rift_lead_' . $i, Synth::tone($freq, 0.075, 'triangle', 0.22));
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

    public function update(float $dt, float $intensity = 1.0): void
    {
        if (!$this->registered || $dt <= 0.0) return;
        $intensity = max(0.0, min(1.0, $intensity));
        $stepSeconds = 60.0 / $this->bpm() / 4.0;
        // A slow frame must not queue a burst of old notes on the next frame.
        $this->clock = min($stepSeconds * 2.0, $this->clock + min($dt, 0.08));
        $guard = 0;
        while ($this->clock >= $stepSeconds && $guard++ < 2) {
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
        // A denser, more forward 808-inspired backbone than the previous 96 BPM pattern.
        if (in_array($step, [0, 4, 8, 12], true) || ($intensity > 0.72 && ($step === 6 || $step === 14))) {
            $this->sound->playSfx('rift_kick', 0, false);
        }
        if ($step === 4 || $step === 12) $this->sound->playSfx('rift_snare', 0, false);
        if (($step % 2) === 0 || ($intensity > 0.72 && ($step % 2) === 1)) {
            $this->sound->playSfx('rift_hat', 0, false);
        }
        if ($this->wave >= 2 && in_array($step, [2, 5, 7, 10, 13, 15], true)) {
            $degree = ($this->wave + intdiv($step, 2)) % count(self::LEAD);
            $this->sound->playSfx('rift_lead_' . $degree, 0, false);
        }
    }
}
