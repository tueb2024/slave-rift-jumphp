<?php

namespace App\Tests;

use App\Game\GameMode;
use App\Game\MenuFlow;
use PHPUnit\Framework\TestCase;

final class MenuFlowTest extends TestCase
{
    public function testMainMenuAndAllFourDailyChoicesSharePointerAndKeyboardActions(): void
    {
        foreach (['normal','daily','horde','controls'] as $i=>$expected) {
            $menu = new MenuFlow(); self::assertSame('main',$menu->screen);
            [$x,$y,$w,$h] = $menu->rect($i);
            self::assertSame($i,$menu->hit($x+$w/2,$y+$h/2));
            self::assertSame($expected,$menu->activate($menu->hit($x+$w/2,$y+$h/2)));
            self::assertSame(match ($expected) {'daily'=>'daily','controls'=>'controls',default=>'play'},$menu->screen);
        }
        $menu=new MenuFlow();$menu->activate(1);
        self::assertSame(['daily_start','back'],array_keys($menu->items()));
        self::assertSame('daily_start',$menu->activate());
        self::assertSame(GameMode::DAILY,$menu->mode);self::assertSame('play',$menu->screen);

    }

    public function testPauseAndTerminalMenusReturnWithoutStartingAnotherRun(): void
    {
        foreach (['pause','dead','won'] as $screen) foreach ([GameMode::NORMAL,GameMode::DAILY,GameMode::HORDE] as $mode) {
            $menu = new MenuFlow(); $menu->mode=$mode; $menu->open($screen);
            $index = array_search('menu',array_keys($menu->items()),true);
            self::assertIsInt($index);
            self::assertSame('menu',$menu->activate($index));
            self::assertSame('main',$menu->screen);
        }
    }

    public function testCampaignTerminalMenuOffersNewCampaignWhileLegacyFixtureAllowsReplay(): void
    {
        $menu = new MenuFlow();
        $menu->campaign = true;
        $menu->open('dead');
        self::assertSame(['new','menu'],array_keys($menu->items()));
        $menu->campaign = false;
        self::assertSame(['replay','new','menu'],array_keys($menu->items()));
    }

    public function testEveryChoiceHasANonoverlappingTouchTarget(): void
    {
        foreach (['main','daily','controls','pause','dead','won'] as $screen) {
            $menu = new MenuFlow(); $menu->open($screen);
            foreach (array_values($menu->items()) as $i=>$_) {
                [$x,$y,$w,$h] = $menu->rect($i);
                self::assertGreaterThanOrEqual(20,$h);
                self::assertLessThanOrEqual(166,$y+$h);
                self::assertSame($i,$menu->hit($x+$w/2,$y+$h/2));
            }
        }
    }
}
