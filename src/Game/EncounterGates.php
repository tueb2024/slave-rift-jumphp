<?php
namespace App\Game;

/** Seeded room topology defines encounter cuts. Gates only open, never reclose on survivors. */
final class EncounterGates
{
    public array $gates = [];
    public int $opened = 0;
    public function __construct(public readonly array $rooms, int $waves)
    {
        $main=array_values(array_filter($rooms,static fn($r)=>$r['mainPath']));
        $last=count($main)-2;
        $previous=0;
        for($wave=1;$wave<=$waves;$wave++){
            $ideal=max($previous+1,(int)floor($last*$wave/$waves));
            $index=min($last,$ideal);
            // Prefer the exit of a combat room near the topological cut, never a stair mid-flight.
            $candidates=[];
            foreach($main as $i=>$room){
                if($i<=$previous||$i>$last-($waves-$wave))continue;
                if(in_array($room['type'],[LevelGraph::LARGE_ROOM,LevelGraph::SMALL_ROOM,LevelGraph::JUNCTION],true))$candidates[$i]=abs($i-$ideal);
            }
            if($candidates!==[]){asort($candidates);$index=(int)array_key_first($candidates);}
            $room=$main[$index];$exit=null;
            foreach($room['connectors'] as $c)if($c['role']==='main_exit')$exit=$c;
            if($exit===null)continue;
            [$x,$y,$z]=$exit['position'];[$nx,,$nz]=$exit['normal'];
            $hx=abs($nx)>.5?.18:$exit['width']/2;
            $hz=abs($nz)>.5?.18:$exit['width']/2;
            $this->gates[]=['wave'=>$wave,'depth'=>$room['depth'],'roomId'=>$room['id'],'open'=>false,
                'position'=>$exit['position'],'normal'=>$exit['normal'],
                'min'=>[$x-$hx,$y,$z-$hz],'max'=>[$x+$hx,$y+$exit['height'],$z+$hz]];
            $previous=$index;
        }
    }
    public function blockers(): array{return array_values(array_filter($this->gates,static fn($g)=>!$g['open']));}
    public function clear(int $wave): bool
    {
        $changed=false;
        foreach($this->gates as &$gate)if($gate['wave']<=$wave&&!$gate['open']){$gate['open']=true;$this->opened++;$changed=true;}
        unset($gate);return $changed;
    }
    public function depthAt(float $x,float $y,float $z): ?int
    {
        foreach($this->rooms as $room){$b=$room['bounds'];
            if($x<$b['min'][0]-.001||$x>$b['max'][0]+.001||$z<$b['min'][2]-.001||$z>$b['max'][2]+.001)continue;
            if($y<$b['min'][1]||$y>$b['max'][1])continue;
            // BONUS belongs to its parent's encounter, rather than the following zone.
            return $room['mainPath']?$room['depth']:$room['depth']-1;
        }
        return null;
    }
    public function inZone(float $x,float $y,float $z,int $wave): bool
    {
        $depth=$this->depthAt($x,$y,$z);if($depth===null)return false;
        $min=$wave>1?($this->gates[$wave-2]['depth']??-1)+1:0;
        $max=$this->gates[$wave-1]['depth']??PHP_INT_MAX;
        return $depth>=$min&&$depth<=$max;
    }
}
