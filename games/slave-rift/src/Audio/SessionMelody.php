<?php
namespace App\Audio;

/** Musical randomness is separate from gameplay. Remember contours, not merely transpositions. */
final class SessionMelody
{
    public const HISTORY = 32;
    public static function signature(int $seed): string
    {
        $notes=array_values((new ProceduralScore($seed))->phrase(0)['lead']);
        $first=$notes[0];
        return implode(',',array_map(static fn($n)=>$n-$first,$notes));
    }
    /** @return array{seed:int,recent:array} */
    public static function choose(int $candidate, array $recent): array
    {
        $recent=array_slice(array_values(array_filter($recent,'is_string')),-self::HISTORY);
        $candidate=max(1,min(65520,$candidate));
        for($i=0;$i<65520;$i++){
            $seed=($candidate-1+$i)%65520+1;
            $signature=self::signature($seed);
            if(in_array($signature,$recent,true))continue;
            $recent[]=$signature;
            return ['seed'=>$seed,'recent'=>array_slice($recent,-self::HISTORY)];
        }
        throw new \LogicException('No unused melody in the bounded seed space.');
    }
}
