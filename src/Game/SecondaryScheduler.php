<?php
namespace App\Game;

/** Deterministic FIFO of ready weapons. Waiting never resets a weapon's cooldown. */
final class SecondaryScheduler
{
    private array $queue=[];
    private float $gap=0.0;

    public function step(float $dt,array $ready,array $eligible,float $spacing): ?string
    {
        $this->gap=max(0.0,$this->gap-max(0.0,$dt));
        $this->queue=array_values(array_filter($this->queue,static fn($id)=>in_array($id,$ready,true)));
        foreach($ready as$id)if(!in_array($id,$this->queue,true))$this->queue[]=$id;
        if($this->gap>1e-9)return null;
        foreach($this->queue as$i=>$id)if(in_array($id,$eligible,true)){
            unset($this->queue[$i]);$this->queue=array_values($this->queue);
            $this->gap=max(0.05,$spacing);return $id;
        }
        return null;
    }

    public function snapshot(): array{return ['queue'=>$this->queue,'gap'=>$this->gap];}
    public function restore(array $state): void{$this->queue=array_values($state['queue']??[]);$this->gap=max(0,(float)($state['gap']??0));}
    public function pending(): array{return $this->queue;}
}
