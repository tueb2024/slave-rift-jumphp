<?php
namespace App\Game;

/** Static brush broad phase and bounded, radius-aware navigation through room portals.
 * Paths contain feet positions. No geometry or actor positions are ever changed here.
 */
final class Navigation
{
    public const CELL = 0.6;
    public const MAX_EXPANSIONS = 2048;
    private array $buckets = [];
    private array $rooms = [];
    private array $links = [];
    private array $roomRoutes = [];
    private array $jobs = [];
    private array $ready = [];
    private array $queue = [];
    public const EXPANSIONS_PER_FRAME = 96;
    public int $searches = 0;
    public int $expansions = 0;
    public int $failed = 0;
    public int $budgetExhausted = 0;

    public function __construct(private readonly BrushMap $map)
    {
        foreach ($map->solids as $id => $s) {
            for ($x=(int)floor($s['min'][0]/4);$x<=(int)floor($s['max'][0]/4);$x++)
                for ($z=(int)floor($s['min'][2]/4);$z<=(int)floor($s['max'][2]/4);$z++) $this->buckets["$x:$z"][]=$id;
        }
    }

    public function setRooms(array $rooms): void
    {
        $this->rooms=$rooms;$this->links=[];$this->roomRoutes=[];
        foreach($rooms as $i=>$a)foreach($rooms as $j=>$b){
            if($i===$j)continue;
            foreach($a['connectors'] as $ca)foreach($b['connectors'] as $cb){
                $p=$ca['position'];$q=$cb['position'];
                if(hypot($p[0]-$q[0],$p[2]-$q[2])<.05 && abs($p[1]-$q[1])<.05){
                    $this->links[$i][$j]=$p;
                }
            }
        }
    }

    private function solids(float $x,float $z,float $radius): array
    {
        $ids=[];
        for($ix=(int)floor(($x-$radius)/4);$ix<=(int)floor(($x+$radius)/4);$ix++)
            for($iz=(int)floor(($z-$radius)/4);$iz<=(int)floor(($z+$radius)/4);$iz++)
                foreach($this->buckets["$ix:$iz"]??[] as $id)$ids[$id]=true;
        return $ids;
    }

    public function collides(float $x,float $y,float $z,float $radius,float $half): bool
    {
        foreach($this->solids($x,$z,$radius) as $id=>$_){
            $s=$this->map->solids[$id];
            if($y+$half<=$s['min'][1]+.001||$y-$half>=$s['max'][1]-.001)continue;
            $dx=$x-max($s['min'][0],min($s['max'][0],$x));$dz=$z-max($s['min'][2],min($s['max'][2],$z));
            if($dx*$dx+$dz*$dz<$radius*$radius)return true;
        }
        return false;
    }

    public function support(float $x,float $z,float $radius,float $ceiling): ?float
    {
        $top=null;
        foreach($this->solids($x,$z,$radius) as $id=>$_){
            $s=$this->map->solids[$id];$h=$s['max'][1];
            if($h>$ceiling+.002||($top!==null&&$h<$top))continue;
            $dx=$x-max($s['min'][0],min($s['max'][0],$x));$dz=$z-max($s['min'][2],min($s['max'][2],$z));
            if($dx*$dx+$dz*$dz<$radius*$radius)$top=$h;
        }
        return $top;
    }

    /** Sweep with real support, step-up and drop limits; prevents corner cutting and floor shortcuts. */
    public function traverse(array $from,array $to,float $radius,float $half=.55): ?array
    {
        $n=max(1,(int)ceil(hypot($to[0]-$from[0],$to[2]-$from[2])/.2));$floor=$from[1];
        for($i=1;$i<=$n;$i++){
            $x=$from[0]+($to[0]-$from[0])*$i/$n;$z=$from[2]+($to[2]-$from[2])*$i/$n;
            $h=$this->support($x,$z,$radius,$floor+.58);
            if($h===null||$h<$floor-.8||$this->collides($x,$h+$half,$z,$radius,$half))return null;
            $floor=$h;
        }
        return [$to[0],$floor,$to[2]];
    }

    private function roomAt(array $p): ?int
    {
        $best=null;$score=INF;
        foreach($this->rooms as $id=>$r){$b=$r['bounds'];
            if($p[0]<$b['min'][0]-.1||$p[0]>$b['max'][0]+.1||$p[2]<$b['min'][2]-.1||$p[2]>$b['max'][2]+.1)continue;
            // Bounds include walls/ceilings; connectors give the actual floor range.
            $ys=array_column(array_column($r['connectors'],'position'),1);
            $d=max(min($ys)-$p[1],$p[1]-max($ys),0);
            if($d<$score){$score=$d;$best=$id;}
        }
        return $best;
    }

    private function portal(array $from,array $to): array
    {
        $a=$this->roomAt($from);$b=$this->roomAt($to);
        if($a===null||$b===null||$a===$b)return $to;
        $key="$a:$b";
        if(!array_key_exists($key,$this->roomRoutes)){
            $q=[$a];$previous=[$a=>null];
            for($i=0;$i<count($q)&&!array_key_exists($b,$previous);$i++)foreach($this->links[$q[$i]]??[] as $next=>$_){
                if(array_key_exists($next,$previous))continue;$previous[$next]=$q[$i];$q[]=$next;
            }
            $next=$b;
            if(!array_key_exists($b,$previous))$next=null;
            else while($previous[$next]!==$a)$next=$previous[$next];
            $this->roomRoutes[$key]=$next;
        }
        $next=$this->roomRoutes[$key];
        if($next===null)return $to;
        $p=$this->links[$a][$next];
        // Go beyond the shared doorway so room selection advances unambiguously.
        $r=$this->rooms[$next]['bounds'];$cx=($r['min'][0]+$r['max'][0])/2;$cz=($r['min'][2]+$r['max'][2])/2;
        $d=max(.001,hypot($cx-$p[0],$cz-$p[2]));
        return [$p[0]+($cx-$p[0])/$d*.9,$p[1],$p[2]+($cz-$p[2])/$d*.9];
    }

