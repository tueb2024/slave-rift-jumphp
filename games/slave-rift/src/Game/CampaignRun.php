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

    public function __construct(public readonly int $seed, public readonly XpCurve $xpCurve = new XpCurve(XpCurve::ACCESSIBLE, XpCurve::CAMPAIGN_MILESTONES), public readonly float $healMissing = 0.35, public readonly ?string $dailyId = null, public readonly ?array $stageSeeds = null)
    {
        if($seed<1 || $seed>65520)throw new \InvalidArgumentException('Campaign seed must be 1..65520');
        if($stageSeeds!==null && (count($stageSeeds)!==4 || count(array_unique($stageSeeds))!==4 || array_filter($stageSeeds,static fn($s)=>!is_int($s)||$s<1||$s>65520)!==[]))throw new \InvalidArgumentException('Four distinct valid seeds required');
        $this->build(null);
    }

    public function state(): string
    {
        return $this->run->state === 'won' ? ($this->stage === 4 ? 'won' : 'level_complete') : $this->run->state;
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
        $this->level=(new LevelAssembler)->assemble(new LevelGraph($seed,$mode->depth));
        $area=0.0;foreach($this->level->rooms as$room){$b=$room['bounds'];$area+=($b['max'][0]-$b['min'][0])*($b['max'][2]-$b['min'][2]);}
        if($this->stage===1)$this->baseArea=$area;
        $this->geometry=['depth'=>$mode->depth,'mainRooms'=>count(array_filter($this->level->rooms,static fn($r)=>$r['mainPath'])),
            'footprint'=>$area,'surfaceRatio'=>$area/$this->baseArea,'horizontalScale'=>1.0,'solids'=>count($this->level->map->solids)];
        $this->run=new Run($this->level->map,$mode->seed,$mode,$this->xpCurve);
        $this->run->setObjective($this->level,$mode->finalWaveSize);
        if($saved!==null)$this->run->restoreProgression($saved,$this->healMissing);
    }

    public static function daily(string $date,?array $serverSeeds=null,?string $id=null): self
    {
        $seeds=$serverSeeds??array_map(static fn($stage)=>GameMode::daily($date,$stage)->seed,range(1,4));
        return new self($seeds[0],new XpCurve(XpCurve::ACCESSIBLE,XpCurve::CAMPAIGN_MILESTONES),.35,$date.($id===null?'':'/'.$id),$seeds);
    }
}
