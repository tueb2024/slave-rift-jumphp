<?php

namespace App\Game;

/** Small reusable library of readable FPS modules with explicit connector roles. */
final class ModuleLibrary
{
    private const DOOR_WIDTH = 2.0;
    private const DOOR_HEIGHT = 2.4;
    private const WALL_HEIGHT = 4.0;
    private const SPAWN_Y = 0.75;

    public static function get(string $type): RoomModule
    {
        return match ($type) {
            LevelGraph::START => self::start(),
            LevelGraph::CORRIDOR_SHORT => self::corridor(4.0, LevelGraph::CORRIDOR_SHORT),
            LevelGraph::CORRIDOR_LONG => self::corridor(7.0, LevelGraph::CORRIDOR_LONG),
            LevelGraph::TURN => self::turn(),
            LevelGraph::SMALL_ROOM => self::room(5.0, 4.0, LevelGraph::SMALL_ROOM),
            LevelGraph::LARGE_ROOM => self::room(8.0, 5.0, LevelGraph::LARGE_ROOM),
            LevelGraph::JUNCTION => self::junction(),
            LevelGraph::BONUS => self::bonus(),
            LevelGraph::BOSS_ROOM => self::bossRoom(),
            default => throw new \InvalidArgumentException('Unknown module ' . $type),
        };
    }

    private static function start(): RoomModule
    {
        $m = new RoomModule(LevelGraph::START);
        self::floorCeiling($m, 4.0, 4.0, self::WALL_HEIGHT);
        self::solidNorth($m, 4.0, 4.0, self::WALL_HEIGHT);
        self::doorSouth($m, 4.0, 4.0, self::WALL_HEIGHT);
        self::solidWestEast($m, 4.0, 4.0, self::WALL_HEIGHT);
        $m->addConnector('main_exit', 0.0, 0.0, 4.0, 0.0, 0.0, 1.0, self::DOOR_WIDTH, self::DOOR_HEIGHT);
        $m->addSpawnAnchor(0.0, self::SPAWN_Y, 2.0, ['start', 'player']);
        return $m;
    }

    private static function corridor(float $halfDepth, string $type): RoomModule
    {
        $m = new RoomModule($type); $halfWidth = 1.5; $h = self::WALL_HEIGHT;
        self::floorCeiling($m, $halfWidth, $halfDepth, $h);
        self::doorNorth($m, $halfWidth, $halfDepth, $h);
        self::doorSouth($m, $halfWidth, $halfDepth, $h);
        self::solidWestEast($m, $halfWidth, $halfDepth, $h);
        $m->addConnector('entry', 0.0, 0.0, -$halfDepth, 0.0, 0.0, -1.0, self::DOOR_WIDTH, self::DOOR_HEIGHT);
        $m->addConnector('main_exit', 0.0, 0.0, $halfDepth, 0.0, 0.0, 1.0, self::DOOR_WIDTH, self::DOOR_HEIGHT);
        $m->addSpawnAnchor(0.0, self::SPAWN_Y, -$halfDepth * 0.45, ['corridor']);
        $m->addSpawnAnchor(0.0, self::SPAWN_Y, $halfDepth * 0.45, ['corridor']);
        return $m;
    }

    private static function turn(): RoomModule
    {
        $m = new RoomModule(LevelGraph::TURN); $half = 3.0; $h = self::WALL_HEIGHT;
        self::floorCeiling($m, $half, $half, $h);
        self::doorNorth($m, $half, $half, $h);
        self::doorEast($m, $half, $half, $h);
        self::solidSouth($m, $half, $half, $h);
        self::solidWest($m, $half, $half, $h);
        $m->addConnector('entry', 0.0, 0.0, -$half, 0.0, 0.0, -1.0, self::DOOR_WIDTH, self::DOOR_HEIGHT);
        $m->addConnector('main_exit', $half, 0.0, 0.0, 1.0, 0.0, 0.0, self::DOOR_WIDTH, self::DOOR_HEIGHT);
        $m->addSpawnAnchor(0.0, self::SPAWN_Y, 0.0, ['turn']);
        return $m;
    }

    private static function room(float $half, float $height, string $type): RoomModule
    {
        $m = new RoomModule($type);
        self::floorCeiling($m, $half, $half, $height);
        self::doorNorth($m, $half, $half, $height);
        self::doorSouth($m, $half, $half, $height);
        self::solidWestEast($m, $half, $half, $height);
        $m->addConnector('entry', 0.0, 0.0, -$half, 0.0, 0.0, -1.0, self::DOOR_WIDTH, self::DOOR_HEIGHT);
        $m->addConnector('main_exit', 0.0, 0.0, $half, 0.0, 0.0, 1.0, self::DOOR_WIDTH, self::DOOR_HEIGHT);
        $m->addSpawnAnchor(-$half * 0.45, self::SPAWN_Y, -$half * 0.35, ['room', 'corner']);
        $m->addSpawnAnchor($half * 0.45, self::SPAWN_Y, $half * 0.35, ['room', 'corner']);
        $m->addPickupAnchor($half * 0.35, 0.25, -$half * 0.35, ['bonus']);
        return $m;
    }

