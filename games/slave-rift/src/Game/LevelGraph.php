<?php

namespace App\Game;

/** Topological level graph: deterministic main path, short optional branches, terminal boss room. */
final class LevelGraph
{
    public const START = 'START';
    public const CORRIDOR_SHORT = 'CORRIDOR_SHORT';
    public const CORRIDOR_LONG = 'CORRIDOR_LONG';
    public const TURN = 'TURN';
    public const SMALL_ROOM = 'SMALL_ROOM';
    public const LARGE_ROOM = 'LARGE_ROOM';
    public const JUNCTION = 'JUNCTION';
    public const BONUS = 'BONUS';
    public const BOSS_ROOM = 'BOSS_ROOM';

    /** @var array<int,array{id:int,type:string,parent:?int,children:list<int>,depth:int,mainPath:bool}> */
    public array $nodes = [];

    public function __construct(public readonly int $seed, int $targetMainDepth = 7)
    {
        $this->build(max(5, min(24, $targetMainDepth)));
    }

    private function build(int $depth): void
    {
        $rng = new LevelRandom($this->seed);
        $nextId = 0;
        $this->addNode($nextId, self::START, null, true);
        $parent = $nextId++;
        $middleCount = $depth - 2;
        $forcedJunction = $middleCount >= 4 ? 1 + $rng->nextInt(max(1, $middleCount - 2)) : -1;
        $turnUsed = false;
        $previousType = self::START;

        for ($i = 0; $i < $middleCount; $i++) {
            if ($i === $forcedJunction) {
                $type = self::JUNCTION;
            } else {
                $pool = [
                    self::CORRIDOR_SHORT,
                    self::CORRIDOR_LONG,
                    self::SMALL_ROOM,
                    self::LARGE_ROOM,
                    self::TURN,
                ];
                $type = $pool[$rng->nextInt(count($pool))];
                if ($type === self::TURN && ($turnUsed || $previousType === self::TURN)) {
                    $type = $rng->chance(0.5) ? self::CORRIDOR_SHORT : self::SMALL_ROOM;
                }
            }
            if ($type === self::TURN) $turnUsed = true;
            $this->addNode($nextId, $type, $parent, true);
            $parent = $nextId++;
            $previousType = $type;
        }

        $this->addNode($nextId++, self::BOSS_ROOM, $parent, true);

        $junction = $this->findFirst(self::JUNCTION);
        if ($junction !== null && $rng->chance(0.85)) {
            $this->addNode($nextId, self::BONUS, $junction['id'], false);
        }
    }

    private function addNode(int $id, string $type, ?int $parent, bool $mainPath): void
    {
        $depth = $parent === null ? 0 : (($this->nodes[$parent]['depth'] ?? -1) + 1);
        if ($parent !== null) $this->nodes[$parent]['children'][] = $id;
        $this->nodes[$id] = compact('id', 'type', 'parent', 'depth', 'mainPath') + ['children' => []];
    }

    /** @return array{id:int,type:string,parent:?int,children:list<int>,depth:int,mainPath:bool}|null */
    public function findFirst(string $type): ?array
    {
        foreach ($this->nodes as $node) if ($node['type'] === $type) return $node;
        return null;
    }

    /** @return list<array{id:int,type:string,parent:?int,children:list<int>,depth:int,mainPath:bool}> */
    public function mainPath(): array
    {
        return array_values(array_filter($this->nodes, static fn (array $node): bool => $node['mainPath']));
    }

    /** @return array{id:int,type:string,parent:?int,children:list<int>,depth:int,mainPath:bool} */
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
