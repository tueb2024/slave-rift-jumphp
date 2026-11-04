<?php
require __DIR__.'/test-bootstrap.php';
use App\Audio\ProceduralScore;
use App\Game\RhythmClock;
$score=new ProceduralScore(1337);$clock=new RhythmClock();$events=[];$bar=0;$previous=-1;
for($frame=0;$frame<48*240;$frame++){
    $t=$frame/240;$level=match(true){$t<8=>1,$t<16=>4,$t<24=>8,$t<32=>12,default=>16};
    foreach($clock->advance(1/240,$level)as$step){
        if($step===0&&$previous>=0)$bar++;
        foreach($score->events($step,$bar,$level,$t>=40,$t>=36?24:0)as[$voice,$note])$events[]=[$t,$voice,$note];
        $previous=$step;
    }
    $clock->drain();
}
// Same cadence as RiftMusic::resolve().
foreach([0,3,7]as$i)$events[]=[48,'lead',$score->root+12+$i];
$events[]=[48,'bass',$score->root-12];
echo json_encode($events,JSON_PRETTY_PRINT)."\n";
