<?php
namespace App\Game;

/** Explicit XP rewards, independent from difficulty or wave coefficients. */
final class XpCurve
{
    public const CURRENT = 'current';
    public const WEIGHTED = '1-2-3-6';
    public const ACCESSIBLE = '2-3-4-6';

    public function __construct(public readonly string $model = self::CURRENT)
    {
        if (!in_array($model,[self::CURRENT,self::WEIGHTED,self::ACCESSIBLE],true)) throw new \InvalidArgumentException('Unknown XP curve');
    }

    public function reward(string $type): int
    {
        $rewards = match ($this->model) {
            self::WEIGHTED => ['runner'=>1,'guardian'=>2,'enforcer'=>3,'brute'=>6],
            self::ACCESSIBLE => ['runner'=>2,'guardian'=>3,'enforcer'=>4,'brute'=>6],
            default => ['runner'=>2,'guardian'=>2,'enforcer'=>2,'brute'=>5],
        };
        return $rewards[$type] ?? $rewards['guardian'];
    }

    public static function threshold(int $level): int
    {
        return 6 + (int)floor(max(0,$level-1)*0.7);
    }

    public static function totalForLevel(int $level): int
    {
        $sum=0; for($i=1;$i<$level;$i++) $sum+=self::threshold($i); return $sum;
    }

    public static function levelForTotal(float $xp): int
    {
        $level=1;
        while($xp>=self::threshold($level)) {$xp-=self::threshold($level);$level++;}
        return $level;
    }
}
