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

    /** Stage is an internal campaign index, never a choice of independent difficulty. */
    public static function daily(string $date, int $tier = 1, ?int $serverSeed = null, ?string $dailyId = null): self
    {
        if($tier<1||$tier>4)throw new \InvalidArgumentException('Daily stage must be 1..4');
        $day=\DateTimeImmutable::createFromFormat('!Y-m-d',$date,new \DateTimeZone('UTC'));
        if($day===false||$day->format('Y-m-d')!==$date)throw new \InvalidArgumentException('Daily date must be YYYY-MM-DD');
        $seed=$serverSeed ?? self::seedForId($dailyId??$date,$tier);
        self::validateSeed($seed);
        return self::stageRules(self::DAILY,$seed,$tier,$dailyId??$date);
    }

    /** Domain separated, bounded integer hash: same result on 32/64-bit PHP. */
    public static function seedForId(string $id,int $stage): int
    {
        $hash=17;
        foreach(str_split('slave-rift/daily/v2/map/'.$stage.'/'.$id)as$c)$hash=($hash*131+ord($c))%65520;
        return $hash+1;
    }

    public static function campaignStage(int $seed, int $stage): self
    {
        self::validateSeed($seed);
        if($stage<1||$stage>4)throw new \InvalidArgumentException('Campaign stage must be 1..4');
        return self::stageRules(self::NORMAL,$seed,$stage,null);
    }

    private static function stageRules(string $kind,int $seed,int $stage,?string $id): self
    {
        return new self($kind,$seed,$stage,$id,depth:[6,10,15,22][$stage-1],intensity:[1,1.1,1.2,1.3][$stage-1],
            finalWaveSize:$kind===self::DAILY?[24,36,48,64][$stage-1]:[12,15,18,22][$stage-1],bossHealth:[1000,1200,1450,1750][$stage-1],campaignStage:$stage);
    }

    public function compositionWave(int $wave): int
    {
        return $wave+max(0,$this->campaignStage-1)*2;
    }

    public function damageScale(int $wave): float
    {
        return DifficultyDirector::damageScaleFor(1,$wave,1)*(1+max(0,$this->campaignStage-1)*0.18);
    }

    public function bossPressure(): float
    {
        return 1+max(0,$this->campaignStage-1)*0.18;
    }

    public static function today(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');
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
        if($this->kind===self::DAILY)return [24,40,64,88][$this->campaignStage-1];
        return $this->kind === self::HORDE ? min(200, 20 + max(1, $wave) * 4) : Run::MAX_ENEMIES;
    }

    public function waveBudget(int $wave): int
    {
        if($this->kind===self::DAILY)return [24,32,40,48][$this->campaignStage-1]+max(0,$wave-1)*6;
        return $this->kind === self::HORDE ? 14 + max(1, $wave) * 6
            : (int)ceil(min(34, 10 + $wave * 2) * $this->intensity);
    }

    public function spawnBatch(int $wave): int
    {
        if($this->kind===self::DAILY)return [3,4,5,6][$this->campaignStage-1];
        return $this->kind === self::HORDE ? min(6, 1 + intdiv($wave, 3)) : min(2, 1 + intdiv($wave, 4));
    }

    public function spawnInterval(int $wave): float
    {
        if($this->kind===self::DAILY)return [.65,.55,.45,.38][$this->campaignStage-1];
        return $this->kind === self::HORDE ? max(0.12, 1.2 - $wave * 0.035) : max(0.85, 1.4 - $wave * 0.04) / (1+max(0,$this->campaignStage-1)*0.08);
    }

    public function enemyHealthScale(int $wave): float
    {
        if($this->kind===self::DAILY)return [1.0,1.25,1.55,1.9][$this->campaignStage-1]*(1+max(0,$wave-1)*.035);
        return ($this->kind === self::HORDE ? 1.0 + max(0, $wave - 1) * 0.035 : DifficultyDirector::healthScaleFor(1, $wave));
    }

    public function waveCount(): int{return $this->kind===self::DAILY?[3,4,5,6][$this->campaignStage-1]:PHP_INT_MAX;}
    public function xpThreshold(int $level): int{return $this->kind===self::DAILY?12+max(0,$level-1)*3:XpCurve::threshold($level);}
    /** The four-map budget is fixed: 114 + 200 + 308 + 442 = 1064 enemies. */
    public function primaryStageForKills(int $kills): int
    {
        $stage=0;foreach([16,53,128,255,447,724]as$threshold)if($kills>=$threshold)$stage++;return $stage;
    }
    public function label(): string
    {
        return match ($this->kind) {
            self::DAILY => 'DÉFI DU JOUR / MAP ' . $this->campaignStage . '/4',
            self::HORDE => 'HORDE', default => 'NORMAL',
        };
    }

    private static function validateSeed(int $seed): void
    {
        if ($seed < 1 || $seed > 65520) throw new \InvalidArgumentException('Seed must be 1..65520');
    }
}