    /** Local A* toward the next portal; deterministic bounded search, never a partial unsafe shortcut. */
    public function path(array $from,array $to,float $radius,float $half=.55): array
    {
        $search=$this->search($from,$to,$radius,$half);
        foreach($search as $_) {}
        return $search->getReturn();
    }

    /** Enemies share a frame budget; expensive searches resume instead of blocking a frame. */
    public function request(int $id,array $from,array $to,float $radius): ?array
    {
        if(isset($this->jobs[$id]))return null;
        unset($this->ready[$id]);
        $g=$this->search($from,$to,$radius,.55);
        if(!$g->valid())return $g->getReturn();
        $this->jobs[$id]=$g;$this->queue[]=$id;
        return null;
    }

    public function poll(int $id): ?array
    {
        if(!array_key_exists($id,$this->ready))return null;
        $path=$this->ready[$id];unset($this->ready[$id]);return $path;
    }

    public function pump(array $liveIds): void
    {
        for($i=0;$i<self::EXPANSIONS_PER_FRAME&&$this->queue!==[];$i++){
            $id=array_shift($this->queue);
            if(!isset($liveIds[$id])){unset($this->jobs[$id],$this->ready[$id]);continue;}
            $g=$this->jobs[$id];$g->next();
            if($g->valid())$this->queue[]=$id;
            else{$this->ready[$id]=$g->getReturn();unset($this->jobs[$id]);}
        }
        foreach($this->ready as $id=>$_)if(!isset($liveIds[$id]))unset($this->ready[$id]);
    }

    private function search(array $from,array $to,float $radius,float $half): \Generator
    {
        $this->searches++;
        $goal=$this->portal($from,$to);
        // The player can fit closer to a wall than a guardian or brute. Aim for
        // reachable melee distance instead of searching forever for that exact point.
        if($this->collides($goal[0],$goal[1]+$half,$goal[2],$radius,$half)){
            $best=null;$distance=INF;
            for($i=0;$i<16;$i++){
                $a=$i*M_PI/8;$p=[$goal[0]+sin($a)*.5,$goal[1],$goal[2]+cos($a)*.5];
                $h=$this->support($p[0],$p[2],$radius,$goal[1]+.58);
                if($h===null||abs($h-$goal[1])>.8||$this->collides($p[0],$h+$half,$p[2],$radius,$half))continue;
                $p[1]=$h;
                if($this->traverse($p,$goal,.01,$half)===null)continue;
                $d=hypot($from[0]-$p[0],$from[2]-$p[2]);if($d<$distance){$distance=$d;$best=$p;}
            }
            if($best!==null)$goal=$best;
        }
        $direct=$this->traverse($from,$goal,$radius,$half);
        if($direct!==null && abs($direct[1]-$goal[1])<.81)return [$direct];
        $open=new \SplPriorityQueue();$open->setExtractFlags(\SplPriorityQueue::EXTR_DATA);
        $open->insert('start',0);$points=['start'=>$from];$cost=['start'=>0];$previous=[];$closed=[];
        $found=null;$count=0;
        while(!$open->isEmpty()&&$count<self::MAX_EXPANSIONS){
            $key=$open->extract();if(isset($closed[$key]))continue;$closed[$key]=true;$count++;$this->expansions++;yield null;$p=$points[$key];
            if(hypot($goal[0]-$p[0],$goal[2]-$p[2])<1.0){
                $end=$this->traverse($p,$goal,$radius,$half);
                if($end!==null&&abs($end[1]-$goal[1])<.81){$found=$key;break;}
            }
            foreach([[1,0],[-1,0],[0,1],[0,-1],[1,1],[1,-1],[-1,1],[-1,-1]] as [$dx,$dz]){
                $ix=(int)round($p[0]/self::CELL)+$dx;$iz=(int)round($p[2]/self::CELL)+$dz;
                $n=$this->traverse($p,[$ix*self::CELL,$p[1],$iz*self::CELL],$radius,$half);if($n===null)continue;
                $nk="$ix:$iz:".(int)round($n[1]*100);if(isset($closed[$nk]))continue;
                $g=$cost[$key]+hypot($n[0]-$p[0],$n[2]-$p[2]);
                if($g>=($cost[$nk]??INF))continue;
                $points[$nk]=$n;$cost[$nk]=$g;$previous[$nk]=$key;
                $open->insert($nk,-($g+hypot($goal[0]-$n[0],$goal[2]-$n[2])));
            }
        }
        if($found===null){$this->failed++;if($count===self::MAX_EXPANSIONS)$this->budgetExhausted++;return [];}
        $path=[$end];
        while($found!=='start'){$path[]=$points[$found];$found=$previous[$found];}
        return array_reverse($path);
    }
}
