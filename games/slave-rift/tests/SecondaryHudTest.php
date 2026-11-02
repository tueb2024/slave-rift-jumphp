<?php
namespace App\Tests;
use App\Game\Run;
use App\Game\HordeArena;
use App\Gfx\SecondaryHud;
use PHPUnit\Framework\TestCase;

final class SecondaryHudTest extends TestCase
{
    public function testOnlyAcquiredWeaponsHaveDistinctSpritesAndCooldownFeedback(): void
    {
        $run=new Run(HordeArena::build(),42);
        self::assertSame([],SecondaryHud::layout($run->secondaryInventory()));
        $run->flameRank=1;$run->rocketRank=2;$run->pulseUnlocked=true;
        $items=SecondaryHud::layout($run->secondaryInventory());
        self::assertSame(['flame','rockets','pulse'],array_column($items,'id'));
        self::assertSame([26,52,78],array_column($items,'y'));
        foreach($items as$item){
            self::assertSame(264,$item['x']);
            self::assertStringEndsWith('_dim',$item['texture']);
            self::assertGreaterThanOrEqual(0,$item['progress']);
            self::assertLessThanOrEqual(1,$item['progress']);
        }
        foreach(SecondaryHud::textures()as$path){
            self::assertFileExists(dirname(__DIR__).'/'.$path);
            self::assertSame([32,16],array_slice(getimagesize(dirname(__DIR__).'/'.$path),0,2));
        }
        (new \ReflectionProperty($run,'rocketClock'))->setValue($run,0.0);
        self::assertSame('secondary_rockets',SecondaryHud::layout($run->secondaryInventory())[1]['texture']);
    }
}
