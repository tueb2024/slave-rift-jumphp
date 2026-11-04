<?php
namespace JumPHP\Audio {
    final class SoundManager {
        public bool $enabled=false;public array $loaded=[];
        public function isEnabled(): bool{return $this->enabled;}
        public function isMuted(): bool{return false;}
        public function getSfxVolume(): float{return 1;}
        public function loadSfx($name,$path): void{$this->loaded[$name]=$path;\Mix_LoadWAV($path);}
        public function playSfx(...$args): void{}
    }
}
namespace {
    require __DIR__.'/test-bootstrap.php';
    $calls=[];$loads=0;
    function Mix_ReserveChannels($n){if($n!==6)throw new \RuntimeException('Channel reservation');}
    function Mix_LoadWAV($path){global $loads;if(!is_file($path))throw new \RuntimeException($path);$loads++;return $path;}
    function Mix_FadeOutChannel($c,$ms){global $calls;$calls[]=['fade',$c,$ms];}
    function Mix_PlayChannelTimed($c,$chunk,$loop,$ticks){global $calls;$calls[]=['play',$c];}
    function Mix_Volume(...$args){}
    function Mix_HaltChannel(...$args){global $calls;$calls[]=['halt'];}
    function Mix_FreeChunk(...$args){global $calls;$calls[]=['free'];}
    function check($ok,$why){if(!$ok)throw new \RuntimeException($why);}
    $sound=new \JumPHP\Audio\SoundManager();$sampler=new \App\Audio\Sampler($sound);
    $sampler->register();check($loads===0,'Defer until audio enabled');
    $sound->enabled=true;$sampler->register();check($loads===53,'53 loads');
    $sampler->register();check($loads===53,'No reload in frame loop');
    foreach(range(0,100)as$i)$sampler->play('bass',36+$i%12);
    $plays=array_values(array_filter($calls,fn($c)=>$c[0]==='play'));
    foreach($plays as$i=>$call)check($call[1]===$i%2,'Alternate bass channels');
    foreach($calls as$call)if($call[0]==='fade')check($call[2]===80,'80ms release');
    $manifest=json_decode(file_get_contents(dirname(__DIR__).'/assets/audio/manifest.json'),true);
    check(count($manifest)===53,'Manifest count');
    foreach($manifest as$sample){
        $path=dirname(__DIR__).'/'.$sample['file'];
        check(hash_file('sha256',$path)===$sample['sha256'],'PCM integrity');
        check(file_get_contents($path,false,null,0,4)==='RIFF','PCM WAV');
    }
    foreach(range(1,128)as$seed){$s=new \App\Audio\ProceduralScore($seed);
        foreach(range(0,15)as$b)foreach(range(0,15)as$t)foreach($s->events($t,$b,16,true,24)as[$v,$n])check(is_file(\App\Audio\Sampler::path($v,$n)),'Every event in bank');
        check(in_array(['bass',$s->phrase(0)['bass']],$s->events(0,0,1),true),'Bass level 1');
        check(!in_array('lead',array_column($s->events(0,0,7),0),true),'Lead threshold');
        check(!in_array('counter',array_column($s->events(0,0,15),0),true),'Counter threshold');
    }
    $sound->enabled=false;$before=count($calls);unset($sampler);
    check(count($calls)===$before,'No SDL calls after audio shutdown');
    echo "PASS: deferred loading, 53 PCM hashes, bounded alternating channels, release, 128 seeds / 16 bars, thresholds, shutdown\n";
}
