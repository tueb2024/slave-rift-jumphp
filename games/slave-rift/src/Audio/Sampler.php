<?php
namespace App\Audio;
use JumPHP\Audio\SoundManager;

/** Offline PCM bank, six reserved melodic channels and ten SFX channels. */
final class Sampler
{
    private const DRUMS=['kick'=>'rift_kick','snare'=>'rift_snare','clap'=>'rift_clap','hat'=>'rift_hat','openhat'=>'rift_openhat'];
    private const RANGES=['bass'=>[33,47],'lead'=>[60,76],'counter'=>[41,56]];
    private array $chunks=[];
    private array $active=[];
    private bool $ready=false;
    public function __construct(private SoundManager $sound){}
    public static function frequency(int $midi): float{return 440*2**(($midi-69)/12);}
    public static function key(string $voice,?int $midi=null): string
    {
        if(isset(self::DRUMS[$voice]))return self::DRUMS[$voice];
        $range=self::RANGES[$voice]??null;
        if(!$range||$midi===null||$midi<$range[0]||$midi>$range[1])throw new \InvalidArgumentException('Sampler note out of bank');
        return 'sr_'.$voice.'_'.$midi;
    }
    public static function path(string $voice,?int $note=null): string
    {
        self::key($voice,$note);
        return dirname(__DIR__,2).'/assets/audio/'.$voice.($note===null?'':'_'.$note).'.wav';
    }
    public function register(): void
    {
        if($this->ready||!$this->sound->isEnabled())return;
        \Mix_ReserveChannels(6);
        foreach(self::DRUMS as$voice=>$key)$this->sound->loadSfx($key,self::path($voice));
        foreach(self::RANGES as$voice=>[$lo,$hi])for($n=$lo;$n<=$hi;$n++){
            $chunk=\Mix_LoadWAV(self::path($voice,$n));
            if(!$chunk)throw new \RuntimeException('Cannot load sampled note '.self::path($voice,$n));
            $this->chunks[self::key($voice,$n)]=$chunk;
        }
        $this->ready=true;
    }
    public function play(string $voice,?int $note=null): void
    {
        if(!$this->ready||!$this->sound->isEnabled()||$this->sound->isMuted())return;
        if(isset(self::DRUMS[$voice])){$this->sound->playSfx(self::key($voice),0,false);return;}
        $base=['bass'=>0,'lead'=>2,'counter'=>4][$voice];
        $old=$this->active[$voice]??($base+1);$next=$old===$base?$base+1:$base;
        \Mix_FadeOutChannel($old,80);
        \Mix_Volume($next,(int)round(128*$this->sound->getSfxVolume()));
        \Mix_PlayChannelTimed($next,$this->chunks[self::key($voice,$note)],0,-1);
        $this->active[$voice]=$next;
    }
    public function stop(): void
    {
        if(!$this->ready||!$this->sound->isEnabled())return;
        foreach(range(0,5)as$c)\Mix_FadeOutChannel($c,80);
        $this->active=[];
    }
    public function __destruct()
    {
        // Engine shutdown closes SDL before scene objects are collected. Never call
        // SDL_mixer after SoundManager::close(); the process owns remaining PCM then.
        if(!$this->ready||!$this->sound->isEnabled())return;
        foreach(range(0,5)as$c)\Mix_HaltChannel($c);
        foreach($this->chunks as$chunk)\Mix_FreeChunk($chunk);
    }
}
