<?php
namespace App\Gfx;
use App\Game\Run;
use JumPHP\Renderer\Renderer;

/** Eight real poses per stage; dual atlases are split around the aiming gap. */
final class WeaponRenderer
{
    public const READY='ready',FIRE='fire',RECOIL='recoil',COOLDOWN='cooldown';
    public const FRAME_W=96,FRAME_H=128;
    private string $state=self::READY;
    private float $stateStarted=0,$lastMuzzle=0;
    private int $lastSerial=-1;
    public static function textures(): array
    {
        $out=[];foreach(['pistol','dual_pistol','smg','dual_smg','machinegun','minigun','dual_minigun']as$id)$out['weapon_'.$id]='assets/sprites/arsenal/'.$id.'.png';$out['weapon_shotgun']='assets/sprites/arsenal/shotgun.png';return $out;
    }
    public function textureKey(int $stage): string{return array_keys(self::textures())[max(0,min(6,$stage))];}
    public function reset(float $time=0): void{$this->state=self::READY;$this->stateStarted=$time;$this->lastMuzzle=0;$this->lastSerial=-1;}
    public function update(float $time,float $muzzle): string
    {
        if($muzzle>$this->lastMuzzle+.015){$this->state=self::FIRE;$this->stateStarted=$time;}
        else{$age=$time-$this->stateStarted;$this->state=match(true){$age<.034=>self::FIRE,$age<.078=>self::RECOIL,$age<.132=>self::COOLDOWN,default=>self::READY};}
        $this->lastMuzzle=$muzzle;return $this->state;
    }
    public function frameForState(string $state): int{return match($state){self::FIRE=>2,self::RECOIL=>3,self::COOLDOWN=>5,default=>0};}
    public function rowForStage(int $stage): int{return 0;}
    public function state(): string{return $this->state;}
    public function layout(int $width,int $height,int $stage,float $time): array
    {
        $dual=in_array($stage,[1,3,6],true);$drawH=(int)round($height*.75);
        $drawW=$drawH*($dual?.375:.75);
        $x=$dual?(int)round($width*.08):(int)round(($width-$drawW)/2);
        $spread=$dual?$width-2*$x-$drawW:0;
        return ['drawW'=>$drawW,'drawH'=>$drawH,'x'=>$x,'y'=>$height-$drawH,'copies'=>$dual?2:1,'spread'=>$spread];
    }
    public function render(Renderer $r,Run $run,array $texture): void
    {
        if($run->shotgunAvailable()){
            $age=Run::SHOTGUN_RELOAD-$run->shotgunReload;
            $frame=$run->shotgunReload<=0?0:($age<.06?1:($age<.18?2:($age<.65?3:0)));
            $h=(int)round($r->getHeight()*.72);$w=(int)round($h*.75);
            $kick=$run->shotgunFlash>0?(int)round($run->shotgunFlash*45):0;
            $r->copy($texture['texture'],[$frame*96,0,96,128],($r->getWidth()-$w)/2,$r->getHeight()-$h+$kick,$w,$h);
            return;
        }
        if($this->lastSerial!==$run->primaryShotSerial){$this->lastSerial=$run->primaryShotSerial;$this->stateStarted=$run->time;}
        $age=$run->time-$this->stateStarted;$dual=in_array($run->primaryStage,[1,3,6],true);
        $phase=match(true){$run->primaryShotSerial===0||$age>=.23=>0,$age<.025=>1,$age<.06=>2,$age<.1=>3,$age<.145=>4,$age<.185=>5,default=>6};
        $this->state=$phase===0?self::READY:($phase<=2?self::FIRE:($phase===3?self::RECOIL:self::COOLDOWN));
        $frame=$dual?($phase===0?0:($run->primaryShotSide===0?min(3,$phase):min(6,3+$phase))):$phase;
        $l=$this->layout($r->getWidth(),$r->getHeight(),$run->primaryStage,$run->time);
        $srcW=$dual?48:96;
        $r->copy($texture['texture'],[$frame*96,0,$srcW,128],$l['x'],$l['y'],$l['drawW'],$l['drawH']);
        if($dual)$r->copy($texture['texture'],[$frame*96+48,0,48,128],$l['x']+$l['spread'],$l['y'],$l['drawW'],$l['drawH']);
    }
}
