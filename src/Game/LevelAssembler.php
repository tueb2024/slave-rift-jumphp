<?php

namespace App\Game;

/** Materialises a LevelGraph as a real BrushMap plus room/spawn metadata. */
final class LevelAssembler
{
    private const DIRECTION_ORDER = ['north' => 0, 'east' => 1, 'south' => 2, 'west' => 3];

    public function assemble(LevelGraph $graph): AssembledLevel
    {
        $solids = [];
        $spawnPoints = [];
        $spawnMeta = [];
        $pickupMeta = [];
        $rooms = [];
        $placedById = [];
        $treasureAnchor = null;
        $bossAnchor = null;
        $playerStart = [0.0, 0.75, 0.0];
        $playerYaw = 0.0;
        $previousExit = null;

        foreach ($graph->mainPath() as $index => $node) {
            $module = ModuleLibrary::get($node['type']);
            if ($index === 0) {
                $placed = $module;
            } else {
                if ($previousExit === null) throw new \RuntimeException('Missing main exit before ' . $node['type']);
                $placed = $this->placeAgainst($module, 'entry', $previousExit);
            }
            $placedById[$node['id']] = $placed;
            $this->appendPlaced($node, $placed, $solids, $spawnPoints, $spawnMeta, $pickupMeta, $rooms, $treasureAnchor, $bossAnchor);
            if ($index === 0 && $placed->spawnAnchors !== []) {
                $first = $placed->spawnAnchors[0];
                $playerStart = [$first['x'], $first['y'], $first['z']];
            }
            $previousExit = $placed->connector('main_exit');
        }

        foreach ($graph->nodes as $node) {
            if ($node['mainPath'] || $node['type'] !== LevelGraph::BONUS || $node['parent'] === null) continue;
            $parent = $placedById[$node['parent']] ?? null;
            if (!$parent instanceof RoomModule) continue;
            $branchExit = $parent->connector('branch_exit');
            if ($branchExit === null) continue;
            $placed = $this->placeAgainst(ModuleLibrary::get(LevelGraph::BONUS), 'entry', $branchExit);
            $placedById[$node['id']] = $placed;
            $this->appendPlaced($node, $placed, $solids, $spawnPoints, $spawnMeta, $pickupMeta, $rooms, $treasureAnchor, $bossAnchor);
        }

        if ($treasureAnchor === null || $bossAnchor === null) {
            throw new \RuntimeException('Generated level is missing its terminal boss/treasure anchors.');
        }

        $map = BrushMapFactory::fromGenerated($solids, $spawnPoints, $playerStart, $playerYaw);
        return new AssembledLevel($map, $spawnMeta, $pickupMeta, $rooms, $treasureAnchor, $bossAnchor, $graph->seed);
    }

    private function placeAgainst(RoomModule $module, string $entryRole, array $parentExit): RoomModule
    {
        $entry = $module->connector($entryRole);
        if ($entry === null) throw new \RuntimeException($module->type . ' has no ' . $entryRole . ' connector');
        $targetNormal = [-$parentExit['normal'][0], -$parentExit['normal'][1], -$parentExit['normal'][2]];
        $rotation = $this->rotationToAlign($entry['normal'], $targetNormal);
        $rotated = $module->transform($rotation, 0.0, 0.0, 0.0);
        $rotatedEntry = $rotated->connector($entryRole);
        if ($rotatedEntry === null) throw new \RuntimeException('Connector lost while rotating ' . $module->type);
        $tx = $parentExit['position'][0] - $rotatedEntry['position'][0];
        $ty = $parentExit['position'][1] - $rotatedEntry['position'][1];
        $tz = $parentExit['position'][2] - $rotatedEntry['position'][2];
        return $rotated->transform(0, $tx, $ty, $tz);
    }

    private function appendPlaced(
        array $node,
        RoomModule $placed,
        array &$solids,
        array &$spawnPoints,
        array &$spawnMeta,
        array &$pickupMeta,
        array &$rooms,
        ?array &$treasureAnchor,
        ?array &$bossAnchor
    ): void {
        foreach ($placed->solids as $solid) $solids[] = $solid;
        foreach ($placed->spawnAnchors as $anchor) {
            $spawnPoints[] = [$anchor['x'], $anchor['y'], $anchor['z']];
            $meta = [
                'x' => $anchor['x'], 'y' => $anchor['y'], 'z' => $anchor['z'], 'tags' => $anchor['tags'],
                'roomId' => (int)$node['id'], 'roomType' => $node['type'],
            ];
            $spawnMeta[] = $meta;
            if (in_array('boss', $anchor['tags'], true)) $bossAnchor = $meta;
        }
        foreach ($placed->pickupAnchors as $anchor) {
            $meta = [
                'x' => $anchor['x'], 'y' => $anchor['y'], 'z' => $anchor['z'], 'tags' => $anchor['tags'],
                'roomId' => (int)$node['id'], 'roomType' => $node['type'],
            ];
            $pickupMeta[] = $meta;
            if (in_array('treasure', $anchor['tags'], true)) $treasureAnchor = $meta;
        }
        $rooms[] = [
            'id' => (int)$node['id'], 'type' => $node['type'], 'mainPath' => (bool)$node['mainPath'],
            'depth' => (int)$node['depth'], 'bounds' => $placed->bounds(), 'connectors' => $placed->connectors,
        ];
    }

    private function rotationToAlign(array $current, array $target): int
    {
        $from = self::DIRECTION_ORDER[$this->normalToDirection($current)];
        $to = self::DIRECTION_ORDER[$this->normalToDirection($target)];
        return (($to - $from + 4) % 4) * 90;
    }

    private function normalToDirection(array $normal): string
    {
        if (abs($normal[0]) >= abs($normal[2])) return $normal[0] >= 0 ? 'east' : 'west';
        return $normal[2] >= 0 ? 'south' : 'north';
    }
}
