<?php

namespace App\Game;

/** Factory for generated maps that preserves BrushMap's public runtime contract. */
final class BrushMapFactory
{
    public static function fromGenerated(array $solids, array $spawnPoints, array $playerStart, float $playerYaw): BrushMap
    {
        $map = new BrushMap();
        $map->solids = $solids;
        $map->spawnPoints = $spawnPoints;
        $map->playerStart = $playerStart;
        $map->playerYaw = $playerYaw;
        return $map;
    }
}
