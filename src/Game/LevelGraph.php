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
        // The first procedural pass interpreted "depth 7" too literally and produced
        // a five-minute idea in a two-minute corridor. Keep the public parameter but
        // expand it into a proper FPS route with enough space for several waves.
        $physicalDepth = max(12, min(32, $targetMainDepth * 2));
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

        // Seeded junction slots guarantee several side opportunities without turning
        // the main route into a maze. Their exact positions still vary by seed.
        $junctionSlots = [];
        $junctionTarget = max(2, min(4, intdiv($middleCount, 5)));
        while (count($junctionSlots) < $junctionTarget) {
            $slot = 2 + $rng->nextInt(max(1, $middleCount - 4));
            $junctionSlots[$slot] = true;
        }

        for ($i = 0; $i < $middleCount; $i++) {
            if (isset($junctionSlots[$i])) {
                $type = self::JUNCTION;
            } else {
                $canVertical = $i >= 2 && $i <= $middleCount - 3 && $previousType !== self::STAIR_UP && $previousType !== self::STAIR_DOWN;
                if ($canVertical && $rng->chance(0.18)) {
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
                    $pool = [
                        self::CORRIDOR_SHORT,
                        self::CORRIDOR_LONG,
                        self::SMALL_ROOM,
                        self::LARGE_ROOM,
                        self::LARGE_ROOM,
                        self::TURN,
                    ];
                    $type = $pool[$rng->nextInt(count($pool))];
                    if ($type === self::TURN && ($turns >= $turnBudget || $previousType === self::TURN)) {
                        $type = $rng->chance(0.5) ? self::CORRIDOR_LONG : self::LARGE_ROOM;
                    }
                }
            }

            if ($type === self::TURN) $turns++;
            $this->addNode($nextId, $type, $parent, true);
            $parent = $nextId++;
            $previousType = $type;
        }

        $this->addNode($nextId++, self::BOSS_ROOM, $parent, true);

        // DeepSeek's useful idea was to make side rooms a normal feature of the
        // generated route rather than a rare special case. Keep them short and
        // deterministic so they enrich exploration without obscuring A -> B.
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
