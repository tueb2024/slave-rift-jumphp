<?php

namespace App\Game;

/** Topological level graph: deterministic seeded path, branches, vertical transitions and terminal boss room. */
final class LevelGraph
{
    public const START = 'START';
    public const CORRIDOR_SHORT = 'CORRIDOR_SHORT';
    public const CORRIDOR_LONG = 'CORRIDOR_LONG';
    public const TURN = 'TURN';
    public const STAIR_UP = 'STAIR_UP';
    public const STAIR_DOWN = 'STAIR_DOWN';
    public const SMALL_ROOM = 'SMALL_ROOM';
    public const LARGE_ROOM = 'LARGE_ROOM';
    public const JUNCTION = 'JUNCTION';
    public const BONUS = 'BONUS';
    public const BOSS_ROOM = 'BOSS_ROOM';

    /** @var array<int,array{id:int,type:string,parent:?int,children:list<int>,depth:int,mainPath:bool}> */
    public array $nodes = [];

    public function __construct(public readonly int $seed, int $targetMainDepth = 7)
    {
        // Public depth remains a compact difficulty knob; physical route length is
        // deliberately larger so several waves happen before the terminal room.
        $physicalDepth = max(12, min(48, $targetMainDepth * 2));
        $this->build($physicalDepth);
    }

    private function build(int $depth): void
    {
        $rng = new LevelRandom($this->seed);
        $nextId = 0;
        $this->addNode($nextId, self::START, null, true);
        $parent = $nextId++;
        $middleCount = $depth - 2;
        $previousType = self::START;
        $turnBudget = max(2, min(4, intdiv($middleCount, 5)));
        $turns = 0;
        $floor = 0;

        // Every run gets a real upper-floor excursion. This is not left to chance:
        // the seed changes the surrounding rooms, branches and extra transitions.
        $upSlot = max(2, intdiv($middleCount, 3));
        $downSlot = min($middleCount - 3, max($upSlot + 3, intdiv($middleCount * 2, 3)));

        $junctionSlots = [];
        $junctionTarget = max(2, min(4, intdiv($middleCount, 5)));
        while (count($junctionSlots) < $junctionTarget) {
            $slot = 2 + $rng->nextInt(max(1, $middleCount - 4));
            if ($slot === $upSlot || $slot === $downSlot) continue;
            $junctionSlots[$slot] = true;
        }

        for ($i = 0; $i < $middleCount; $i++) {
            if ($i === $upSlot) {
                $type = self::STAIR_UP;
                $floor++;
            } elseif ($i === $downSlot) {
                $type = self::STAIR_DOWN;
                $floor--;
            } elseif (isset($junctionSlots[$i])) {
                $type = self::JUNCTION;
            } else {
                $canVertical = $i >= 2 && $i <= $middleCount - 3
                    && $previousType !== self::STAIR_UP && $previousType !== self::STAIR_DOWN
                    && $i !== $upSlot - 1 && $i !== $downSlot - 1;
                if ($canVertical && $rng->chance(0.10)) {
                    if ($floor <= 0) {
                        $type = self::STAIR_UP;
                        $floor++;
                    } elseif ($floor >= 2) {
                        $type = self::STAIR_DOWN;
                        $floor--;
                    } elseif ($rng->chance(0.52)) {
                        $type = self::STAIR_UP;
                        $floor++;
                    } else {
                        $type = self::STAIR_DOWN;
                        $floor--;
                    }
                } else {
                    $pool = [self::CORRIDOR_SHORT, self::CORRIDOR_LONG, self::SMALL_ROOM, self::LARGE_ROOM, self::LARGE_ROOM, self::TURN];
                    $type = $pool[$rng->nextInt(count($pool))];
                    if ($type === self::TURN && ($turns >= $turnBudget || $previousType === self::TURN)) {
                        $type = $rng->chance(0.5) ? self::CORRIDOR_LONG : self::LARGE_ROOM;
                    }
                }
            }
            if ($i===$middleCount-1)$type=self::SMALL_ROOM; // Wide adjacent refuge before the arena.
            if ($type === self::TURN) $turns++;
            $this->addNode($nextId, $type, $parent, true);
            $parent = $nextId++;
            $previousType = $type;
        }

        $this->addNode($nextId++, self::BOSS_ROOM, $parent, true);
        foreach ($this->nodes as $node) {
            if ($node['type'] !== self::JUNCTION || !$rng->chance(0.92)) continue;
            $this->addNode($nextId++, self::BONUS, $node['id'], false);
        }
    }

    private function addNode(int $id, string $type, ?int $parent, bool $mainPath): void
    {
        $depth = $parent === null ? 0 : (($this->nodes[$parent]['depth'] ?? -1) + 1);
        if ($parent !== null) $this->nodes[$parent]['children'][] = $id;
        $this->nodes[$id] = compact('id', 'type', 'parent', 'depth', 'mainPath') + ['children' => []];
    }

    public function findFirst(string $type): ?array
    {
        foreach ($this->nodes as $node) if ($node['type'] === $type) return $node;
        return null;
    }

    public function mainPath(): array
    {
        return array_values(array_filter($this->nodes, static fn (array $node): bool => $node['mainPath']));
    }

    public function terminal(): array
    {
        $main = $this->mainPath();
        return $main[count($main) - 1];
    }

    public function signature(): string
    {
        return implode('>', array_map(static fn (array $node): string => $node['type'], $this->mainPath()));
    }
}
