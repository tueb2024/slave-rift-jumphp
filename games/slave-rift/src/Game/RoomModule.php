<?php

namespace App\Game;

/** Axis-aligned room module expressed directly in BrushMap runtime solids. */
final class RoomModule
{
    /** @var list<array{min:float[],max:float[],material:string,color:int[],triangles:array}> */
    public array $solids = [];
    /** @var list<array{position:float[],normal:float[],width:float,height:float,kind:string,role:string}> */
    public array $connectors = [];
    /** @var list<array{x:float,y:float,z:float,tags:list<string>}> */
    public array $spawnAnchors = [];
    /** @var list<array{x:float,y:float,z:float,tags:list<string>}> */
    public array $pickupAnchors = [];
    /** @var list<array{material:string,position:float[],normal:float[],width:float,height:float}> */
    public array $decals = [];

    public function __construct(public readonly string $type) {}

    public function addBox(float $x1, float $y1, float $z1, float $x2, float $y2, float $z2, string $material = 'stone'): void
    {
        $this->solids[] = [
            'min' => [min($x1, $x2), min($y1, $y2), min($z1, $z2)],
            'max' => [max($x1, $x2), max($y1, $y2), max($z1, $z2)],
            'material' => $material,
            'color' => self::materialColor($material),
            'triangles' => [],
        ];
    }

    /** Add a wall slab split around a real rectangular opening. */
    public function addWallWithDoor(
        float $x1, float $z1, float $x2, float $z2,
        float $y1, float $y2,
        float $doorCenter, float $doorWidth, float $doorHeight,
        string $axis, string $material = 'stone'
    ): void {
        $half = $doorWidth * 0.5;
        $doorMin = $doorCenter - $half;
        $doorMax = $doorCenter + $half;
        if ($axis === 'x') {
            $min = min($x1, $x2); $max = max($x1, $x2);
            if ($doorMin > $min) $this->addBox($min, $y1, $z1, $doorMin, $y2, $z2, $material);
            if ($doorMax < $max) $this->addBox($doorMax, $y1, $z1, $max, $y2, $z2, $material);
            if ($y2 > $doorHeight) $this->addBox($doorMin, $doorHeight, $z1, $doorMax, $y2, $z2, $material);
            return;
        }
        $min = min($z1, $z2); $max = max($z1, $z2);
        if ($doorMin > $min) $this->addBox($x1, $y1, $min, $x2, $y2, $doorMin, $material);
        if ($doorMax < $max) $this->addBox($x1, $y1, $doorMax, $x2, $y2, $max, $material);
        if ($y2 > $doorHeight) $this->addBox($x1, $doorHeight, $doorMin, $x2, $y2, $doorMax, $material);
    }

    public function addConnector(string $role, float $x, float $y, float $z, float $nx, float $ny, float $nz, float $width, float $height, string $kind = 'door'): void
    {
        $this->connectors[] = [
            'position' => [$x, $y, $z], 'normal' => [$nx, $ny, $nz],
            'width' => $width, 'height' => $height, 'kind' => $kind, 'role' => $role,
        ];
    }

    public function addSpawnAnchor(float $x, float $y, float $z, array $tags = []): void
    {
        $this->spawnAnchors[] = compact('x', 'y', 'z', 'tags');
    }

    public function addPickupAnchor(float $x, float $y, float $z, array $tags = []): void
    {
        $this->pickupAnchors[] = compact('x', 'y', 'z', 'tags');
    }

    public function addDecal(string $material, float $x, float $y, float $z, float $nx, float $ny, float $nz, float $width, float $height): void
    {
        $this->decals[] = [
            'material' => $material, 'position' => [$x, $y, $z], 'normal' => [$nx, $ny, $nz],
            'width' => $width, 'height' => $height,
        ];
    }

    public function connector(string $role): ?array
    {
        foreach ($this->connectors as $connector) if ($connector['role'] === $role) return $connector;
        return null;
    }

