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
    public static function foreground(Renderer $r,Sdl $s,Run $run): void
    {
        $w=$r->getWidth();$h=$r->getHeight();
        if($run->flameActive>0){$frame=2+(int)($run->time/.075)%4;if($run->flameActive<.2)$frame=6;
            // Keep the power ahead of the gun, with a center gap for aiming.
            self::sprite($r,$s,'flame',$frame,$w*.32,$h+15,$h*.85);
            self::sprite($r,$s,'flame',2+(int)($run->time/.09)%4,$w*.68,$h+15,$h*.75);
        }
        if($run->empFx>0)self::sprite($r,$s,'emp',min(7,(int)((.65-$run->empFx)/.65*8)),$w/2,$h*.8,$h*.8);
    }
    public static function ring(Renderer $r,float $x,float $y,float $radius,array $color,float $progress=1): void
    {
        for($i=0;$i<24*$progress;$i++){$a=$i*M_PI/12-M_PI/2;$b=($i+1)*M_PI/12-M_PI/2;$r->drawLine((int)($x+cos($a)*$radius),(int)($y+sin($a)*$radius),(int)($x+cos($b)*$radius),(int)($y+sin($b)*$radius),$color);}
    }
}
