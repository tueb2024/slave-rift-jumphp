<?php

namespace App\Game;

/**
 * Multiplicative end-of-level score model.
 *
 * Route and movement terms measure efficiency against a reference path; raw
 * distance never grants points by itself. Exponential growth is represented as
 * mantissa/exponent from the start, so long campaigns do not overflow floats.
 */
final class ScoreSystem
{
    /**
     * @param array{
     *   level?:int,wavesCleared?:int,kills?:int,damageTaken?:float,maxHealth?:float,
     *   actualDistance?:float,optimalDistance?:float,movementDecisions?:int,optimalMovementDecisions?:int,
     *   averageKillSeconds?:float,interWaveIdleSeconds?:float,upgradeReactionSeconds?:float,
     *   enemiesSpawned?:int,enemiesKilled?:int,xpDropped?:int,xpCollected?:int,
     *   pickupsDropped?:int,pickupsCollected?:int
     * } $metrics
     */
    public function calculate(array $metrics): ScoreNumber
    {
        $kills = max(0, (int)($metrics['kills'] ?? 0));
        $level = max(1, (int)($metrics['level'] ?? 1));
        $waves = max(0, (int)($metrics['wavesCleared'] ?? 0));
        $base = new ScoreNumber(max(1000.0, 1000.0 + $kills * 275.0));

        $base->multiply($this->killSpeedCoefficient((float)($metrics['averageKillSeconds'] ?? 4.0)));
        $base->multiply($this->survivalCoefficient(
            (float)($metrics['damageTaken'] ?? 0.0),
            max(1.0, (float)($metrics['maxHealth'] ?? 100.0)),
        ));
        $base->multiply($this->routeCoefficient(
            (float)($metrics['actualDistance'] ?? 0.0),
            (float)($metrics['optimalDistance'] ?? 0.0),
        ));
        $base->multiply($this->movementCoefficient(
            (int)($metrics['movementDecisions'] ?? 0),
            (int)($metrics['optimalMovementDecisions'] ?? 0),
        ));
        $base->multiply($this->cleanupCoefficient(
            (int)($metrics['enemiesKilled'] ?? $kills),
            (int)($metrics['enemiesSpawned'] ?? max(1, $kills)),
            (int)($metrics['pickupsCollected'] ?? 0),
            (int)($metrics['pickupsDropped'] ?? 0),
        ));
        $base->multiply($this->xpCoefficient(
            (int)($metrics['xpCollected'] ?? 0),
            (int)($metrics['xpDropped'] ?? 0),
        ));
        $base->multiply($this->reactionCoefficient((float)($metrics['upgradeReactionSeconds'] ?? 3.0)));
        $base->multiply($this->paceCoefficient((float)($metrics['interWaveIdleSeconds'] ?? 0.0)));
        $base->multiplyScore($this->campaignGrowth($level, $waves));
        return $base;
    }

    public function routeCoefficient(float $actual, float $optimal): float
    {
        if ($actual <= 0.0 || $optimal <= 0.0) return 1.0;
        $efficiency = min(1.0, $optimal / max($optimal, $actual));
        return 0.65 + 0.85 * pow($efficiency, 0.8);
    }

    public function movementCoefficient(int $actual, int $optimal): float
    {
        if ($actual <= 0 || $optimal <= 0) return 1.0;
        $efficiency = min(1.0, $optimal / max($optimal, $actual));
        return 0.78 + 0.47 * pow($efficiency, 0.75);
    }

    public function cleanupCoefficient(int $killed, int $spawned, int $pickupsCollected, int $pickupsDropped): float
    {
        $enemyRatio = $spawned <= 0 ? 1.0 : max(0.0, min(1.0, $killed / $spawned));
        $pickupRatio = $pickupsDropped <= 0 ? 1.0 : max(0.0, min(1.0, $pickupsCollected / $pickupsDropped));
        $quality = $enemyRatio * 0.75 + $pickupRatio * 0.25;
        return 0.65 + 0.85 * $quality;
    }

    public function xpCoefficient(int $collected, int $dropped): float
    {
        if ($dropped <= 0) return 1.0;
        $ratio = max(0.0, min(1.0, $collected / $dropped));
        return 0.80 + 0.55 * $ratio;
    }

    public function campaignGrowth(int $level, int $wavesCleared): ScoreNumber
    {
        // Equivalent to 2.35^(level-1) * 1.22^waves without ever materialising it.
        $log10 = max(0, $level - 1) * log10(2.35) + max(0, $wavesCleared) * log10(1.22);
        $exponent = (int)floor($log10);
        $mantissa = pow(10.0, $log10 - $exponent);
        return ScoreNumber::fromParts($mantissa, $exponent);
    }

    private function killSpeedCoefficient(float $seconds): float
    {
        $seconds = max(0.0, $seconds);
        return max(0.72, min(1.75, 1.70 - $seconds * 0.07));
    }

    private function survivalCoefficient(float $damageTaken, float $maxHealth): float
    {
        $ratio = max(0.0, $damageTaken) / max(1.0, $maxHealth);
        return max(0.55, min(1.55, 1.55 - $ratio * 0.72));
    }

    private function reactionCoefficient(float $seconds): float
    {
        $seconds = max(0.0, $seconds);
        return 0.78 + 0.72 / (1.0 + $seconds / 2.5);
    }

    private function paceCoefficient(float $idleSeconds): float
    {
        $idleSeconds = max(0.0, $idleSeconds);
        return max(0.70, 1.28 / (1.0 + $idleSeconds / 45.0));
    }
}
