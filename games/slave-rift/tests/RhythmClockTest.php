<?php

namespace App\Tests;

use App\Game\RhythmClock;
use PHPUnit\Framework\TestCase;

final class RhythmClockTest extends TestCase
{
    public function testTempoFollowsXpLevelWithoutWaveState(): void
    {
        $clock = new RhythmClock();
        self::assertSame(116.0, $clock->bpm(1));
        self::assertSame(122.0, $clock->bpm(4));
        self::assertSame(130.0, $clock->bpm(8));
        self::assertSame(146.0, $clock->bpm(16));
    }

    public function testSixteenthPhaseSurvivesLevelChange(): void
    {
        $clock = new RhythmClock();
        $firstBeat = $clock->advance(60.0 / 116.0 + 1e-6, 1);
        self::assertSame([0, 1, 2, 3], $firstBeat);
        self::assertSame($firstBeat, $clock->drain());
        self::assertSame([], $clock->drain());

        $next = $clock->advance(60.0 / 122.0 / 4.0 + 1e-6, 4);
        self::assertSame([4], $next, 'Changing level must change tempo, not restart the bar.');
    }
}
