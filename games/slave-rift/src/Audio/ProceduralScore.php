<?php
namespace App\Audio;
use App\Game\LevelRandom;

/** Small phrase grammar; local musical RNG never reads or mutates Run. */
final class ProceduralScore
{
    public readonly int $root;
    public readonly array $scale;
    private array $motif;
    public function __construct(public readonly int $seed)
    {
        $rng=new LevelRandom(($seed+4093)%65520+1);
        $this->root=48+$rng->nextInt(5);
        $this->scale=$rng->chance(.5)?[0,2,3,5,7,8,10]:[0,2,3,5,7,9,10];
        $this->motif=$rng->chance(.5)?[0,2,4,3,2,0]:[0,1,3,4,2,1];
    }
    /** One four-beat phrase, repeated with controlled variation every four bars. */
    public function phrase(int $bar): array
    {
        $rotation=[0,3,4,0][intdiv(max(0,$bar),4)%4];
        $degrees=$this->motif;
        if($bar%4===3)$degrees[4]=max(0,$degrees[4]-1);
        $lead=[];
        foreach([0,3,6,8,11,14]as$i=>$step){$degree=min(6,$degrees[$i]+($rotation===0?0:1));$lead[$step]=$this->root+12+$this->scale[$degree];}
        $counter=[];$last=$this->root-5;
        foreach([0,5,9,13]as$step){
            // Validate consonance against the next structural beat (held notes count).
            $strong=match($step){0=>[0,4],5=>[8],9=>[12],default=>[0]};
            $valid=[];
            for($note=$this->root-7;$note<=$this->root+4;$note++){
                $pc=(($note-$this->root)%12+12)%12;if(!in_array($pc,$this->scale,true))continue;
                $ok=true;foreach($strong as$beat){$l=$lead[0];foreach($lead as$t=>$v)if($t<=$beat)$l=$v;
                    if(!in_array(($l-$note)%12,[0,3,4,7,8,9],true)||$l-$note<8)$ok=false;}
                if($ok)$valid[]=$note;
            }
            // Prefer small motion opposite the lead; seeded motif supplies identity.
            $l=$lead[0];foreach($lead as$t=>$v)if($t<=$step)$l=$v;
            $desired=$last+($l>$lead[0]?-2:2);
            usort($valid,static fn($a,$b)=>abs($a-$desired)<=>abs($b-$desired));
            $counter[$step]=$valid[0]??$this->root;$last=$counter[$step];
        }
        return ['lead'=>$lead,'counter'=>$counter,'bass'=>$this->root-12+$this->scale[$rotation]];
    }
    /** Pure event plan; arrangement intensity changes notes, never the clock. */
    public function events(int $step,int $bar,int $level,bool $boss=false,int $pressure=0): array
    {
        $step%=16;$p=$this->phrase($bar);$events=[];
        if($step%4===0)$events[]=['kick',null];
        if($level>=4 && in_array($step,[4,12],true))$events[]=['snare',null];
        if($level>=12 && in_array($step,[4,12],true))$events[]=['clap',null];
        if(($pressure>=12||$boss)&&$step%2===1)$events[]=['hat',null];
        if(($pressure>=20||$boss)&&$step===14)$events[]=['openhat',null];
        if($step%8===0)$events[]=['bass',$p['bass']];
        if($level>=2&&isset($p['lead'][$step]))$events[]=['lead',$p['lead'][$step]];
        if($level>=6 && isset($p['counter'][$step]))$events[]=['counter',$p['counter'][$step]];
        if($boss&&$step===15)$events[]=['bass',$this->root-12+7];
        return $events;
    }
}
