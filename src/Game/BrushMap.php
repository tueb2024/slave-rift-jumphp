<?php

namespace App\Game;

/**
 * Quake .map reader for a clean-room JumPHP prototype.
 *
 * Milestone 0.2 keeps collision deliberately conservative (brush AABBs) but no
 * longer flattens rendering to boxes. Convex worldspawn brushes are rebuilt
 * from their planes, triangulated, and sent to the renderer as real 3D faces.
 */
final class BrushMap
{
    public const UNITS = 32.0;
    private const EPS = 0.01;

    /** @var array<int,array{min:float[],max:float[],material:string,color:int[],triangles:array<int,array{p:float[],n:float[],color:int[]}>>} */
    public array $solids = [];
    /** @var float[] */
    public array $playerStart = [0.0, 0.75, -4.5];
    public float $playerYaw = 0.0;
    /** @var float[] */
    public array $guardianStart = [0.0, 0.75, 4.5];

    public static function load(string $path): self
    {
        $map = new self();
        $text = file_get_contents($path);
        if ($text === false) {
            throw new \RuntimeException("Cannot read map: {$path}");
        }

        $depth = 0;
        $props = [];
        $brush = [];

        foreach (preg_split('/\R/', $text) ?: [] as $raw) {
            $line = trim(preg_replace('~//.*$~', '', $raw) ?? '');
            if ($line === '') {
                continue;
            }
            if ($line === '{') {
                $depth++;
                if ($depth === 1) {
                    $props = [];
                } elseif ($depth === 2) {
                    $brush = [];
                }
                continue;
            }
            if ($line === '}') {
                if ($depth === 2 && $brush !== []) {
                    $map->addBrush($brush);
                } elseif ($depth === 1) {
                    $map->finishEntity($props);
                }
                $depth--;
                continue;
            }

            if ($depth === 1 && preg_match('/^"([^"]+)"\s+"([^"]*)"/', $line, $m)) {
                $props[$m[1]] = $m[2];
                continue;
            }

            if ($depth === 2) {
                preg_match_all('/\(\s*(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)\s*\)/', $line, $points, PREG_SET_ORDER);
                if (count($points) < 3) {
                    continue;
                }
                $material = 'stone';
                if (preg_match('/\)\s+([A-Za-z0-9_.\-\/]+)\s+[-\d.]+\s+[-\d.]+\s+[-\d.]+/', $line, $m)) {
                    $material = $m[1];
                }
                $brush[] = [
                    'points' => [
                        [(float)$points[0][1], (float)$points[0][2], (float)$points[0][3]],
                        [(float)$points[1][1], (float)$points[1][2], (float)$points[1][3]],
                        [(float)$points[2][1], (float)$points[2][2], (float)$points[2][3]],
                    ],
                    'material' => $material,
                ];
            }
        }

        if ($map->solids === []) {
            throw new \RuntimeException('Map contains no supported world brushes.');
        }
        return $map;
    }

    /** @param array<int,array{points:array<int,float[]>,material:string}> $faces */
    private function addBrush(array $faces): void
    {
        $planes = [];
        $allPoints = [];
        foreach ($faces as $face) {
            [$a, $b, $c] = $face['points'];
            $allPoints[] = $a;
            $allPoints[] = $b;
            $allPoints[] = $c;
            $n = self::cross(self::sub($b, $a), self::sub($c, $a));
            $len = self::length($n);
            if ($len < 1e-8) {
                continue;
            }
            $n = self::scale($n, 1.0 / $len);
            $planes[] = [
                'n' => $n,
                'd' => self::dot($n, $a),
                'material' => $face['material'],
            ];
        }
        if (count($planes) < 4) {
            return;
        }

        // Orient every plane consistently so the rough brush centroid is inside.
        $centroid = [0.0, 0.0, 0.0];
        foreach ($allPoints as $p) {
            $centroid = self::add($centroid, $p);
        }
        $centroid = self::scale($centroid, 1.0 / max(1, count($allPoints)));
        foreach ($planes as &$plane) {
            if (self::dot($plane['n'], $centroid) > $plane['d']) {
                $plane['n'] = self::scale($plane['n'], -1.0);
                $plane['d'] = -$plane['d'];
            }
        }
        unset($plane);

        $vertices = [];
        $count = count($planes);
        for ($i = 0; $i < $count - 2; $i++) {
            for ($j = $i + 1; $j < $count - 1; $j++) {
                for ($k = $j + 1; $k < $count; $k++) {
                    $p = self::intersect3($planes[$i], $planes[$j], $planes[$k]);
                    if ($p === null || !self::insideAll($p, $planes)) {
                        continue;
                    }
                    self::appendUnique($vertices, $p);
                }
            }
        }
        if (count($vertices) < 4) {
            return;
        }

        $xs = array_column($vertices, 0);
        $ys = array_column($vertices, 1);
        $zs = array_column($vertices, 2);
        $qMin = [min($xs), min($ys), min($zs)];
        $qMax = [max($xs), max($ys), max($zs)];
        $triangles = [];
        $dominantMaterial = $planes[0]['material'] ?? 'stone';

        foreach ($planes as $plane) {
            $onFace = [];
            foreach ($vertices as $p) {
                if (abs(self::dot($plane['n'], $p) - $plane['d']) <= self::EPS * 4) {
                    $onFace[] = $p;
                }
            }
            if (count($onFace) < 3) {
                continue;
            }

            $center = [0.0, 0.0, 0.0];
            foreach ($onFace as $p) {
                $center = self::add($center, $p);
            }
            $center = self::scale($center, 1.0 / count($onFace));

            $ref = abs($plane['n'][2]) < 0.9 ? [0.0, 0.0, 1.0] : [0.0, 1.0, 0.0];
            $u = self::normalize(self::cross($ref, $plane['n']));
            $v = self::cross($plane['n'], $u);
            usort($onFace, function (array $a, array $b) use ($center, $u, $v): int {
                $da = self::sub($a, $center);
                $db = self::sub($b, $center);
                $aa = atan2(self::dot($da, $v), self::dot($da, $u));
                $ab = atan2(self::dot($db, $v), self::dot($db, $u));
                return $aa <=> $ab;
            });

            $worldNormal = self::normalToWorld($plane['n']);
            $color = self::materialColor($plane['material']);
            for ($i = 1; $i < count($onFace) - 1; $i++) {
                foreach ([$onFace[0], $onFace[$i], $onFace[$i + 1]] as $p) {
                    $triangles[] = [
                        'p' => self::toWorld($p),
                        'n' => $worldNormal,
                        'color' => $color,
                    ];
                }
            }
        }

        $this->solids[] = [
            'min' => self::toWorld([$qMin[0], $qMin[1], $qMin[2]], true),
            'max' => self::toWorld([$qMax[0], $qMax[1], $qMax[2]], true, true),
            'material' => $dominantMaterial,
            'color' => self::materialColor($dominantMaterial),
            'triangles' => $triangles,
        ];
    }

