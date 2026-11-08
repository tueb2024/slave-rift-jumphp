<?php

namespace App\Game;

/** One closed room, shared by the arcade mode and CPU stress measurements. */
final class HordeArena
{
    public static function build(): BrushMap
    {
        $room = new RoomModule('HORDE');
        $room->addBox(-20, -1, -20, 20, 0, 20, 'metal');
        $room->addBox(-20, 5, -20, 20, 5.3, 20, 'ceil');
        $room->addBox(-20, 0, -20, -19.5, 5, 20);
        $room->addWallWithDoor(19.5,-20,20,20,0,5,0,3.0,3.2,'z','stone');
        // A real side room shields the player from arena barrages.
        $room->addBox(19.5,-1,-4,28,0,4,'metal');
        $room->addBox(19.5,5,-4,28,5.3,4,'ceil');
        $room->addBox(27.5,0,-4,28,5,4);
        $room->addBox(19.5,0,-4,28,5,-3.5);
        $room->addBox(19.5,0,3.5,28,5,4);
        $room->addBox(-20, 0, -20, 20, 5, -19.5);
        $room->addBox(-20, 0, 19.5, 20, 5, 20);
        $points = [[0.0, 0.75, 0.0]];
        foreach ([-16.0, -8.0, 0.0, 8.0, 16.0] as $x) foreach ([-16.0, 16.0] as $z) $points[] = [$x, 0.75, $z];
        foreach ([-8.0, 0.0, 8.0] as $z) foreach ([-16.0, 16.0] as $x) $points[] = [$x, 0.75, $z];
        return BrushMapFactory::fromGenerated($room->solids, $points, $points[0], 0.0);
    }
}
