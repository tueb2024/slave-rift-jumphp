<?php

namespace App\Audio;

use JumPHP\Audio\SoundManager;

/** Small original 44.1 kHz sound bank: no external audio dependency. */
final class RiftSfx
{
    private const RATE = 44100;

    public static function register(SoundManager $sound): void
    {
        if ($sound->hasSfx('colt_fire')) return;
        $sound->synth('rift_kick', self::kick());
        $sound->synth('rift_snare', self::snare());
        $sound->synth('rift_clap', self::clap());
        $sound->synth('rift_hat', self::hat());
        $sound->synth('colt_fire', self::gun(0.22, 0.62, 0.52));
        $sound->synth('sten_fire', self::gun(0.12, 0.42, 0.50));
        $sound->synth('heavy_fire', self::gun(0.16, 0.72, 0.46));
        $sound->synth('pulse_sfx', self::sweepFx(0.42, 140.0, 760.0));
        $sound->synth('special_sfx', self::special());
        $sound->synth('pickup_xp', self::pickup());
        $sound->synth('level_up', self::levelUp());
    }

    private static function kick(): string
    {
        return self::wav(0.24, static function (float $t, int $i): float {
            $f = 48.0 + 92.0 * exp(-20.0 * $t);
            return self::env($t, 0.24) * (0.90 * sin(2 * M_PI * $f * $t) + ($i % 11 === 0 ? 0.05 : 0.0));
        });
    }

    private static function snare(): string
    {
        $seed = 0x13579b;
        return self::wav(0.20, static function (float $t, int $i) use (&$seed): float {
            $noise = self::noise($seed);
            return self::env($t, 0.20) * (0.62 * $noise + 0.26 * sin(2 * M_PI * 185.0 * $t));
        });
    }

    private static function clap(): string
    {
        $seed = 0x61ac3d; $last = 0.0;
        return self::wav(0.14, static function (float $t, int $i) use (&$seed, &$last): float {
            $x = self::noise($seed); $hp = $x - $last * 0.88; $last = $x;
            $burst = 0.0;
            foreach ([0.0, 0.018, 0.036] as $offset) {
                if ($t >= $offset) $burst += exp(-92.0 * ($t - $offset));
            }
            return 0.28 * $burst * $hp;
        });
    }

    private static function hat(): string
    {
        $seed = 0x2468ac; $last = 0.0;
        return self::wav(0.065, static function (float $t, int $i) use (&$seed, &$last): float {
            $x = self::noise($seed); $hp = $x - $last * 0.92; $last = $x;
            return self::env($t, 0.065, 0.001) * 0.42 * $hp;
        });
    }

    private static function gun(float $duration, float $body, float $noiseAmount): string
    {
        $seed = (int)round($duration * 100000) ^ 0x5a17;
        return self::wav($duration, static function (float $t, int $i) use ($duration, $body, $noiseAmount, &$seed): float {
            $f = 90.0 + 40.0 * exp(-18.0 * $t);
            return self::env($t, $duration, 0.0015) * ($noiseAmount * self::noise($seed) + $body * sin(2 * M_PI * $f * $t));
        });
    }

    private static function sweepFx(float $duration, float $from, float $to): string
    {
        return self::wav($duration, static function (float $t) use ($duration, $from, $to): float {
            $u = min(1.0, $t / $duration); $f = $from + ($to - $from) * $u;
            return self::env($t, $duration, 0.008) * (0.55 * sin(2 * M_PI * $f * $t) + 0.18 * sin(2 * M_PI * $f * 2.03 * $t));
        });
    }

    private static function special(): string
    {
        return self::wav(0.52, static function (float $t): float {
            $u = $t / 0.52; $f = 520.0 - 260.0 * $u;
            return self::env($t, 0.52, 0.006) * (0.52 * sin(2 * M_PI * $f * $t) + 0.34 * sin(2 * M_PI * $f * 2.0 * $t));
        });
    }

    private static function pickup(): string
    {
        return self::wav(0.16, static fn(float $t): float => self::env($t, 0.16, 0.003) *
            (0.50 * sin(2 * M_PI * 880.0 * $t) + 0.28 * sin(2 * M_PI * 1320.0 * $t)));
    }

    private static function levelUp(): string
    {
        $notes = [523.25, 659.25, 783.99];
        return self::wav(0.42, static function (float $t) use ($notes): float {
            $part = min(2, (int)floor($t / 0.14)); $local = $t - $part * 0.14;
            return 0.46 * exp(-28.0 * $local) * sin(2 * M_PI * $notes[$part] * $t);
        });
    }

    private static function env(float $t, float $duration, float $attack = 0.003): float
    {
        return min(1.0, $t / max(0.0001, $attack)) * exp(-6.0 * $t / max(0.001, $duration));
    }

    private static function noise(int &$seed): float
    {
        $seed ^= ($seed << 13) & 0x7fffffff;
        $seed ^= ($seed >> 17);
        $seed ^= ($seed << 5) & 0x7fffffff;
        $seed &= 0x7fffffff;
        return ($seed / 1073741824.0) - 1.0;
    }

    private static function wav(float $duration, callable $sample): string
    {
        $count = max(1, (int)round(self::RATE * $duration));
        $pcm = '';
        for ($i = 0; $i < $count; $i++) {
            $v = max(-1.0, min(1.0, (float)$sample($i / self::RATE, $i)));
            $pcm .= pack('v', ((int)round($v * 32767.0)) & 0xffff);
        }
        $bytes = strlen($pcm);
        return 'RIFF' . pack('V', 36 + $bytes) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, self::RATE, self::RATE * 2, 2, 16) .
            'data' . pack('V', $bytes) . $pcm;
    }
}
