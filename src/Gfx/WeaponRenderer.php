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
    /** Visual poses span the existing reload clock; no simulation timing changes. */
    public function shotgunFrame(float $reload): int
    {
        if ($reload <= 0) return 0;
        $age = max(0.0, Run::SHOTGUN_RELOAD - $reload);
        return match (true) {
            $age < .025 => 1,
            $age < .060 => 2,
            $age < .180 => 3,
            $age < .420 => 4,
            $age < .780 => 5,
            $age < 1.000 => 6,
            default => 7,
        };
    }
    public function render(Renderer $r,Run $run,array $texture): void
    {
        if($run->shotgunAvailable()){
            $frame=$this->shotgunFrame($run->shotgunReload);
            $l=$this->layout($r->getWidth(),$r->getHeight(),0,$run->time);
            // Recoil is authored into the registered poses, like the primary arsenal.
            $r->copy($texture['texture'],[$frame*self::FRAME_W,0,self::FRAME_W,self::FRAME_H],$l['x'],$l['y'],$l['drawW'],$l['drawH']);
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
