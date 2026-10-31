<?php

namespace App\Tests;

use App\Game\LevelAssembler;
use App\Game\LevelGraph;
use App\Game\ModuleLibrary;
use App\Game\Run;
use PHPUnit\Framework\TestCase;

final class ProceduralLevelTest extends TestCase
{
    public function testSeedIsDeterministicButActuallyChangesTopology(): void
    {
        self::assertSame((new LevelGraph(1337, 8))->signature(), (new LevelGraph(1337, 8))->signature());
        $signatures = [];
        for ($seed = 1; $seed <= 16; $seed++) $signatures[] = (new LevelGraph($seed, 8))->signature();
        self::assertGreaterThan(2, count(array_unique($signatures)), 'The seed must affect the level, not only be stored.');
    }

    public function testAssemblerProducesBrushMapRuntimeContract(): void
    {
        $graph = new LevelGraph(1337, 8);
        self::assertSame(LevelGraph::BOSS_ROOM, $graph->terminal()['type']);
        $level = (new LevelAssembler())->assemble($graph);
        self::assertNotEmpty($level->map->solids);
        self::assertNotEmpty($level->map->spawnPoints);
        self::assertNotNull($level->bossAnchor);
        self::assertNotNull($level->treasureAnchor);
        self::assertGreaterThanOrEqual(0.70, $level->map->spawnPoints[0][1]);
        self::assertNotEmpty($level->map->decals);
        self::assertContains('poster3615', array_column($level->map->decals, 'material'));
        $floorMaterials = [];
        $wallMaterials = [];
        foreach ($level->map->solids as $solid) {
            if ($solid['max'][1] <= 0.01) $floorMaterials[] = $solid['material'];
            elseif ($solid['min'][1] <= 0.01 && $solid['max'][1] >= 2.0) $wallMaterials[] = $solid['material'];
            self::assertArrayHasKey('min', $solid);
            self::assertArrayHasKey('max', $solid);
            self::assertArrayHasKey('color', $solid);
            self::assertArrayHasKey('triangles', $solid);
            self::assertSame([], $solid['triangles'], 'Generated AABBs should let Meshes::world build the vertex buffer.');
        }
        self::assertSame(['metal'], array_values(array_unique($floorMaterials)), 'Generated floors stay visually uniform.');
        self::assertGreaterThan(1, count(array_unique($wallMaterials)), 'Generated rooms must visibly mix wall materials.');
        $run = new Run($level->map, 1337);
        self::assertFalse($run->collides($run->x, $run->y, $run->z, Run::PLAYER_RADIUS, Run::PLAYER_HALF_HEIGHT));
    }

    public function testTurnAndJunctionExposeSemanticConnectors(): void
    {
        $turn = ModuleLibrary::get(LevelGraph::TURN);
        self::assertSame([0.0, 0.0, -1.0], $turn->connector('entry')['normal']);
        self::assertSame([1.0, 0.0, 0.0], $turn->connector('main_exit')['normal']);
        $junction = ModuleLibrary::get(LevelGraph::JUNCTION);
        self::assertNotNull($junction->connector('branch_exit'));
    }
}
