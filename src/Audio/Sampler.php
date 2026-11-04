<?php
namespace App\Audio;
use JumPHP\Audio\SoundManager;
use JumPHP\Audio\Synth;

/** Bounded multisample bank: synthesize each pitch once, use the existing SDL mixer. */
final class Sampler
{
    private const DRUMS=['kick'=>'rift_kick','snare'=>'rift_snare','clap'=>'rift_clap','hat'=>'rift_hat'];
    public function __construct(private SoundManager $sound){}
    public static function frequency(int $midi): float{return 440*2**(($midi-69)/12);}
    public static function key(string $voice,?int $midi=null): string
    {
        if(isset(self::DRUMS[$voice]))return self::DRUMS[$voice];
        if(!in_array($voice,['bass','lead','counter'],true)||$midi===null||$midi<29||$midi>76)throw new \InvalidArgumentException('Sampler note out of bank');
        return 'sr_'.$voice.'_'.$midi;
    }
    public function register(): void
    {
        if(!$this->sound->isEnabled())return;
        RiftSfx::register($this->sound);
        foreach(['bass'=>[29,47,.20,'sine',.19],'lead'=>[60,76,.13,'triangle',.14],'counter'=>[41,56,.16,'triangle',.10]]as$voice=>[$lo,$hi,$length,$wave,$volume]){
            for($note=$lo;$note<=$hi;$note++){$key=self::key($voice,$note);
                if(!$this->sound->hasSfx($key))$this->sound->synth($key,Synth::tone(self::frequency($note),$length,$wave,$volume));
            }
        }
    }
    public function play(string $voice,?int $note=null): void{$this->sound->playSfx(self::key($voice,$note),0,false);}
}
