<?php

namespace App\Tests;

use App\Gfx\Meshes;
use PHPUnit\Framework\TestCase;

final class PosterFactoryTest extends TestCase
{
    public function testPosterAdDecalsUseDifferentAtlasTiles(): void
    {
        $decals = [
            ['material' => 'posterAd', 'position' => [1.0, 1.5, 2.0], 'normal' => [0.0, 0.0, -1.0], 'width' => 1.5, 'height' => 2.0],
            ['material' => 'posterAd', 'position' => [4.0, 1.5, 7.0], 'normal' => [1.0, 0.0, 0.0], 'width' => 1.5, 'height' => 2.0],
        ];
        $mesh = Meshes::decals($decals, 'posterAd');
        self::assertCount(12 * Meshes::STRIDE, $mesh);
        $firstU = $mesh[9];
        $secondU = $mesh[6 * Meshes::STRIDE + 9];
        self::assertNotSame($firstU, $secondU);
        self::assertGreaterThanOrEqual(0.0, min($firstU, $secondU));
        self::assertLessThanOrEqual(1.0, max($firstU, $secondU));
    }

    public function testLegacyTrollPlacementsAreAbsorbedIntoProceduralPosterAtlas(): void
    {
        $legacy = [[
            'material' => 'poster3615',
            'position' => [2.0, 1.5, 3.0],
            'normal' => [0.0, 0.0, -1.0],
            'width' => 1.5,
            'height' => 2.0,
        ]];

        self::assertSame([], Meshes::decals($legacy, 'poster3615'));
        self::assertCount(6 * Meshes::STRIDE, Meshes::decals($legacy, 'posterAd'));
    }
}
