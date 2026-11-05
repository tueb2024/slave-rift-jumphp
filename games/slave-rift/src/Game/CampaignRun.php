<?php
namespace App\Game;

/** Four-stage continuous run; daily seeds are fixed before gameplay starts. */
final class CampaignRun
{
    public int $stage = 1;
    public Run $run;
    public AssembledLevel $level;
    public float $baseArea = 0;
    public array $geometry = [];

    public function __construct(public readonly int $seed, public readonly XpCurve $xpCurve = new XpCurve(XpCurve::ACCESSIBLE, XpCurve::CAMPAIGN_MILESTONES), public readonly float $healMissing = 0.35, public readonly ?string $dailyId = null, public readonly ?array $stageSeeds = null, public readonly int $totalStages = 4)
    {
        if($seed<1 || $seed>65520)throw new \InvalidArgumentException('Campaign seed must be 1..65520');
        if (!in_array($this->totalStages,[3,4],true)) throw new \InvalidArgumentException('Campaign must have three or four stages');
        if($stageSeeds!==null && (count($stageSeeds)!==$this->totalStages || count(array_unique($stageSeeds))!==$this->totalStages || array_filter($stageSeeds,static fn($s)=>!is_int($s)||$s<1||$s>65520)!==[]))throw new \InvalidArgumentException('Distinct valid stage seeds required');
        $this->build(null);
    }

    public function state(): string
    {
        return $this->run->state === 'won' ? ($this->stage === $this->totalStages ? 'won' : 'level_complete') : $this->run->state;
    }

    public function advance(): bool
    {
        if ($this->state() !== 'level_complete') return false;
        $saved=$this->run->progressionSnapshot();
        $this->stage++;$this->build($saved);return true;
    }

    public static function stageSeed(int $seed, int $stage): int
    {
        return (($seed-1+($stage-1)*7919)%65520)+1;
    }

    private function build(?array $saved): void
    {
        $seed=$this->stageSeeds[$this->stage-1]??self::stageSeed($this->seed,$this->stage);
        $mode=$this->dailyId===null?GameMode::campaignStage($seed,$this->stage):GameMode::daily(substr($this->dailyId,0,10),$this->stage,$seed,$this->dailyId);
        $graph=new LevelGraph($seed,$mode->depth);
        $minimum=$this->geometry['footprint']??0;
        $promotions=0;
        do{
            $this->level=(new LevelAssembler)->assemble($graph);
            $area=0.0;foreach($this->level->rooms as$room){$b=$room['bounds'];$area+=($b['max'][0]-$b['min'][0])*($b['max'][2]-$b['min'][2]);}
            if($area>$minimum)break;
            // A longer random route can still contain more narrow corridors. Promote
            // fixed-size modules in graph order; never scale rooms, stairs or doors.
            $changed=false;
            foreach($graph->nodes as$id=>$node)if($node['mainPath']&&in_array($node['type'],[LevelGraph::CORRIDOR_SHORT,LevelGraph::CORRIDOR_LONG,LevelGraph::SMALL_ROOM],true)){
                $graph->nodes[$id]['type']=LevelGraph::LARGE_ROOM;$changed=true;$promotions++;break;
            }
            if(!$changed)throw new \RuntimeException('Cannot build a strictly larger campaign stage');
        }while($promotions<=48);
        if($this->stage===1)$this->baseArea=$area;
        $this->geometry=['depth'=>$mode->depth,'mainRooms'=>count(array_filter($this->level->rooms,static fn($r)=>$r['mainPath'])),
            'footprint'=>$area,'surfaceRatio'=>$area/$this->baseArea,'horizontalScale'=>1.0,'roomPromotions'=>$promotions,'solids'=>count($this->level->map->solids)];
        $this->run=new Run($this->level->map,$mode->seed,$mode,$this->xpCurve);
        $this->run->setObjective($this->level,$mode->finalWaveSize);
        if($saved!==null)$this->run->restoreProgression($saved,$this->healMissing);
    }

    /**
     * Each new JOUER campaign rolls independent, unique seeds for its 3 or 4 maps.
     * No daily ID or date influences this run; the selected seeds remain stable
     * during the same campaign and can be logged/replayed if needed.
     */
    public static function random(int $stageCount = 4, ?int $firstSeed = null): self
    {
        if (!in_array($stageCount,[3,4],true)) throw new \InvalidArgumentException('Campaign must have three or four stages');
        if ($firstSeed !== null && ($firstSeed < 1 || $firstSeed > 65520)) throw new \InvalidArgumentException('Invalid first-stage seed');
        $seeds = $firstSeed === null ? [] : [$firstSeed];
        while (count($seeds) < $stageCount) {
            $candidate = random_int(1,65520);
            if (!in_array($candidate,$seeds,true)) $seeds[] = $candidate;
        }
        return new self($seeds[0],new XpCurve(XpCurve::ACCESSIBLE,XpCurve::CAMPAIGN_MILESTONES),.35,null,$seeds,$stageCount);
    }

    public static function daily(string $date,?array $serverSeeds=null,?string $id=null): self
    {
        $seeds=$serverSeeds??array_map(static fn($stage)=>GameMode::daily($date,$stage)->seed,range(1,4));
        return new self($seeds[0],new XpCurve(XpCurve::ACCESSIBLE,XpCurve::CAMPAIGN_MILESTONES),.35,$date.($id===null?'':'/'.$id),$seeds);
    }
}
