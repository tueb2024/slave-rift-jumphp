<?php
/** Geometry and encounter budgets of the campaign actually launched by JOUER. */
require_once __DIR__.'/test-bootstrap.php';
use App\Game\{CampaignRun,XpCurve};
$seeds=[1,42,1337,65520];$runs=[];
foreach($seeds as $seed){
    $campaign=new CampaignRun($seed,new XpCurve());$rows=[];
    foreach(range(1,4) as $stage){
        $run=$campaign->run;$mode=$run->mode;$budgets=[];
        foreach(range(1,$mode->waveCount()) as $wave)$budgets[]=$mode->waveBudget($wave);
        $rows[]=['stage'=>$stage,'seed'=>$mode->seed,'geometry'=>$campaign->geometry,'encounters'=>$mode->waveCount(),
            'waveBudgets'=>$budgets,'finalWave'=>$mode->finalWaveSize,'totalEnemies'=>array_sum($budgets)+$mode->finalWaveSize,
            'activeCap'=>[$mode->enemyLimit(1),$mode->enemyLimit($mode->waveCount())],
            'spawnInterval'=>[$mode->spawnInterval(1),$mode->spawnInterval($mode->waveCount())],
            'rooms'=>array_map(static fn($r)=>['id'=>$r['id'],'mainPath'=>$r['mainPath'],'bounds'=>$r['bounds']],$campaign->level->rooms)];
        $run->state='won';if($stage<4)$campaign->advance();
    }
    $runs[]=['seed'=>$seed,'stages'=>$rows];
}
echo json_encode(['scope'=>'Generated geometry and configured budgets; not a human completion-time estimate.','runs'=>$runs],JSON_PRETTY_PRINT)."\n";
