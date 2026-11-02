<?php
require_once __DIR__.'/balance-sim.php';
use App\Game\CampaignRun;
use App\Game\XpCurve;
use App\Game\RhythmClock;

function simulateCampaign(string $profile,int $seed,string $model=XpCurve::CURRENT,float $heal=.35, bool $compactMilestones=false): array
{
    $campaign=new CampaignRun($seed,new XpCurve($model,$compactMilestones?XpCurve::CAMPAIGN_MILESTONES:XpCurve::LEGACY_MILESTONES),$heal);$rows=[];$clock=new RhythmClock();
    for($stage=1;$stage<=4;$stage++){
        $run=$campaign->run;$start=$run->progressionSnapshot();
        $result=simulateProfile($profile,$run->mode->seed,900,'route',$run->mode,$run->xpCurve,$run,$clock);
        $rows[]=['stage'=>$stage,'seed'=>$run->mode->seed,'geometry'=>$campaign->geometry,'start'=>$start,
            'duration'=>$run->time,'kills'=>$run->kills-$start['kills'],'xpEarned'=>$run->collectedXp-$start['collectedXp'],'result'=>$result];
        if(!$campaign->advance())break;
    }
    return ['profile'=>$profile,'seed'=>$seed,'xpModel'=>$model,'healMissing'=>$heal,'milestones'=>$campaign->xpCurve->milestones,'state'=>$campaign->state(),'reached'=>count($rows),'levels'=>$rows];
}

if(realpath($_SERVER['SCRIPT_FILENAME'])===__FILE__){
    $n=max(1,min(100,(int)($argv[1]??10)));$models=($argv[2]??'all')==='all'?[XpCurve::CURRENT,XpCurve::WEIGHTED,XpCurve::ACCESSIBLE]:[$argv[2]];
    $heal=(float)($argv[3]??.35);$rows=[];
    foreach($models as $model)foreach(['faible','moyen','bon']as$profile)for($seed=1;$seed<=$n;$seed++){
        $row=simulateCampaign($profile,$seed,$model,$heal,($argv[4]??'legacy')==='compact');$rows[]=$row;
        fwrite(STDERR,"$model $profile seed $seed: {$row['state']} / L{$row['reached']}\n");
        if(isset($argv[5]))file_put_contents($argv[5],json_encode($row,JSON_UNESCAPED_UNICODE)."\n",FILE_APPEND);
    }
    $summary=[];
    foreach($models as$model)foreach(['faible','moyen','bon']as$profile){
        $group=array_values(array_filter($rows,static fn($r)=>$r['profile']===$profile&&$r['xpModel']===$model));
        $s=['runs'=>count($group),'victoryPercent'=>100*count(array_filter($group,static fn($r)=>$r['state']==='won'))/count($group)];
        foreach(range(1,4)as$stage){$levels=[];foreach($group as$r)if(isset($r['levels'][$stage-1]))$levels[]=$r['levels'][$stage-1];
            $s['stages'][$stage]=['accessPercent'=>100*count($levels)/count($group),'runs'=>count($levels),'meanStartLevel'=>$levels?array_sum(array_column(array_column($levels,'start'),'level'))/count($levels):null,
                'meanEndLevel'=>$levels?array_sum(array_column(array_column($levels,'result'),'level'))/count($levels):null,'bossWeapons'=>[]];
            foreach($levels as$l){$weapon=$l['result']['checkpoints']['boss']['weapon']??'BOSS NON ATTEINT';$s['stages'][$stage]['bossWeapons'][$weapon]=($s['stages'][$stage]['bossWeapons'][$weapon]??0)+1;}
        }
        $summary[$model][$profile]=$s;
    }
    echo json_encode(['scope'=>'Experimental four-stage campaign; geometry doubles footprint, not graph depth. Simulation policy, not a human win-rate prediction.','summary'=>$summary,'runs'=>$rows],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
}
