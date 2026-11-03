<?php
require_once __DIR__.'/test-bootstrap.php';
use App\Game\GameMode;
use App\Game\Run;
use App\Game\XpCurve;

function xpMath(): array
{
    $out=[]; $mode=GameMode::fixed(42);
    foreach([XpCurve::CURRENT,XpCurve::WEIGHTED,XpCurve::ACCESSIBLE] as $model){
        $curve=new XpCurve($model);$cumulative=0;$kills=0;$rows=[];$milestones=[];
        for($wave=1;$wave<=16;$wave++){
            $weights=Run::enemyWeightsForWave($wave);$mean=0;
            foreach($weights as $type=>$weight)$mean+=$weight*$curve->reward($type);
            $n=$mode->waveBudget($wave);$heals=intdiv($kills+$n,9)-intdiv($kills,9);$kills+=$n;
            $xp=($n-$heals)*$mean;$before=$cumulative;$cumulative+=$xp;
            $levels=[];foreach([1.0,0.8,0.6] as $collection)$levels[(string)$collection]=XpCurve::levelForTotal($cumulative*$collection);
            foreach([4,8,12,16,20,24] as $level)if(!isset($milestones[$level])&&$cumulative*.8>=XpCurve::totalForLevel($level))
                $milestones[$level]=round($wave-1+(XpCurve::totalForLevel($level)/.8-$before)/$xp,3);
            $rows[]=['wave'=>$wave,'composition'=>$weights,'enemies'=>$n,'heals'=>$heals,'meanXp'=>$mean,'expectedXp'=>$xp,'cumulativeXp'=>$cumulative,'levelsAtCollection'=>$levels];
        }
        $out[$model]=['assumption'=>'All wave budgets killed; heal positions counted exactly; class independent of kill order. Levels from mean XP, not a simulated population average.','waves'=>$rows,'milestoneWaveAt80Percent'=>$milestones];
    }
    return $out;
}
if(realpath($_SERVER['SCRIPT_FILENAME'])===__FILE__)echo json_encode(xpMath(),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