    /** @param array<string,string> $props */
    private function finishEntity(array $props): void
    {
        $class = $props['classname'] ?? '';
        if (!isset($props['origin'])) {
            return;
        }
        $q = array_map('floatval', preg_split('/\s+/', trim($props['origin'])) ?: []);
        if (count($q) < 3) {
            return;
        }
        $p = self::toWorld([$q[0], $q[1], $q[2]]);
        if ($class === 'info_player_start') {
            $this->playerStart = $p;
            $this->playerYaw = -deg2rad((float)($props['angle'] ?? 0));
        } elseif ($class === 'monster_guardian') {
            $this->guardianStart = $p;
        }
    }

    /** @param float[] $p @return float[] */
    private static function toWorld(array $p, bool $bounds = false, bool $upper = false): array
    {
        if (!$bounds) {
            return [$p[0] / self::UNITS, $p[2] / self::UNITS, -$p[1] / self::UNITS];
        }
        // Bounds need Y inversion awareness: caller passes qMin for lower and qMax for upper.
        return [$p[0] / self::UNITS, $p[2] / self::UNITS, -$p[1] / self::UNITS];
    }

    /** @param float[] $n @return float[] */
    private static function normalToWorld(array $n): array
    {
        return self::normalize([$n[0], $n[2], -$n[1]]);
    }

    /** @param array{n:float[],d:float} $a @param array{n:float[],d:float} $b @param array{n:float[],d:float} $c @return float[]|null */
    private static function intersect3(array $a, array $b, array $c): ?array
    {
        $bc = self::cross($b['n'], $c['n']);
        $ca = self::cross($c['n'], $a['n']);
        $ab = self::cross($a['n'], $b['n']);
        $den = self::dot($a['n'], $bc);
        if (abs($den) < 1e-8) {
            return null;
        }
        return self::scale(self::add(self::add(self::scale($bc, $a['d']), self::scale($ca, $b['d'])), self::scale($ab, $c['d'])), 1.0 / $den);
    }

    /** @param float[] $p @param array<int,array{n:float[],d:float}> $planes */
    private static function insideAll(array $p, array $planes): bool
    {
        foreach ($planes as $plane) {
            if (self::dot($plane['n'], $p) - $plane['d'] > self::EPS) {
                return false;
            }
        }
        return true;
    }

    /** @param array<int,float[]> $vertices @param float[] $p */
    private static function appendUnique(array &$vertices, array $p): void
    {
        foreach ($vertices as $v) {
            if (self::length(self::sub($v, $p)) < self::EPS * 2) {
                return;
            }
        }
        $vertices[] = $p;
    }

    /** @param float[] $a @param float[] $b @return float[] */
    private static function add(array $a, array $b): array { return [$a[0] + $b[0], $a[1] + $b[1], $a[2] + $b[2]]; }
    /** @param float[] $a @param float[] $b @return float[] */
    private static function sub(array $a, array $b): array { return [$a[0] - $b[0], $a[1] - $b[1], $a[2] - $b[2]]; }
    /** @param float[] $a @return float[] */
    private static function scale(array $a, float $s): array { return [$a[0] * $s, $a[1] * $s, $a[2] * $s]; }
    /** @param float[] $a @param float[] $b */
    private static function dot(array $a, array $b): float { return $a[0] * $b[0] + $a[1] * $b[1] + $a[2] * $b[2]; }
    /** @param float[] $a @param float[] $b @return float[] */
    private static function cross(array $a, array $b): array { return [$a[1] * $b[2] - $a[2] * $b[1], $a[2] * $b[0] - $a[0] * $b[2], $a[0] * $b[1] - $a[1] * $b[0]]; }
    /** @param float[] $a */
    private static function length(array $a): float { return sqrt(self::dot($a, $a)); }
    /** @param float[] $a @return float[] */
    private static function normalize(array $a): array { $l = max(1e-9, self::length($a)); return self::scale($a, 1.0 / $l); }

    /** @return int[] */
    private static function materialColor(string $name): array
    {
        $key = strtolower(basename(str_replace('\\', '/', $name)));
        return match ($key) {
            'basalt' => [72, 65, 78],
            'rust' => [148, 74, 48],
            'sand' => [172, 142, 82],
            'metal', 'tech', 'trim' => [92, 112, 124],
            default => [112, 105, 118],
        };
    }
}
