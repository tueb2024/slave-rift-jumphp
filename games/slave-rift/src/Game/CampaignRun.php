<?php
namespace App\Game;

/** Experimental four-level campaign. Not substituted for NORMAL until human qualification. */
final class CampaignRun
{
    public int $stage = 1;
    public Run $run;
    public AssembledLevel $level;
    public float $baseArea = 0;
    public array $geometry = [];

    public function __construct(public readonly int $seed, public readonly XpCurve $xpCurve = new XpCurve(), public readonly float $healMissing = 0.35)
    {
        if($seed<1 || $seed>65520)throw new \InvalidArgumentException('Campaign seed must be 1..65520');
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
        $mode=GameMode::campaignStage(self::stageSeed($this->seed,$this->stage),$this->stage);
        $level=(new LevelAssembler)->assemble(new LevelGraph($mode->seed,$mode->depth));
        $area=0.0;
        foreach($level->rooms as $room){$b=$room['bounds'];$area+=($b['max'][0]-$b['min'][0])*($b['max'][2]-$b['min'][2]);}
        if($this->stage===1)$this->baseArea=$area;
        $target=$this->baseArea*(2**($this->stage-1));$scale=sqrt($target/$area);
        $this->level=self::scale($level,$scale);
        $this->geometry=['depth'=>$mode->depth,'mainRooms'=>count(array_filter($level->rooms,static fn($r)=>$r['mainPath'])),
            'footprint'=>$target,'surfaceRatio'=>2**($this->stage-1),'horizontalScale'=>$scale,'solids'=>count($level->map->solids)];
        $this->run=new Run($this->level->map,$mode->seed,$mode,$this->xpCurve);
        $this->run->setObjective($this->level,$mode->finalWaveSize);
        if($saved!==null)$this->run->restoreProgression($saved,$this->healMissing);
    }

    private static function scale(AssembledLevel $level,float $factor): AssembledLevel
    {
        $point=static fn(array $p):array=>[$p[0]*$factor,$p[1],$p[2]*$factor];
        $anchor=static function(array $a)use($factor):array{$a['x']*=$factor;$a['z']*=$factor;return $a;};
        $solids=$level->map->solids;
        foreach($solids as &$solid){$solid['min']=$point($solid['min']);$solid['max']=$point($solid['max']);
            foreach($solid['triangles'] as &$v)$v['p']=$point($v['p']);unset($v);}
        unset($solid);
        $decals=$level->map->decals;
        foreach($decals as &$decal){$decal['position']=$point($decal['position']);$decal['width']*=$factor;}unset($decal);
        $rooms=$level->rooms;
        foreach($rooms as &$room){$room['bounds']['min']=$point($room['bounds']['min']);$room['bounds']['max']=$point($room['bounds']['max']);
            foreach($room['connectors'] as &$c){$c['position']=$point($c['position']);$c['width']*=$factor;}unset($c);}
        unset($room);
        $map=BrushMapFactory::fromGenerated($solids,array_map($point,$level->map->spawnPoints),$point($level->map->playerStart),$level->map->playerYaw,$decals);
        return new AssembledLevel($map,array_map($anchor,$level->spawnMeta),array_map($anchor,$level->pickupMeta),$rooms,
            $level->treasureAnchor===null?null:$anchor($level->treasureAnchor),$level->bossAnchor===null?null:$anchor($level->bossAnchor),$level->seed);
    }
}
