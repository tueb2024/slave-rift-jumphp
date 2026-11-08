<?php
namespace App\Gfx;
use App\Game\Run;
use JumPHP\Gl\Mat4;
use JumPHP\Renderer\{Renderer,Sdl};

/** One shared atlas per power; draw only bounded visible effects. */
final class PowerRenderer
{
    public static function textures(): array
    {
        $a=[];foreach(['flame','sniper','artillery','rocket','emp']as$id)$a['power_'.$id]='assets/sprites/arsenal/'.$id.'.png';return $a;
    }
    private static function sprite(Renderer $r,Sdl $s,string $id,int $frame,float $x,float $y,float $height): void
    {
        $t=$s->getTexture('power_'.$id);$w=$height*.75;
        $r->copy($t['texture'],[min(7,max(0,$frame))*96,0,96,128],$x-$w/2,$y-$height*.78,$w,$height);
    }
    public static function world(Renderer $r,Sdl $s,Run $run,float $pitch): void
    {
        $vp=World::camera($run,$r->getWidth()/max(1,$r->getHeight()),$pitch);
        $project=static function($p)use($vp,$r,$run){if(!$run->lineClear(World::eye($run),$p))return null;$q=Mat4::project($vp,$p,$r->getWidth(),$r->getHeight());return $q!==null&&$q[2]>=0&&$q[2]<=1?$q:null;};
        self::flameJet($r,$s,$run,$project);
        foreach($run->projectiles as$p){$q=$project([$p['x'],$p['y'],$p['z']]);if(!$q)continue;
            self::sprite($r,$s,'rocket',(int)($p['age']/.075)%8,$q[0],$q[1],max(12,min(64,110/max(1,hypot($p['x']-$run->x,$p['z']-$run->z)))));
        }
        foreach($run->explosions as$e){$q=$project([$e['x'],$e['y'],$e['z']]);if(!$q)continue;
            self::sprite($r,$s,'artillery',(int)((1-$e['life']/($e['totalLife']??.7))*8),$q[0],$q[1],min(160,180*$e['radius']/max(2,hypot($e['x']-$run->x,$e['z']-$run->z))));
        }
        foreach($run->traces as$t)if($t['type']==='sniper'){
            $q=$project([$t['endX'],$t['endY'],$t['endZ']]);if(!$q)continue;
            // The true ray is projected from the hit position, not baked into HUD coordinates.
            $r->drawLine((int)($r->getWidth()/2),(int)($r->getHeight()*.86),(int)$q[0],(int)$q[1],[150,255,255]);
            self::sprite($r,$s,'sniper',min(7,(int)((1-$t['life']/$t['totalLife'])*8)),$q[0],$q[1],32);
        }
        foreach($run->enemies as$e)if(($e['stun']??0)>0 || ($e['spawnDelay']??0)>0){$q=$project([$e['x'],$e['y']+.15,$e['z']]);if(!$q)continue;
            self::ring($r,$q[0],$q[1],max(3,35/max(1,hypot($e['x']-$run->x,$e['z']-$run->z))),($e['stun']??0)>0?[100,230,255]:[255,180,70]);
        }
    }
    /** Textured wisps follow a world-space stream, narrowing into the aiming direction. */
    private static function flameJet(Renderer $r,Sdl $s,Run $run,callable $project): void
    {
        if($run->flameActive<=0 || $run->flameSegments===[])return;
        $limit=max(array_column($run->flameSegments,'d'));
        $texture=$s->getTexture('power_flame');$points=[];
        foreach([.38,.55,.78,1.06,1.42,1.85,2.38,3.03,3.83,4.8,6.,6.7] as $d){
            if($d>$limit)break;
            $lateral=-.32*exp(-$d/1.7);
            $p=[$run->x+sin($run->yaw)*$d+cos($run->yaw)*$lateral,
                $run->y+.23+sin($run->time*14+$d*5)*.018,
                $run->z+cos($run->yaw)*$d-sin($run->yaw)*$lateral];
            $q=$project($p);if($q===null)break;
            $points[]=['x'=>$q[0],'y'=>$q[1],'d'=>$d];
        }
        $fade=min(1.,$run->flameActive/.18);$scale=$r->getHeight()/180;
        // Back-to-front wisps overlap into one stream. No upright fire billboards.
        for($i=count($points)-2;$i>=0;$i--){
            $a=$points[$i];$b=$points[$i+1];$dx=$b['x']-$a['x'];$dy=$b['y']-$a['y'];
            $radius=(.085+.045*$a['d'])*100/max(.3,$a['d'])*$scale*$fade;
            $width=max(hypot($dx,$dy)*2.3,$radius*2.5);$height=$radius*4.4;
            $frame=((int)($run->time/.065)+$i*3)%8;
            $r->copy($texture['texture'],[$frame*96,0,96,128],($a['x']+$b['x'])/2-$width/2,($a['y']+$b['y'])/2-$height/2,
                $width,$height,false,false,rad2deg(atan2($dy,$dx)));
        }
    }

    public static function foreground(Renderer $r,Sdl $s,Run $run): void
    {
        $w=$r->getWidth();$h=$r->getHeight();
        if($run->empFx>0)self::sprite($r,$s,'emp',min(7,(int)((.65-$run->empFx)/.65*8)),$w/2,$h*.8,$h*.8);
    }
    public static function ring(Renderer $r,float $x,float $y,float $radius,array $color,float $progress=1): void
    {
        for($i=0;$i<24*$progress;$i++){$a=$i*M_PI/12-M_PI/2;$b=($i+1)*M_PI/12-M_PI/2;$r->drawLine((int)($x+cos($a)*$radius),(int)($y+sin($a)*$radius),(int)($x+cos($b)*$radius),(int)($y+sin($b)*$radius),$color);}
    }
}
