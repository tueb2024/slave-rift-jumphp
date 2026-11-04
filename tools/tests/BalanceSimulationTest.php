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
        $reached=0;
        foreach ([1, 2, 3] as $seed) {
            $result = \simulateProfile('moyen', $seed, 240, 'survival');
            if($result['wave7']===null){self::assertSame('dead',$result['result'],'A failure must be combat death rather than a softlock');continue;}
            $reached++;
            self::assertGreaterThanOrEqual(12, $result['wave7']['level']);
            self::assertGreaterThan(0, $result['wave7']['health']);
        }
        self::assertGreaterThanOrEqual(2,$reached,'Representative policies must still be able to discover late weapons');
    }
}
