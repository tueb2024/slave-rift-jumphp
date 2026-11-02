<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/balance-sim.php';

final class BalanceSimulationTest extends TestCase
{
    public function testMediumProfilesCanFinishRepresentativeProceduralSeeds(): void
    {
        foreach ([1, 2, 7] as $seed) {
            $result = \simulateProfile('moyen', $seed, 360, 'route');
            self::assertTrue($result['finalRoom'], 'Final room unreachable: seed ' . $seed);
            self::assertTrue($result['bossDefeated'], 'Boss unreachable/unwinnable: seed ' . $seed);
            self::assertSame('won', $result['result'], 'Treasure/victory blocked: seed ' . $seed);
        }
    }

    public function testWaveSevenHasAUsablePrimaryWithoutPowerCheats(): void
    {
        foreach ([1, 2, 3] as $seed) {
            $result = \simulateProfile('moyen', $seed, 240, 'survival');
            self::assertNotNull($result['wave7'], 'Medium input policy stalls before wave 7: seed ' . $seed);
            self::assertGreaterThanOrEqual(12, $result['wave7']['level']);
            self::assertGreaterThan(0, $result['wave7']['health']);
        }
    }
}
