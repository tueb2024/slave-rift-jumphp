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
        $decals = [];
        $rooms = [];
        $placedById = [];
        $treasureAnchor = null;
        $bossAnchor = null;
        $playerStart = [0.0, 0.75, 0.0];
        $playerYaw = 0.0;
        $previousExit = null;
        $turnNumber = 0;

        foreach ($graph->mainPath() as $index => $node) {
            $module = ModuleLibrary::get($node['type']);
            if ($node['type'] === LevelGraph::TURN && (++$turnNumber % 2) === 0) $module = $module->mirrorX();
            if ($index === 0) {
                $placed = $module;
            } else {
                if ($previousExit === null) throw new \RuntimeException('Missing main exit before ' . $node['type']);
                $placed = $this->placeAgainst($module, 'entry', $previousExit);
            }
            $placedById[$node['id']] = $placed;
            $this->appendPlaced($node, $placed, $solids, $spawnPoints, $spawnMeta, $pickupMeta, $decals, $rooms, $treasureAnchor, $bossAnchor);
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
            $bounds = $placed->bounds();
            $overlap = false;
            foreach ($rooms as $room) {
                if ($room['id'] === $node['parent']) continue;
                $b = $room['bounds'];
                if ($bounds['min'][0] < $b['max'][0] - 0.1 && $bounds['max'][0] > $b['min'][0] + 0.1
                    && $bounds['min'][2] < $b['max'][2] - 0.1 && $bounds['max'][2] > $b['min'][2] + 0.1) $overlap = true;
            }
            if ($overlap) continue;
            $placedById[$node['id']] = $placed;
            $this->appendPlaced($node, $placed, $solids, $spawnPoints, $spawnMeta, $pickupMeta, $decals, $rooms, $treasureAnchor, $bossAnchor);
        }

        // A junction may have no bonus room. Seal every unused opening, never leave a door into void.
        $connectors = [];
        foreach ($placedById as $placed) foreach ($placed->connectors as $c) $connectors[] = $c;
        foreach ($connectors as $i => $c) {
            $connected = false;
            foreach ($connectors as $j => $other) {
                if ($i === $j) continue;
                if (abs($c['position'][0] - $other['position'][0]) < 0.01
                    && abs($c['position'][1] - $other['position'][1]) < 0.01
                    && abs($c['position'][2] - $other['position'][2]) < 0.01) $connected = true;
            }
            if ($connected) continue;
            [$x, $y, $z] = $c['position'];
            [$nx, , $nz] = $c['normal'];
            $seal = new RoomModule('seal');
            $hx = abs($nx) > 0.5 ? 0.25 : $c['width'] / 2;
            $hz = abs($nz) > 0.5 ? 0.25 : $c['width'] / 2;
            $seal->addBox($x - $hx, $y, $z - $hz, $x + $hx, $y + $c['height'], $z + $hz);
            array_push($solids, ...$seal->solids);
        }

        if ($treasureAnchor === null || $bossAnchor === null) {
            throw new \RuntimeException('Generated level is missing its terminal boss/treasure anchors.');
        }

        $map = BrushMapFactory::fromGenerated($solids, $spawnPoints, $playerStart, $playerYaw, $decals);
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
        array &$decals,
        array &$rooms,
        ?array &$treasureAnchor,
        ?array &$bossAnchor
    ): void {
        foreach ($placed->solids as $solid) $solids[] = $solid;
        foreach ($placed->decals as $decal) $decals[] = $decal;
        foreach ($placed->spawnAnchors as $anchor) {
            $spawnPoints[] = [$anchor['x'], $anchor['y'], $anchor['z']];
            $meta = [
                'x' => $anchor['x'], 'y' => $anchor['y'], 'z' => $anchor['z'], 'tags' => $anchor['tags'],
                'roomId' => (int)$node['id'], 'roomType' => $node['type'],
            ];
            $spawnMeta[] = $meta;
            if (in_array('boss', $anchor['tags'], true) && !in_array('wing', $anchor['tags'], true)) $bossAnchor = $meta;
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
