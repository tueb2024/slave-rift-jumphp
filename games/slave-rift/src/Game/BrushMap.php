<?php

namespace App\Game;

/**
 * Tiny, deliberately restricted reader for Quake .map text.
 *
 * Milestone 0.1 accepts axis-aligned convex worldspawn brushes only.  We still
 * parse normal plane syntax, but derive an AABB from every point mentioned by
 * the brush instead of solving arbitrary plane intersections.  That makes the
 * limitation explicit and deterministic while keeping the file a genuine
 * brush-map source that can grow into a full importer later.
 */
final class BrushMap
{
    public const UNITS = 32.0;

    /** @var array<int,array{min:float[],max:float[],material:string,color:int[]}> */
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
        $material = 'stone';

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
                    $material = 'stone';
                }
                continue;
            }
            if ($line === '}') {
                if ($depth === 2 && $brush !== []) {
                    $map->addBrush($brush, $material);
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
                foreach ($points as $p) {
                    $brush[] = [(float)$p[1], (float)$p[2], (float)$p[3]];
                }
                if (preg_match('/\)\s+([A-Za-z0-9_.-]+)\s+[-\d.]+\s+[-\d.]+\s+[-\d.]+/', $line, $m)) {
                    $material = $m[1];
                }
            }
        }

        if ($map->solids === []) {
            throw new \RuntimeException('Map contains no supported world brushes.');
        }
        return $map;
    }

    /** @param array<int,float[]> $points */
    private function addBrush(array $points, string $material): void
    {
        $xs = array_column($points, 0);
        $ys = array_column($points, 1);
        $zs = array_column($points, 2);
        $qMin = [min($xs), min($ys), min($zs)];
        $qMax = [max($xs), max($ys), max($zs)];

        // Quake X/Y/Z -> JumPHP X/Y/Z.  Z is vertical in Quake; world Z is
        // negated Quake Y so yaw zero can look towards +Z.
        $min = [$qMin[0] / self::UNITS, $qMin[2] / self::UNITS, -$qMax[1] / self::UNITS];
        $max = [$qMax[0] / self::UNITS, $qMax[2] / self::UNITS, -$qMin[1] / self::UNITS];
        $this->solids[] = [
            'min' => $min,
            'max' => $max,
            'material' => $material,
            'color' => self::materialColor($material),
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
        $p = [$q[0] / self::UNITS, $q[2] / self::UNITS, -$q[1] / self::UNITS];
        if ($class === 'info_player_start') {
            $this->playerStart = $p;
            // Quake angle 90 points +Y; after our Y->-Z conversion this maps
            // naturally to a negative yaw from +Z.
            $this->playerYaw = -deg2rad((float)($props['angle'] ?? 0));
        } elseif ($class === 'monster_guardian') {
            $this->guardianStart = $p;
        }
    }

    /** @return int[] */
    private static function materialColor(string $name): array
    {
        return match (strtolower($name)) {
            'basalt' => [72, 65, 78],
            'rust' => [148, 74, 48],
            'sand' => [172, 142, 82],
            default => [112, 105, 118],
        };
    }
}
