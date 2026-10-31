<?php

namespace App\Game;

/** BrushMap plus procedural metadata kept outside the legacy runtime map contract. */
final class AssembledLevel
{
    /**
     * @param list<array{x:float,y:float,z:float,tags:list<string>,roomId:int,roomType:string}> $spawnMeta
     * @param list<array{x:float,y:float,z:float,tags:list<string>,roomId:int,roomType:string}> $pickupMeta
     * @param list<array{id:int,type:string,mainPath:bool,depth:int,bounds:array{min:float[],max:float[]},connectors:array}> $rooms
     */
    public function __construct(
        public readonly BrushMap $map,
        public readonly array $spawnMeta,
        public readonly array $pickupMeta,
        public readonly array $rooms,
        public readonly ?array $treasureAnchor,
        public readonly ?array $bossAnchor,
        public readonly int $seed,
    ) {}
}
