<?php
namespace App\Tests;

use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__) . '/horde-stress.php';

final class HordeStressTest extends TestCase
{
    public function testMeasuredPopulationsStayStableWithoutGpuOrWeaponShortcuts(): void
    {
        foreach ([50,100,150,200] as $population) {
            $result = \measureHorde($population,60);
            self::assertSame($population,$result['enemies']);
            self::assertSame('play',$result['state']);
            self::assertTrue(is_finite($result['meanStepMs']));
            self::assertGreaterThanOrEqual(0.0,$result['meanStepMs']);
        }
    }
}
