<?php

namespace App\Game;

/**
 * Score value stored as mantissa × 10^exponent.
 *
 * Arithmetic never reconstructs the complete value, so scores can keep growing
 * far beyond PHP's floating-point range while staying deterministic.
 */
final class ScoreNumber
{
    public float $mantissa = 0.0;
    public int $exponent = 0;

    public function __construct(float $value = 0.0)
    {
        $this->setFloat($value);
    }

    public static function fromParts(float $mantissa, int $exponent): self
    {
        if (!is_finite($mantissa)) {
            throw new \InvalidArgumentException('Score mantissa must be finite.');
        }
        $score = new self();
        $score->mantissa = $mantissa;
        $score->exponent = $exponent;
        $score->normalize();
        return $score;
    }

    public function copy(): self
    {
        return self::fromParts($this->mantissa, $this->exponent);
    }

    public function setFloat(float $value): self
    {
        if (!is_finite($value)) {
            throw new \InvalidArgumentException('Score value must be finite.');
        }
        if ($value === 0.0) {
            $this->mantissa = 0.0;
            $this->exponent = 0;
            return $this;
        }
        $this->exponent = (int)floor(log10(abs($value)));
        $this->mantissa = $value / pow(10.0, $this->exponent);
        $this->normalize();
        return $this;
    }

    public function multiply(float $factor): self
    {
        if (!is_finite($factor)) {
            throw new \InvalidArgumentException('Score factor must be finite.');
        }
        if ($factor === 0.0 || $this->mantissa === 0.0) {
            $this->mantissa = 0.0;
            $this->exponent = 0;
            return $this;
        }
        $this->mantissa *= $factor;
        $this->normalize();
        return $this;
    }

    public function add(self $other): self
    {
        if ($other->mantissa === 0.0) return $this;
        if ($this->mantissa === 0.0) {
            $this->mantissa = $other->mantissa;
            $this->exponent = $other->exponent;
            return $this;
        }

        $diff = $this->exponent - $other->exponent;
        // A term more than 15 decimal orders smaller cannot materially affect
        // the displayed score at double precision, so avoid pointless pow().
        if ($diff > 15) return $this;
        if ($diff < -15) {
            $this->mantissa = $other->mantissa;
            $this->exponent = $other->exponent;
            return $this;
        }

        if ($diff >= 0) {
            $this->mantissa += $other->mantissa / pow(10.0, $diff);
        } else {
            $this->mantissa = $this->mantissa / pow(10.0, -$diff) + $other->mantissa;
            $this->exponent = $other->exponent;
        }
        $this->normalize();
        return $this;
    }

    public function format(int $decimals = 2): string
    {
        if ($this->mantissa === 0.0) return '0';
        $decimals = max(0, min(4, $decimals));
        $suffixes = ['', 'K', 'M', 'B', 'T', 'Qa', 'Qi', 'Sx', 'Sp', 'Oc', 'No', 'Dc'];
        if ($this->exponent >= 0) {
            $group = intdiv($this->exponent, 3);
            if ($group < count($suffixes)) {
                $withinGroup = $this->exponent - $group * 3;
                $value = $this->mantissa * pow(10.0, $withinGroup);
                return number_format($value, $decimals, '.', '') . $suffixes[$group];
            }
        }
        return sprintf('%.' . $decimals . 'fe%d', $this->mantissa, $this->exponent);
    }

    public function __toString(): string
    {
        return $this->format();
    }

    private function normalize(): void
    {
        if ($this->mantissa === 0.0) {
            $this->exponent = 0;
            return;
        }
        if (!is_finite($this->mantissa)) {
            throw new \OverflowException('Score mantissa overflowed before normalization.');
        }

        $shift = (int)floor(log10(abs($this->mantissa)));
        if ($shift !== 0) {
            $this->mantissa /= pow(10.0, $shift);
            $this->exponent += $shift;
        }
        // Protect against rounding leaving exactly 10 or below 1.
        while (abs($this->mantissa) >= 10.0) {
            $this->mantissa /= 10.0;
            $this->exponent++;
        }
        while (abs($this->mantissa) < 1.0) {
            $this->mantissa *= 10.0;
            $this->exponent--;
        }
    }
}
