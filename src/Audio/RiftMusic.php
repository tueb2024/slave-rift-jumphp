<?php
namespace App\Audio;
use JumPHP\Audio\SoundManager;

/** RhythmClock is the only transport. Musical state survives waves and daily maps. */
final class RiftMusic
{
    private Sampler $sampler;
    private ProceduralScore $score;
    private int $bar=0;
    private int $previous=-1;
    private bool $resolved=false;
    public function __construct(SoundManager $sound){$this->sampler=new Sampler($sound);$this->reset(1337);}
    public function register(): void{$this->sampler->register();}
    public function reset(int $seed): void{$this->sampler->stop();$this->score=new ProceduralScore($seed);$this->bar=0;$this->previous=-1;$this->resolved=false;}
    public function playSteps(array $steps,int $level,bool $boss=false,int $pressure=0): void
    {
        $this->register(); // Retry after browser audio becomes available on a gesture.
        foreach($steps as$step){
            if($step===0&&$this->previous>=0)$this->bar++;
            foreach($this->score->events($step,$this->bar,$level,$boss,$pressure)as[$voice,$note])$this->sampler->play($voice,$note);
            $this->previous=$step;
        }
    }
    public function resolve(): void
    {
        if($this->resolved)return;$this->resolved=true;
        foreach([0,3,7]as$interval)$this->sampler->play('lead',$this->score->root+12+$interval);
        $this->sampler->play('bass',$this->score->root-12);
    }
}
