<?php

namespace App\Tests;

use App\Game\ScoreNumber;
use PHPUnit\Framework\TestCase;

final class ScoreNumberTest extends TestCase
{
    public function testFormatsOrdinaryIdleStyleScores(): void
    {
        self::assertSame('1.23K', (new ScoreNumber(1234.0))->format());
        self::assertSame('987.00M', (new ScoreNumber(987000000.0))->format());
    }

    public function testMultipliesFarBeyondNativeFloatRange(): void
    {
        $score = ScoreNumber::fromParts(9.9, 400)->multiply(12.0);
        self::assertEqualsWithDelta(1.188, $score->mantissa, 0.000001);
        self::assertSame(402, $score->exponent);
        self::assertSame('1.19e402', $score->format());
    }

    public function testAddsAlignedHugeValuesWithoutReconstructingThem(): void
    {
        $score = ScoreNumber::fromParts(5.0, 250);
        $score->add(ScoreNumber::fromParts(2.5, 249));
        self::assertEqualsWithDelta(5.25, $score->mantissa, 0.000001);
        self::assertSame(250, $score->exponent);
    }

    public function testNegligibleTermsDoNotDestabiliseHugeScore(): void
    {
        $score = ScoreNumber::fromParts(7.0, 500);
        $score->add(ScoreNumber::fromParts(9.0, 480));
        self::assertSame(7.0, $score->mantissa);
        self::assertSame(500, $score->exponent);
    }
}
