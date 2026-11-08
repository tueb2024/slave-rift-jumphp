<?php

namespace App\Game;

/** Immutable run contract. Seed policy and rule differences live here, without persistence. */
final class GameMode
{
    public const NORMAL = 'normal';
    public const DAILY = 'daily';
    public const HORDE = 'horde';

    private const DAILY_WAVES = [
        [10, 12, 14],
        [16, 18, 20, 22],
        [24, 26, 28, 28, 30],
        [32, 34, 36, 36, 38, 40],
    ];
    private const DAILY_WEAPON_KILLS = [8, 26, 63, 125, 218, 354];

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
        return self::campaignStage($seed, 1);
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
            finalWaveSize:$kind===self::DAILY?[8,12,16,20][$stage-1]:[12,15,18,22][$stage-1],bossHealth:[2200,2800,3500,4300][$stage-1],campaignStage:$stage);
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

    /** Progressive normal play shares bounded encounters, not daily weapon rules. */
    public function isProgressiveNormal(): bool
    {
        return $this->kind === self::NORMAL && $this->campaignStage > 0;
    }

    public function hasFiniteEncounters(): bool
    {
        return $this->kind === self::DAILY || $this->isProgressiveNormal();
    }

    public function enemyLimit(int $wave): int
    {
        if($this->isProgressiveNormal())return [12,16,20,24][$this->campaignStage-1]+min(2,max(0,$wave-1))*2;
        if($this->kind===self::DAILY)return [4,8,12,16][$this->campaignStage-1]+min(2,max(0,$wave-1))*2;
        return $this->kind === self::HORDE ? min(200, 20 + max(1, $wave) * 4) : Run::MAX_ENEMIES;
    }

    public function waveBudget(int $wave): int
    {
        if($this->isProgressiveNormal()){
            $waves=[[12,14,16],[18,20,22,24],[26,28,30,32,34],[36,38,40,42,44,46]][$this->campaignStage-1];
            return $waves[max(0,min(count($waves)-1,$wave-1))];
        }
        if($this->kind===self::DAILY){
            $waves=self::DAILY_WAVES[$this->campaignStage-1];
            return $waves[max(0,min(count($waves)-1,$wave-1))];
        }
        return $this->kind === self::HORDE ? 14 + max(1, $wave) * 6
            : (int)ceil(min(34, 10 + $wave * 2) * $this->intensity);
    }

    public function spawnBatch(int $wave): int
    {
        if($this->isProgressiveNormal())return $this->campaignStage<3?1:($this->campaignStage===3?2:3);
        if($this->kind===self::DAILY)return match($this->campaignStage){
            1=>1, 2=>$wave===1?1:2, 3=>2, default=>$wave<=3?2:3,
        };
        return $this->kind === self::HORDE ? min(6, 1 + intdiv($wave, 3)) : min(2, 1 + intdiv($wave, 4));
    }

    public function spawnInterval(int $wave, int $kills = 0): float
    {
        if($this->isProgressiveNormal())return max(.55,[1.05,.90,.75,.65][$this->campaignStage-1]-max(0,$wave-1)*.04);
        if($this->kind===self::DAILY){
            // The opening teaches movement; the first weapon upgrade raises the pace.
            if($this->campaignStage===1)return $kills<self::DAILY_WEAPON_KILLS[0]?1.15:max(.65,.85-max(0,$wave-1)*.08);
            return max([0,.75,.65,.55][$this->campaignStage-1],
                [0,.95,.8,.7][$this->campaignStage-1]-max(0,$wave-1)*.05);
        }
        return $this->kind === self::HORDE ? max(0.12, 1.2 - $wave * 0.035) : max(0.85, 1.4 - $wave * 0.04) / (1+max(0,$this->campaignStage-1)*0.08);
    }

    public function intermissionSeconds(): float
    {
        if($this->isProgressiveNormal())return [3.5,3.0,2.5,2.0][$this->campaignStage-1];
        return $this->kind===self::DAILY?[4.0,3.5,3.0,2.5][$this->campaignStage-1]:4.0;
    }

    public function enemyHealthScale(int $wave): float
    {
        if($this->isProgressiveNormal())return [1.0,1.10,1.20,1.35][$this->campaignStage-1]*(1+max(0,$wave-1)*.025);
        if($this->kind===self::DAILY)return [1.0,1.25,1.55,1.9][$this->campaignStage-1]*(1+max(0,$wave-1)*.035);
        return ($this->kind === self::HORDE ? 1.0 + max(0, $wave - 1) * 0.035 : DifficultyDirector::healthScaleFor(1, $wave));
    }

    public function waveCount(): int{return $this->hasFiniteEncounters()?[3,4,5,6][$this->campaignStage-1]:PHP_INT_MAX;}
    public function xpThreshold(int $level): int{return $this->kind===self::DAILY?12+max(0,$level-1)*3:XpCurve::threshold($level);}
    /** Fixed daily budget: 44 + 88 + 152 + 236 = 520; all seven weapons remain reachable. */
    public function primaryStageForKills(int $kills): int
    {
        $stage=0;foreach(self::DAILY_WEAPON_KILLS as$threshold)if($kills>=$threshold)$stage++;return $stage;
    }
    public function label(): string
    {
        return match ($this->kind) {
            self::DAILY => 'DÉFI DU JOUR / MAP ' . $this->campaignStage . '/4',
            self::HORDE => 'HORDE', default => $this->isProgressiveNormal() ? 'MAP ' . $this->campaignStage . '/4' : 'NORMAL',
        };
    }

    private static function validateSeed(int $seed): void
    {
        if ($seed < 1 || $seed > 65520) throw new \InvalidArgumentException('Seed must be 1..65520');
    }
}

