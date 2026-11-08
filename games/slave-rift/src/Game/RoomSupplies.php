<?php
namespace App\Game;

/** Seeded room rewards use no combat RNG and never occupy enemy-drop capacity. */
final class RoomSupplies
{
    public static function place(AssembledLevel $level, Navigation $navigation): array
    {
        $rooms=array_values(array_filter($level->rooms,static fn($r)=>in_array($r['type'],[LevelGraph::SMALL_ROOM,LevelGraph::BONUS],true)));
        $selected=array_values(array_filter($rooms,static fn($r)=>(($level->seed*113+$r['id']*7919+37)%65521)%3===0));
        if($selected===[] && $rooms!==[])$selected=[$rooms[($level->seed*31)%count($rooms)]];
        $packs=[];
        foreach($selected as $room){
            $bounds=$room['bounds'];$cx=($bounds['min'][0]+$bounds['max'][0])/2;$cz=($bounds['min'][2]+$bounds['max'][2])/2;
            $entry=reset($room['connectors']);$floor=$entry['position'][1];
            foreach([[-1.15,-.7],[0.,.9],[1.15,-.7]] as [$dx,$dz]){
                $x=$cx+$dx;$z=$cz+$dz;
                $support=$navigation->support($x,$z,.25,$floor+.1);
                if($support===null || abs($support-$floor)>.15 || $navigation->collides($x,$support+.7,$z,.28,.7))continue;
                $packs[]=['x'=>$x,'y'=>$support+.25,'z'=>$z,'kind'=>'health','value'=>16,'age'=>0.,'roomId'=>$room['id']];
                if(count($packs)>=32)return $packs;
            }
        }
        return $packs;
    }
}
