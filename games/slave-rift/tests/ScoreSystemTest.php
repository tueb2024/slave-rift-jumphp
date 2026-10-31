<?php

namespace App\Tests;

use App\Game\ScoreSystem;
use PHPUnit\Framework\TestCase;

final class ScoreSystemTest extends TestCase
{
    public function testDetoursReduceRouteCoefficientInsteadOfRewardingDistance(): void
    {
        $system = new ScoreSystem();
        $optimal = $system->routeCoefficient(100.0, 100.0);
        $detour = $system->routeCoefficient(160.0, 100.0);
        $hugeDetour = $system->routeCoefficient(300.0, 100.0);

        self::assertGreaterThan($detour, $optimal);
        self::assertGreaterThan($hugeDetour, $detour);
    }

    public function testCleaningEnemiesAndPickupsImprovesScore(): void
    {
        $system = new ScoreSystem();
        $partial = $system->cleanupCoefficient(8, 10, 3, 6);
        $clean = $system->cleanupCoefficient(10, 10, 6, 6);
        self::assertGreaterThan($partial, $clean);
    }

    public function testCampaignGrowthStaysFiniteFarBeyondFloatScoreRange(): void
    {
        $growth = (new ScoreSystem())->campaignGrowth(1200, 5000);
        self::assertTrue(is_finite($growth->mantissa));
        self::assertGreaterThan(300, $growth->exponent);
    }

    public function testEfficientRunBeatsSloppyRunWithSameKillsAndLevel(): void
    {
        $system = new ScoreSystem();
        $common = [
            'level' => 12, 'wavesCleared' => 20, 'kills' => 150,
            'enemiesSpawned' => 150, 'enemiesKilled' => 150,
            'xpDropped' => 100, 'pickupsDropped' => 40,
            'optimalDistance' => 500.0, 'optimalMovementDecisions' => 80,
            'maxHealth' => 120.0,
        ];
        $efficient = $system->calculate($common + [
            'actualDistance' => 510.0, 'movementDecisions' => 86,
            'damageTaken' => 30.0, 'averageKillSeconds' => 1.8,
            'interWaveIdleSeconds' => 8.0, 'upgradeReactionSeconds' => 1.2,
            'xpCollected' => 98, 'pickupsCollected' => 38,
        ]);
        $sloppy = $system->calculate($common + [
            'actualDistance' => 820.0, 'movementDecisions' => 170,
            'damageTaken' => 110.0, 'averageKillSeconds' => 5.5,
            'interWaveIdleSeconds' => 70.0, 'upgradeReactionSeconds' => 9.0,
            'xpCollected' => 62, 'pickupsCollected' => 18,
        ]);

        $efficientLog = log10($efficient->mantissa) + $efficient->exponent;
        $sloppyLog = log10($sloppy->mantissa) + $sloppy->exponent;
        self::assertGreaterThan($sloppyLog, $efficientLog);
    }
}
