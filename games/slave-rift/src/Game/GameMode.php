<?php

namespace App\Game;

/** Immutable run contract. Seed policy and rule differences live here, without persistence. */
final class GameMode
{
    public const NORMAL = 'normal';
    public const DAILY = 'daily';
    public const HORDE = 'horde';

    private function __construct(
        public readonly string $kind,
        public readonly int $seed,
        public readonly int $difficultyTier = 2,
        public readonly ?string $dailyId = null,
        public readonly int $depth = 7,
        public readonly float $intensity = 1.0,
        public readonly int $finalWaveSize = 12,
        public readonly int $bossHealth = 1200,
        public readonly int $campaignStage = 0,
    ) {}

    public static function normal(?int $previousSeed = null): self
    {
        $seed = random_int(1, 65519);
        if ($previousSeed !== null && $seed >= $previousSeed) $seed++;
        return new self(self::NORMAL, $seed);
    }

    /** Explicit fixture/replay entry, also usable by a future server-supplied challenge. */
    public static function fixed(int $seed, int $depth = 7): self
    {
        self::validateSeed($seed);
        return new self(self::NORMAL, $seed, depth: max(5, min(24, $depth)));
    }

    public static function daily(string $date, int $tier, ?int $serverSeed = null, ?string $dailyId = null): self
    {
        if ($tier < 1 || $tier > 4) throw new \InvalidArgumentException('Daily tier must be 1..4');
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('Europe/Paris'));
        if ($day === false || $day->format('Y-m-d') !== $date) throw new \InvalidArgumentException('Daily date must be YYYY-MM-DD');
        // Small integer arithmetic works identically in PHP-WASM (32 bit) and desktop.
        $ordinal = (int)$day->format('Y') * 366 + (int)$day->format('z');
        $seed = $serverSeed ?? (($ordinal * 31) % 65517 + $tier);
        self::validateSeed($seed);
        return new self(self::DAILY, $seed, $tier, $dailyId ?? $date . '/' . $tier,
            [5, 7, 9, 11][$tier - 1], [0.85, 1.0, 1.15, 1.30][$tier - 1],
            [10, 12, 16, 20][$tier - 1], [900, 1200, 1500, 1800][$tier - 1]);
    }

    public static function campaignStage(int $seed, int $stage): self
    {
        self::validateSeed($seed);
        if($stage<1 || $stage>4) throw new \InvalidArgumentException('Campaign stage must be 1..4');
        return new self(self::NORMAL,$seed,depth:[6,9,12,16][$stage-1],intensity:[1,1.12,1.25,1.4][$stage-1],
            finalWaveSize:[12,15,18,22][$stage-1],bossHealth:[1200,1800,2700,4000][$stage-1],campaignStage:$stage);
    }

    public function compositionWave(int $wave): int
    {
        return $wave+max(0,$this->campaignStage-1)*2;
    }

    public function damageScale(int $wave): float
    {
        return DifficultyDirector::damageScaleFor(1,$wave,1)*(1+max(0,$this->campaignStage-1)*0.1);
    }

    public function bossPressure(): float
    {
        return 1+max(0,$this->campaignStage-1)*0.12;
    }

    public static function today(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')))->format('Y-m-d');
    }

    public static function dateLabel(string $date): string
    {
        $parts = explode('-', $date);
        $months = ['JANVIER','FÉVRIER','MARS','AVRIL','MAI','JUIN','JUILLET','AOÛT','SEPTEMBRE','OCTOBRE','NOVEMBRE','DÉCEMBRE'];
        return (int)$parts[2] . ' ' . $months[(int)$parts[1]-1] . ' ' . $parts[0];
    }

    public static function horde(?int $seed = null): self
    {
        $seed ??= random_int(1, 65520);
        self::validateSeed($seed);
        return new self(self::HORDE, $seed);
    }

    public function enemyLimit(int $wave): int
    {
        return $this->kind === self::HORDE ? min(200, 20 + max(1, $wave) * 4) : Run::MAX_ENEMIES;
    }

    public function waveBudget(int $wave): int
    {
        return $this->kind === self::HORDE ? 14 + max(1, $wave) * 6
            : (int)ceil(min(34, 10 + $wave * 2) * $this->intensity);
    }

    public function spawnBatch(int $wave): int
    {
        return $this->kind === self::HORDE ? min(6, 1 + intdiv($wave, 3)) : min(2, 1 + intdiv($wave, 4));
    }

    public function spawnInterval(int $wave): float
    {
        return $this->kind === self::HORDE ? max(0.12, 1.2 - $wave * 0.035) : max(0.85, 1.4 - $wave * 0.04) / (1+max(0,$this->campaignStage-1)*0.08);
    }

    public function enemyHealthScale(int $wave): float
    {
        return $this->intensity * ($this->kind === self::HORDE ? 1.0 + max(0, $wave - 1) * 0.035 : DifficultyDirector::healthScaleFor(1, $wave));
    }

    public function label(): string
    {
        return match ($this->kind) {
            self::DAILY => 'DÉFI DU JOUR / NIVEAU ' . $this->difficultyTier,
            self::HORDE => 'HORDE', default => 'NORMAL',
        };
    }

    private static function validateSeed(int $seed): void
    {
        if ($seed < 1 || $seed > 65520) throw new \InvalidArgumentException('Seed must be 1..65520');
    }
}
