<?php

namespace App\Game;

/** Reusable FPS modules with explicit connectors, vertical transitions and dense wall dressing. */
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
            LevelGraph::STAIR_UP => self::stair(true),
            LevelGraph::STAIR_DOWN => self::stair(false),
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
        $m->addSpawnAnchor(0.0, self::SPAWN_Y, -1.0, ['start', 'player']);
        // Immediate visual signature: one Minitel troll and one deliberately banal ad slot.
        $m->addDecal('poster3615', -2.10, 1.55, 3.485, 0.0, 0.0, -1.0, 1.65, 2.25);
        $m->addDecal('posterAd', 2.05, 1.55, 3.485, 0.0, 0.0, -1.0, 1.65, 2.25);
        $m->addDecal('posterAd', -3.485, 1.40, -1.0, 1.0, 0.0, 0.0, 1.45, 1.95);
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
        // Corridors should never be visually empty: alternating ads on both walls.
        $m->addDecal('poster3615', -0.985, 1.55, -$halfDepth * 0.35, 1.0, 0.0, 0.0, 1.20, 1.65);
        $m->addDecal('posterAd', 0.985, 1.50, $halfDepth * 0.28, -1.0, 0.0, 0.0, 1.20, 1.65);
        if ($halfDepth >= 6.0) {
            $m->addDecal('posterAd', -0.985, 1.45, $halfDepth * 0.55, 1.0, 0.0, 0.0, 1.10, 1.50);
            $m->addDecal('poster3615', 0.985, 1.55, -$halfDepth * 0.58, -1.0, 0.0, 0.0, 1.15, 1.60);
        }
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
        $m->addDecal('posterAd', -$half + 0.515, 1.45, 0.45, 1.0, 0.0, 0.0, 1.45, 1.95);
        $m->addDecal('poster3615', -0.75, 1.55, $half - 0.515, 0.0, 0.0, -1.0, 1.45, 1.95);
        return $m;
    }

    private static function stair(bool $up): RoomModule
    {
        $m = new RoomModule($up ? LevelGraph::STAIR_UP : LevelGraph::STAIR_DOWN);
        $halfWidth = 2.4; $halfDepth = 6.0; $h = 4.2;
        $steps = 6; $stepRise = 0.45; $totalRise = $steps * $stepRise; $segment = ($halfDepth * 2.0) / $steps;
        $bottom = -$totalRise - 1.0;

        // Side walls stay axis-aligned; stair treads are real collision boxes. Each
        // 0.45 rise is below Run's 0.58 automatic step-up limit.
        $m->addBox(-$halfWidth, $bottom, -$halfDepth, -$halfWidth + 0.5, $h + $totalRise, $halfDepth, 'stone');
        $m->addBox($halfWidth - 0.5, $bottom, -$halfDepth, $halfWidth, $h + $totalRise, $halfDepth, 'stone');
        for ($i = 0; $i < $steps; $i++) {
            $z1 = -$halfDepth + $i * $segment;
            $z2 = $z1 + $segment;
            $top = ($i + 1) * $stepRise * ($up ? 1.0 : -1.0);
            $m->addBox(-$halfWidth, $bottom, $z1, $halfWidth, $top, $z2, 'metal');
        }
        $ceilingY = $h + ($up ? $totalRise : 0.0);
        $m->addBox(-$halfWidth, $ceilingY, -$halfDepth, $halfWidth, $ceilingY + 0.2, $halfDepth, 'ceil');

        $exitY = $up ? $totalRise : -$totalRise;
        $m->addConnector('entry', 0.0, 0.0, -$halfDepth, 0.0, 0.0, -1.0, self::DOOR_WIDTH, self::DOOR_HEIGHT);
        $m->addConnector('main_exit', 0.0, $exitY, $halfDepth, 0.0, 0.0, 1.0, self::DOOR_WIDTH, self::DOOR_HEIGHT);
        $m->addSpawnAnchor(0.0, ($up ? $stepRise : -$stepRise) + self::SPAWN_Y, -4.0, ['stairs', 'lower']);
        $m->addSpawnAnchor(0.0, $exitY + self::SPAWN_Y, 4.6, ['stairs', 'upper']);
        $m->addDecal('poster3615', -$halfWidth + 0.515, 1.75, -1.7, 1.0, 0.0, 0.0, 1.35, 1.85);
        $m->addDecal('posterAd', $halfWidth - 0.515, 2.15 + ($up ? 0.8 : -0.4), 1.5, -1.0, 0.0, 0.0, 1.45, 1.95);
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
        $m->addSpawnAnchor($half * 0.15, self::SPAWN_Y, 0.0, ['room', 'centre']);
        $m->addPickupAnchor($half * 0.35, 0.25, -$half * 0.35, ['bonus']);
        $m->addDecal('posterAd', -$half + 0.515, 1.55, -$half * 0.25, 1.0, 0.0, 0.0, 1.55, 2.05);
        $m->addDecal('poster3615', $half - 0.515, 1.65, $half * 0.15, -1.0, 0.0, 0.0, 1.85, 2.45);
        $m->addDecal('posterAd', -$half * 0.38, 1.50, -$half + 0.515, 0.0, 0.0, 1.0, 1.50, 2.00);
        if ($type === LevelGraph::LARGE_ROOM) {
            $m->addDecal('poster3615', $half * 0.48, 1.70, $half - 0.515, 0.0, 0.0, -1.0, 1.90, 2.50);
            $m->addDecal('posterAd', -$half * 0.52, 2.35, $half - 0.515, 0.0, 0.0, -1.0, 1.70, 2.15);
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
        $m->addSpawnAnchor(-3.2, self::SPAWN_Y, 2.6, ['junction', 'corner']);
        $m->addDecal('poster3615', -$half + 0.515, 1.65, -2.0, 1.0, 0.0, 0.0, 1.85, 2.45);
        $m->addDecal('posterAd', -$half + 0.515, 1.50, 2.3, 1.0, 0.0, 0.0, 1.65, 2.15);
        $m->addDecal('posterAd', -2.4, 1.55, $half - 0.515, 0.0, 0.0, -1.0, 1.60, 2.05);
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
        $m->addDecal('poster3615', 0.75, 1.55, $half - 0.515, 0.0, 0.0, -1.0, 1.55, 2.10);
        $m->addDecal('posterAd', -0.95, 1.45, -$half + 0.515, 0.0, 0.0, 1.0, 1.45, 1.95);
        $m->addDecal('posterAd', $half - 0.515, 1.50, 0.65, -1.0, 0.0, 0.0, 1.35, 1.85);
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
        $m->addSpawnAnchor(-5.0, self::SPAWN_Y, -2.0, ['boss', 'wing']);
        $m->addSpawnAnchor(5.0, self::SPAWN_Y, -2.0, ['boss', 'wing']);
        $m->addPickupAnchor(0.0, 0.25, 6.0, ['treasure']);
        $m->addDecal('poster3615', -$half + 0.515, 2.0, -2.5, 1.0, 0.0, 0.0, 2.20, 2.80);
        $m->addDecal('posterAd', $half - 0.515, 2.0, -2.5, -1.0, 0.0, 0.0, 2.20, 2.80);
        $m->addDecal('posterAd', -3.6, 2.1, $half - 0.515, 0.0, 0.0, -1.0, 2.0, 2.6);
        $m->addDecal('poster3615', 3.6, 2.1, $half - 0.515, 0.0, 0.0, -1.0, 2.0, 2.6);
        return $m;
    }

    private static function floorCeiling(RoomModule $m, float $hw, float $hd, float $h): void
    {
        // Floors deliberately share one material while walls vary by room.
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
