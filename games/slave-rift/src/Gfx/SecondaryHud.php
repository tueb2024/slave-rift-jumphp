<?php
namespace App\Gfx;
use App\Game\Run;
use JumPHP\Renderer\Renderer;
use JumPHP\Renderer\Sdl;
use JumPHP\Renderer\TextDrawer;

/** Compact acquired-weapon rail. No gameplay is inferred from a texture. */
final class SecondaryHud
{
    public static function textures(): array
    {
        $out=[];foreach(['flame','rockets','pulse','sniper','artillery']as$id)foreach(['','-dim']as$suffix)
            $out['secondary_'.$id.($suffix?'_dim':'')]='assets/generated/weapons/'.match($id){'rockets'=>'bazooka',default=>$id}.'-hud'.$suffix.'.png';
        return $out;
    }

    public static function layout(array $inventory,int $width=320): array
    {
        $out=[];
        foreach(array_values($inventory)as$i=>$item){
            $item['x']=$width-56;$item['y']=26+$i*25;$item['width']=54;$item['height']=25;
            $item['texture']='secondary_'.$item['id'].($item['ready']||$item['active']?'':'_dim');
            $item['progress']=max(0,min(1,1-$item['remaining']/max(.01,$item['cooldown'])));$out[]=$item;
        }
        return $out;
    }

    public static function render(Renderer $r,Sdl $sdl,TextDrawer $font,Run $run,int $width): void
    {
        foreach(self::layout($run->secondaryInventory(),$width)as$item){
            $x=$item['x'];$y=$item['y'];$r->fillRect($x,$y,54,25,[6,9,15,225]);
            $texture=$sdl->getTexture($item['texture']);$r->copy($texture['texture'],[0,0,32,16],$x+2,$y+1,32,16);
            $color=$item['flash']?[255,245,170]:($item['active']?[255,156,70]:($item['ready']?[90,245,225]:[115,133,151]));
            $prefix=match($item['id']){'flame'=>'F','rockets'=>'R','sniper'=>'S','artillery'=>'A',default=>'I'};
            $font->draw($r,$prefix.$item['rank'],$x+51,$y+5,1,$color,'right');
            $r->fillRect($x+2,$y+20,48,3,[32,44,57]);$r->fillRect($x+2,$y+20,(int)round(48*$item['progress']),3,$color);
            if($item['flash']||$item['active'])$r->drawRect($x,$y,54,25,$color,1);
        }
    }
}
