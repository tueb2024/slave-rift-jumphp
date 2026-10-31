<?php

namespace App\Tests;

use App\Game\BossController;
use PHPUnit\Framework\TestCase;

final class BossControllerTest extends TestCase
{
    public function testWallBossRequiresOrbsBeforeTakingDamage(): void
    {
        $boss = new BossController(BossController::WALL, 1000, 1.0);
        $boss->armOrbs([
            ['x' => -2.0, 'y' => 2.0, 'z' => 0.0],
            ['x' => 2.0, 'y' => 2.0, 'z' => 0.0],
        ], 40);
        $boss->activate();

        self::assertSame(0, $boss->damage(200));
        self::assertSame(40, $boss->damageOrb(0, 99));
        self::assertFalse($boss->vulnerable);
        self::assertSame(40, $boss->damageOrb(1, 99));
        self::assertTrue($boss->vulnerable);
        self::assertSame(200, $boss->damage(200));
        self::assertSame(800, $boss->health);
    }

    public function testExpiredWindowRearmsWallBossOrbs(): void
    {
        $boss = new BossController(BossController::WALL, 500, 0.5);
        $boss->armOrbs([['x' => 0.0, 'y' => 1.0, 'z' => 0.0]], 25);
        $boss->activate();
        $boss->damageOrb(0, 25);
        self::assertTrue($boss->vulnerable);

        $boss->update(0.25);
        $boss->update(0.25);
        self::assertFalse($boss->vulnerable);
        self::assertTrue($boss->orbs[0]['alive']);
        self::assertSame(25, $boss->orbs[0]['hp']);
    }

    public function testBossPhaseTracksRemainingHealth(): void
    {
        $boss = new BossController(BossController::DYNAMIC, 1000);
        $boss->activate();
        $boss->damage(500);
        $boss->update(0.01);
        self::assertSame(2, $boss->phase);
        $boss->damage(300);
        $boss->update(0.01);
        self::assertSame(3, $boss->phase);
    }

    public function testDynamicUltimateHasCooldown(): void
    {
        $boss = new BossController(BossController::DYNAMIC, 1000);
        $boss->activate();
        self::assertTrue($boss->consumeUltimate(2.0));
        self::assertFalse($boss->consumeUltimate(2.0));
        for ($i = 0; $i < 8; $i++) $boss->update(0.25);
        self::assertTrue($boss->canUseUltimate());
    }
}
