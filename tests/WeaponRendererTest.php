<?php

namespace App\Tests;

use App\Gfx\WeaponRenderer;
use PHPUnit\Framework\TestCase;

final class WeaponRendererTest extends TestCase
{
    public function testCurrentLogicalResolutionKeepsWeaponFullyOnScreen(): void
    {
        $renderer = new WeaponRenderer();
        $pistol = $renderer->layout(320, 180, 0, 0.0);
        self::assertSame(135, $pistol['drawH']);
        self::assertEqualsWithDelta(101.25, $pistol['drawW'], .01);
        self::assertSame(1, $pistol['copies']);
        self::assertGreaterThanOrEqual(0, $pistol['x']);
        self::assertGreaterThanOrEqual(0, $pistol['y']);
        self::assertLessThanOrEqual(320, $pistol['x'] + $pistol['drawW']);
        self::assertLessThanOrEqual(180, $pistol['y'] + $pistol['drawH']);

        foreach ([1, 3, 6] as $stage) {
            $dual = $renderer->layout(320, 180, $stage, 0.0);
            self::assertSame(2, $dual['copies']);
            self::assertGreaterThan(0, $dual['spread']);
            self::assertGreaterThanOrEqual(0, $dual['x']);
            self::assertLessThanOrEqual(320, $dual['x'] + $dual['spread'] + $dual['drawW']);
            self::assertLessThanOrEqual(180, $dual['y'] + $dual['drawH']);
        }
    }

    public function testOnlyDualStagesRequestTwoWeaponCopies(): void
    {
        $renderer = new WeaponRenderer();

        foreach ([0, 2, 4, 5] as $stage) {
            self::assertSame(1, $renderer->layout(320, 180, $stage, 0.0)['copies']);
        }
        foreach ([1, 3, 6] as $stage) {
            self::assertSame(2, $renderer->layout(320, 180, $stage, 0.0)['copies']);
        }
    }

    public function testLayoutScalesWithLogicalResolution(): void
    {
        $renderer = new WeaponRenderer();
        $small = $renderer->layout(320, 180, 4, 0.0);
        $large = $renderer->layout(640, 360, 4, 0.0);
        self::assertSame($small['drawH'] * 2, $large['drawH']);
        self::assertSame($small['drawW'] * 2, $large['drawW']);
    }

    public function testShotPulseWalksThroughVisualCooldownStates(): void
    {
        $renderer = new WeaponRenderer();
        $renderer->reset(1.0);
        self::assertSame(WeaponRenderer::FIRE, $renderer->update(1.0, 0.12));
        self::assertSame(WeaponRenderer::RECOIL, $renderer->update(1.04, 0.08));
        self::assertSame(WeaponRenderer::COOLDOWN, $renderer->update(1.09, 0.03));
        self::assertSame(WeaponRenderer::READY, $renderer->update(1.14, 0.0));
    }

    public function testAtlasContractAndFourWeaponProgression(): void
    {
        $renderer = new WeaponRenderer();
        self::assertSame(0, $renderer->frameForState(WeaponRenderer::READY));
        self::assertSame(2, $renderer->frameForState(WeaponRenderer::FIRE));
        self::assertSame(3, $renderer->frameForState(WeaponRenderer::RECOIL));
        self::assertSame(5, $renderer->frameForState(WeaponRenderer::COOLDOWN));
        self::assertSame(96, WeaponRenderer::FRAME_W);
        self::assertSame(128, WeaponRenderer::FRAME_H);
        self::assertSame(0, $renderer->rowForStage(0));
        self::assertSame('weapon_pistol', $renderer->textureKey(0));
        self::assertSame('weapon_smg', $renderer->textureKey(2));
        self::assertSame('weapon_machinegun', $renderer->textureKey(4));
        self::assertSame('weapon_dual_minigun', $renderer->textureKey(6));
    }
}