    public function transform(int $rotationDeg, float $tx, float $ty, float $tz): self
    {
        $rotationDeg = (($rotationDeg % 360) + 360) % 360;
        $rad = deg2rad((float)$rotationDeg);
        $cos = (int)round(cos($rad));
        $sin = (int)round(sin($rad));
        $copy = new self($this->type);

        foreach ($this->solids as $solid) {
            $mins = [INF, INF, INF]; $maxs = [-INF, -INF, -INF];
            foreach ([$solid['min'][0], $solid['max'][0]] as $x) {
                foreach ([$solid['min'][1], $solid['max'][1]] as $y) {
                    foreach ([$solid['min'][2], $solid['max'][2]] as $z) {
                        [$rx, $ry, $rz] = self::transformPoint($x, $y, $z, $cos, $sin, $tx, $ty, $tz);
                        $mins = [min($mins[0], $rx), min($mins[1], $ry), min($mins[2], $rz)];
                        $maxs = [max($maxs[0], $rx), max($maxs[1], $ry), max($maxs[2], $rz)];
                    }
                }
            }
            $copy->solids[] = $solid + [];
            $last = array_key_last($copy->solids);
            $copy->solids[$last]['min'] = $mins;
            $copy->solids[$last]['max'] = $maxs;
            $copy->solids[$last]['triangles'] = [];
        }

        foreach ($this->connectors as $c) {
            [$x, $y, $z] = $c['position']; [$nx, $ny, $nz] = $c['normal'];
            [$rx, $ry, $rz] = self::transformPoint($x, $y, $z, $cos, $sin, $tx, $ty, $tz);
            $copy->connectors[] = $c + [];
            $last = array_key_last($copy->connectors);
            $copy->connectors[$last]['position'] = [$rx, $ry, $rz];
            $copy->connectors[$last]['normal'] = [$nx * $cos - $nz * $sin, $ny, $nx * $sin + $nz * $cos];
        }
        foreach ($this->spawnAnchors as $a) {
            [$x, $y, $z] = self::transformPoint($a['x'], $a['y'], $a['z'], $cos, $sin, $tx, $ty, $tz);
            $copy->spawnAnchors[] = ['x' => $x, 'y' => $y, 'z' => $z, 'tags' => $a['tags']];
        }
        foreach ($this->pickupAnchors as $a) {
            [$x, $y, $z] = self::transformPoint($a['x'], $a['y'], $a['z'], $cos, $sin, $tx, $ty, $tz);
            $copy->pickupAnchors[] = ['x' => $x, 'y' => $y, 'z' => $z, 'tags' => $a['tags']];
        }
        foreach ($this->decals as $decal) {
            [$x, $y, $z] = $decal['position'];
            [$nx, $ny, $nz] = $decal['normal'];
            [$rx, $ry, $rz] = self::transformPoint($x, $y, $z, $cos, $sin, $tx, $ty, $tz);
            $copy->decals[] = [
                'material' => $decal['material'],
                'position' => [$rx, $ry, $rz],
                'normal' => [$nx * $cos - $nz * $sin, $ny, $nx * $sin + $nz * $cos],
                'width' => $decal['width'], 'height' => $decal['height'],
            ];
        }
        return $copy;
    }

    /** @return array{min:float[],max:float[]} */
    public function bounds(): array
    {
        $mins = [INF, INF, INF]; $maxs = [-INF, -INF, -INF];
        foreach ($this->solids as $solid) {
            for ($axis = 0; $axis < 3; $axis++) {
                $mins[$axis] = min($mins[$axis], $solid['min'][$axis]);
                $maxs[$axis] = max($maxs[$axis], $solid['max'][$axis]);
            }
        }
        return ['min' => $mins, 'max' => $maxs];
    }

    private static function transformPoint(float $x, float $y, float $z, int $cos, int $sin, float $tx, float $ty, float $tz): array
    {
        return [$x * $cos - $z * $sin + $tx, $y + $ty, $x * $sin + $z * $cos + $tz];
    }

    private static function materialColor(string $material): array
    {
        return match ($material) {
            'metal' => [92, 112, 124], 'crate' => [160, 110, 70], 'ceil' => [90, 90, 100],
            default => [112, 105, 118],
        };
    }
}
