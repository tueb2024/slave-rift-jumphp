<?php

namespace App\Audio;

use JumPHP\Audio\SoundManager;
use JumPHP\Audio\Synth;

/** Music renderer for RhythmClock events; owns no timing of its own. */
final class RiftMusic
{
    private SoundManager $sound;
    private bool $registered = false;

    /** @var float[] */
    private const LEAD_A = [220.00, 261.63, 293.66, 329.63, 392.00, 440.00];
    /** @var float[] */
    private const LEAD_B = [440.00, 493.88, 523.25, 587.33, 659.25, 783.99];

    public function __construct(SoundManager $sound)
    {
        $this->sound = $sound;
    }

    public function register(): void
    {
        if ($this->registered) return;
        RiftSfx::register($this->sound);
        if (!$this->sound->hasSfx('rift_lead_a_0')) {
            foreach (self::LEAD_A as $i => $freq) {
                $this->sound->synth('rift_lead_a_' . $i, Synth::tone($freq, 0.085, 'triangle', 0.19));
            }
            foreach (self::LEAD_B as $i => $freq) {
                $this->sound->synth('rift_lead_b_' . $i, Synth::tone($freq, 0.060, 'square', 0.11));
            }
        }
        $this->registered = true;
    }

    /** @param list<int> $steps */
    public function playSteps(array $steps, int $level): void
    {
        if (!$this->registered) return;
        foreach ($steps as $step) $this->triggerStep($step % 16, max(1, $level));
    }

    private function triggerStep(int $step, int $level): void
    {
        // Level 1: only quarter-note kicks. Weapon hi-hats are added by the
        // gameplay rhythm pass so they cannot drift from actual shots.
        if (in_array($step, [0, 4, 8, 12], true)) {
            $this->sound->playSfx('rift_kick', 0, false);
        }

        // Level 4: backbeat.
        if ($level >= 4 && ($step === 4 || $step === 12)) {
            $this->sound->playSfx('rift_snare', 0, false);
        }

        // Level 8: first sparse melody, kept deliberately simple so shots remain rhythmic foreground.
        if ($level >= 8) {
            $melodyA = [0 => 0, 2 => 2, 4 => 3, 6 => 4, 8 => 2, 10 => 5, 12 => 4, 14 => 1];
            if (isset($melodyA[$step])) $this->sound->playSfx('rift_lead_a_' . $melodyA[$step], 0, false);
        }

        // Level 12: clap reinforces the snare without changing the transport.
        if ($level >= 12 && ($step === 4 || $step === 12)) {
            $this->sound->playSfx('rift_clap', 0, false);
        }

        // Level 16: a light counter-line on the offbeats.
        if ($level >= 16) {
            $melodyB = [1 => 0, 5 => 2, 9 => 3, 13 => 5];
            if (isset($melodyB[$step])) $this->sound->playSfx('rift_lead_b_' . $melodyB[$step], 0, false);
        }
    }
}
