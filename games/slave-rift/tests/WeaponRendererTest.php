<?php

namespace App\Tests;

use App\Gfx\WeaponRenderer;
use PHPUnit\Framework\TestCase;

final class WeaponRendererTest extends TestCase
{
    public function testCurrentLogicalResolutionKeepsWeaponCompact(): void
    {
        $renderer = new WeaponRenderer();
        $colt = $renderer->layout(320, 180, 0, 0.0);
        self::assertSame(49, $colt['drawH']);
        self::assertSame(74, $colt['drawW']);
        self::assertSame(1, $colt['copies']);

        $dual = $renderer->layout(320, 180, 3, 0.0);
        self::assertSame(52, $dual['drawH']);
        self::assertSame(78, $dual['drawW']);
        self::assertSame(2, $dual['copies']);
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

    public function testAtlasFramesRemainFourHorizontal144By96Crops(): void
    {
        $renderer = new WeaponRenderer();
        self::assertSame(0, $renderer->frameForState(WeaponRenderer::READY));
        self::assertSame(1, $renderer->frameForState(WeaponRenderer::FIRE));
        self::assertSame(2, $renderer->frameForState(WeaponRenderer::RECOIL));
        self::assertSame(3, $renderer->frameForState(WeaponRenderer::COOLDOWN));
        self::assertSame(144, WeaponRenderer::FRAME_W);
        self::assertSame(96, WeaponRenderer::FRAME_H);
    }
}
