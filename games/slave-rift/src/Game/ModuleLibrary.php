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
            LevelGraph::CORRIDOR_SHORT => self::corridor(4.0, LevelGraph::CORRIDOR_SHORT, 'metal'),
            LevelGraph::CORRIDOR_LONG => self::corridor(7.0, LevelGraph::CORRIDOR_LONG, 'crate'),
            LevelGraph::TURN => self::turn(),
            LevelGraph::SMALL_ROOM => self::room(5.0, 4.0, LevelGraph::SMALL_ROOM, 'crate'),
            LevelGraph::LARGE_ROOM => self::room(8.0, 5.0, LevelGraph::LARGE_ROOM, 'metal'),
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
        self::solidNorth($m, 4.0, 4.0, self::WALL_HEIGHT, 'stone');
        self::doorSouth($m, 4.0, 4.0, self::WALL_HEIGHT, 'stone');
        self::solidWestEast($m, 4.0, 4.0, self::WALL_HEIGHT, 'stone');
        $m->addConnector('main_exit', 0.0, 0.0, 4.0, 0.0, 0.0, 1.0, self::DOOR_WIDTH, self::DOOR_HEIGHT);
        $m->addSpawnAnchor(0.0, self::SPAWN_Y, 2.0, ['start', 'player']);
        // Visible immediately from the start: beside the first doorway, at eye height.
        $m->addDecal('poster3615', -2.35, 1.55, 3.485, 0.0, 0.0, -1.0, 1.65, 2.25);
        return $m;
    }

    private static function corridor(float $halfDepth, string $type, string $wall): RoomModule
    {
        $m = new RoomModule($type); $halfWidth = 1.5; $h = self::WALL_HEIGHT;
        self::floorCeiling($m, $halfWidth, $halfDepth, $h);
        self::doorNorth($m, $halfWidth, $halfDepth, $h, $wall);
        self::doorSouth($m, $halfWidth, $halfDepth, $h, $wall);
        self::solidWestEast($m, $halfWidth, $halfDepth, $h, $wall);
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
        self::doorNorth($m, $half, $half, $h, 'stone');
        self::doorEast($m, $half, $half, $h, 'stone');
        self::solidSouth($m, $half, $half, $h, 'stone');
        self::solidWest($m, $half, $half, $h, 'stone');
        $m->addConnector('entry', 0.0, 0.0, -$half, 0.0, 0.0, -1.0, self::DOOR_WIDTH, self::DOOR_HEIGHT);
        $m->addConnector('main_exit', $half, 0.0, 0.0, 1.0, 0.0, 0.0, self::DOOR_WIDTH, self::DOOR_HEIGHT);
        $m->addSpawnAnchor(0.0, self::SPAWN_Y, 0.0, ['turn']);
        return $m;
    }

    private static function room(float $half, float $height, string $type, string $wall): RoomModule
    {
        $m = new RoomModule($type);
        self::floorCeiling($m, $half, $half, $height);
        self::doorNorth($m, $half, $half, $height, $wall);
        self::doorSouth($m, $half, $half, $height, $wall);
        self::solidWestEast($m, $half, $half, $height, $wall);
        $m->addConnector('entry', 0.0, 0.0, -$half, 0.0, 0.0, -1.0, self::DOOR_WIDTH, self::DOOR_HEIGHT);
        $m->addConnector('main_exit', 0.0, 0.0, $half, 0.0, 0.0, 1.0, self::DOOR_WIDTH, self::DOOR_HEIGHT);
        $m->addSpawnAnchor(-$half * 0.45, self::SPAWN_Y, -$half * 0.35, ['room', 'corner']);
        $m->addSpawnAnchor($half * 0.45, self::SPAWN_Y, $half * 0.35, ['room', 'corner']);
        $m->addPickupAnchor($half * 0.35, 0.25, -$half * 0.35, ['bonus']);
        if ($type === LevelGraph::LARGE_ROOM) {
            $m->addDecal('poster3615', $half - 0.515, 1.65, 1.2, -1.0, 0.0, 0.0, 1.85, 2.45);
        }
        return $m;
    }

    private static function junction(): RoomModule
    {
        $m = new RoomModule(LevelGraph::JUNCTION); $half = 6.0; $h = self::WALL_HEIGHT;
        self::floorCeiling($m, $half, $half, $h);
        self::doorNorth($m, $half, $half, $h, 'stone');
        self::doorSouth($m, $half, $half, $h, 'stone');
        self::doorEast($m, $half, $half, $h, 'stone');
        self::solidWest($m, $half, $half, $h, 'stone');
        $m->addConnector('entry', 0.0, 0.0, -$half, 0.0, 0.0, -1.0, self::DOOR_WIDTH, self::DOOR_HEIGHT);
        $m->addConnector('main_exit', 0.0, 0.0, $half, 0.0, 0.0, 1.0, self::DOOR_WIDTH, self::DOOR_HEIGHT);
        $m->addConnector('branch_exit', $half, 0.0, 0.0, 1.0, 0.0, 0.0, self::DOOR_WIDTH, self::DOOR_HEIGHT);
        $m->addSpawnAnchor(0.0, self::SPAWN_Y, 0.0, ['junction']);
        return $m;
    }

    private static function bonus(): RoomModule
    {
        $m = new RoomModule(LevelGraph::BONUS); $half = 3.0; $h = self::WALL_HEIGHT;
        self::floorCeiling($m, $half, $half, $h);
        self::solidNorth($m, $half, $half, $h, 'crate');
        self::solidSouth($m, $half, $half, $h, 'crate');
        self::solidEast($m, $half, $half, $h, 'crate');
        self::doorWest($m, $half, $half, $h, 'crate');
        $m->addConnector('entry', -$half, 0.0, 0.0, -1.0, 0.0, 0.0, self::DOOR_WIDTH, self::DOOR_HEIGHT);
        $m->addPickupAnchor(0.0, 0.25, 0.0, ['bonus', 'rare']);
        $m->addDecal('poster3615', 0.75, 1.55, $half - 0.515, 0.0, 0.0, -1.0, 1.55, 2.1);
        return $m;
    }

    private static function bossRoom(): RoomModule
    {
        $m = new RoomModule(LevelGraph::BOSS_ROOM); $half = 10.0; $h = 6.0;
        self::floorCeiling($m, $half, $half, $h);
        self::doorNorth($m, $half, $half, $h, 'stone');
        self::solidSouth($m, $half, $half, $h, 'stone');
        self::solidWestEast($m, $half, $half, $h, 'stone');
        $m->addConnector('entry', 0.0, 0.0, -$half, 0.0, 0.0, -1.0, self::DOOR_WIDTH, self::DOOR_HEIGHT);
        $m->addSpawnAnchor(0.0, self::SPAWN_Y, 0.0, ['boss']);
        $m->addPickupAnchor(0.0, 0.25, 6.0, ['treasure']);
        return $m;
    }

    private static function floorCeiling(RoomModule $m, float $hw, float $hd, float $h): void
    {
        // The floor deliberately stays uniform while wall materials change by room.
        $m->addBox(-$hw, -1.0, -$hd, $hw, 0.0, $hd, 'metal');
        $m->addBox(-$hw, $h, -$hd, $hw, $h + 0.2, $hd, 'ceil');
    }
    private static function doorNorth(RoomModule $m,float $hw,float $hd,float $h,string $mat='stone'): void { $m->addWallWithDoor(-$hw,-$hd,$hw,-$hd+0.5,0,$h,0,self::DOOR_WIDTH,self::DOOR_HEIGHT,'x',$mat); }
    private static function doorSouth(RoomModule $m,float $hw,float $hd,float $h,string $mat='stone'): void { $m->addWallWithDoor(-$hw,$hd-0.5,$hw,$hd,0,$h,0,self::DOOR_WIDTH,self::DOOR_HEIGHT,'x',$mat); }
    private static function doorEast(RoomModule $m,float $hw,float $hd,float $h,string $mat='stone'): void { $m->addWallWithDoor($hw-0.5,-$hd,$hw,$hd,0,$h,0,self::DOOR_WIDTH,self::DOOR_HEIGHT,'z',$mat); }
    private static function doorWest(RoomModule $m,float $hw,float $hd,float $h,string $mat='stone'): void { $m->addWallWithDoor(-$hw,-$hd,-$hw+0.5,$hd,0,$h,0,self::DOOR_WIDTH,self::DOOR_HEIGHT,'z',$mat); }
    private static function solidNorth(RoomModule $m,float $hw,float $hd,float $h,string $mat='stone'): void { $m->addBox(-$hw,0,-$hd,$hw,$h,-$hd+0.5,$mat); }
    private static function solidSouth(RoomModule $m,float $hw,float $hd,float $h,string $mat='stone'): void { $m->addBox(-$hw,0,$hd-0.5,$hw,$h,$hd,$mat); }
    private static function solidEast(RoomModule $m,float $hw,float $hd,float $h,string $mat='stone'): void { $m->addBox($hw-0.5,0,-$hd,$hw,$h,$hd,$mat); }
    private static function solidWest(RoomModule $m,float $hw,float $hd,float $h,string $mat='stone'): void { $m->addBox(-$hw,0,-$hd,-$hw+0.5,$h,$hd,$mat); }
    private static function solidWestEast(RoomModule $m,float $hw,float $hd,float $h,string $mat='stone'): void { self::solidWest($m,$hw,$hd,$h,$mat); self::solidEast($m,$hw,$hd,$h,$mat); }
}