    private static function junction(): RoomModule
    {
        $m = new RoomModule(LevelGraph::JUNCTION); $half = 6.0; $h = self::WALL_HEIGHT;
        self::floorCeiling($m, $half, $half, $h);
        self::doorNorth($m, $half, $half, $h);
        self::doorSouth($m, $half, $half, $h);
        self::doorEast($m, $half, $half, $h);
        self::solidWest($m, $half, $half, $h);
        $m->addConnector('entry', 0.0, 0.0, -$half, 0.0, 0.0, -1.0, self::DOOR_WIDTH, self::DOOR_HEIGHT);
        $m->addConnector('main_exit', 0.0, 0.0, $half, 0.0, 0.0, 1.0, self::DOOR_WIDTH, self::DOOR_HEIGHT);
        $m->addConnector('branch_exit', $half, 0.0, 0.0, 1.0, 0.0, 0.0, self::DOOR_WIDTH, self::DOOR_HEIGHT);
        $m->addSpawnAnchor(0.0, self::SPAWN_Y, 0.0, ['junction']);
        return $m;
    }

    private static function bonus(): RoomModule
    {
        $m = new RoomModule(LevelGraph::BONUS); $half = 3.0; $h = self::WALL_HEIGHT;
        self::floorCeiling($m, $half, $half, $h, 'crate');
        self::solidNorth($m, $half, $half, $h);
        self::solidSouth($m, $half, $half, $h);
        self::solidEast($m, $half, $half, $h);
        self::doorWest($m, $half, $half, $h);
        $m->addConnector('entry', -$half, 0.0, 0.0, -1.0, 0.0, 0.0, self::DOOR_WIDTH, self::DOOR_HEIGHT);
        $m->addPickupAnchor(0.0, 0.25, 0.0, ['bonus', 'rare']);
        return $m;
    }

    private static function bossRoom(): RoomModule
    {
        $m = new RoomModule(LevelGraph::BOSS_ROOM); $half = 10.0; $h = 6.0;
        self::floorCeiling($m, $half, $half, $h);
        self::doorNorth($m, $half, $half, $h);
        self::solidSouth($m, $half, $half, $h);
        self::solidWestEast($m, $half, $half, $h);
        $m->addConnector('entry', 0.0, 0.0, -$half, 0.0, 0.0, -1.0, self::DOOR_WIDTH, self::DOOR_HEIGHT);
        $m->addSpawnAnchor(0.0, self::SPAWN_Y, 0.0, ['boss']);
        $m->addPickupAnchor(0.0, 0.25, 6.0, ['treasure']);
        return $m;
    }

    private static function floorCeiling(RoomModule $m, float $hw, float $hd, float $h, string $floor = 'metal'): void
    {
        $m->addBox(-$hw, -1.0, -$hd, $hw, 0.0, $hd, $floor);
        $m->addBox(-$hw, $h, -$hd, $hw, $h + 0.2, $hd, 'ceil');
    }
    private static function doorNorth(RoomModule $m,float $hw,float $hd,float $h): void { $m->addWallWithDoor(-$hw,-$hd,$hw,-$hd+0.5,0,$h,0,self::DOOR_WIDTH,self::DOOR_HEIGHT,'x'); }
    private static function doorSouth(RoomModule $m,float $hw,float $hd,float $h): void { $m->addWallWithDoor(-$hw,$hd-0.5,$hw,$hd,0,$h,0,self::DOOR_WIDTH,self::DOOR_HEIGHT,'x'); }
    private static function doorEast(RoomModule $m,float $hw,float $hd,float $h): void { $m->addWallWithDoor($hw-0.5,-$hd,$hw,$hd,0,$h,0,self::DOOR_WIDTH,self::DOOR_HEIGHT,'z'); }
    private static function doorWest(RoomModule $m,float $hw,float $hd,float $h): void { $m->addWallWithDoor(-$hw,-$hd,-$hw+0.5,$hd,0,$h,0,self::DOOR_WIDTH,self::DOOR_HEIGHT,'z'); }
    private static function solidNorth(RoomModule $m,float $hw,float $hd,float $h): void { $m->addBox(-$hw,0,-$hd,$hw,$h,-$hd+0.5); }
    private static function solidSouth(RoomModule $m,float $hw,float $hd,float $h): void { $m->addBox(-$hw,0,$hd-0.5,$hw,$h,$hd); }
    private static function solidEast(RoomModule $m,float $hw,float $hd,float $h): void { $m->addBox($hw-0.5,0,-$hd,$hw,$h,$hd); }
    private static function solidWest(RoomModule $m,float $hw,float $hd,float $h): void { $m->addBox(-$hw,0,-$hd,-$hw+0.5,$h,$hd); }
    private static function solidWestEast(RoomModule $m,float $hw,float $hd,float $h): void { self::solidWest($m,$hw,$hd,$h); self::solidEast($m,$hw,$hd,$h); }
}
