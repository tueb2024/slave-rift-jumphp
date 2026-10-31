<?php

namespace App\Gfx;

use App\Game\BrushMap;

/** Procedural meshes and deliberately simple Saturn-ish shaders. */
final class Meshes
{
    public const STRIDE = 11;

    public const VERTEX = <<<'GLSL'
        attribute vec3 a_pos;
        attribute vec3 a_color;
        attribute vec3 a_normal;
        attribute vec2 a_uv;
        uniform mat4 u_viewProj;
        uniform mat4 u_model;
        varying vec3 v_color;
        varying vec3 v_normal;
        varying vec3 v_world;
        varying vec2 v_uv;
        void main() {
            vec4 world = u_model * vec4(a_pos, 1.0);
            v_world = world.xyz;
            v_normal = (u_model * vec4(a_normal, 0.0)).xyz;
            v_color = a_color;
            v_uv = a_uv;
            gl_Position = u_viewProj * world;
        }
        GLSL;

    public const FRAGMENT = <<<'GLSL'
        #ifdef GL_FRAGMENT_PRECISION_HIGH
        precision highp float;
        #else
        precision mediump float;
        #endif
        uniform vec3 u_eye;
        uniform vec3 u_fog;
        uniform float u_flash;
        uniform float u_useTex;
        uniform sampler2D u_tex;
        varying vec3 v_color;
        varying vec3 v_normal;
        varying vec3 v_world;
        varying vec2 v_uv;
        void main() {
            vec3 n = normalize(v_normal);
            vec3 lightDir = normalize(vec3(-0.45, 0.80, -0.30));
            float diffuse = max(dot(n, lightDir), 0.0);
            // Keep Quake's stepped lighting, but lift the floor enough that dark
            // material texels still describe the room instead of clipping to black.
            float band = floor((0.78 + diffuse * 0.32) * 5.0) / 5.0;
            float dist = length(u_eye - v_world);
            float fog = clamp((dist - 7.0) / 15.0, 0.0, 1.0);
            vec3 texel = texture2D(u_tex, v_uv).rgb;
            vec3 base = mix(v_color, texel * (0.65 + v_color * 0.45), u_useTex);
            base = min(vec3(1.0), base * 1.35 + vec3(0.20));
            vec3 c = base * band + vec3(u_flash * 0.75, u_flash * 0.18, u_flash * 0.08);
            gl_FragColor = vec4(mix(c, u_fog, fog * fog), 1.0);
        }
        GLSL;

    /** @return float[] */
    public static function world(BrushMap $map): array
    {
        $out = [];
        foreach ($map->solids as $solid) {
            if (($solid['triangles'] ?? []) !== []) {
                foreach ($solid['triangles'] as $vertex) {
                    self::vertex($out, $vertex['p'], [
                        $vertex['color'][0] / 255,
                        $vertex['color'][1] / 255,
                        $vertex['color'][2] / 255,
                    ], $vertex['n']);
                }
            } else {
                self::boxInto($out, $solid['min'], $solid['max'], $solid['color']);
            }
        }
        return $out;
    }

    /** @return float[] */
    public static function decals(array $decals, string $material): array
    {
        $out = [];
        foreach ($decals as $decal) {
            if (($decal['material'] ?? '') !== $material) continue;
            [$x, $y, $z] = $decal['position'];
            [$nx, $ny, $nz] = $decal['normal'];
            $halfW = $decal['width'] * 0.5;
            $halfH = $decal['height'] * 0.5;
            // Horizontal tangent in the wall plane. The normal is always one of
            // the four cardinal wall normals in generated modules.
            $rx = -$nz;
            $rz = $nx;
            $offset = 0.012;
            $cx = $x + $nx * $offset;
            $cy = $y + $ny * $offset;
            $cz = $z + $nz * $offset;
            $leftX = $cx - $rx * $halfW;
            $leftZ = $cz - $rz * $halfW;
            $rightX = $cx + $rx * $halfW;
            $rightZ = $cz + $rz * $halfW;
            $bottom = $cy - $halfH;
            $top = $cy + $halfH;
            $normal = [$nx, $ny, $nz];
            $color = [1.0, 1.0, 1.0];
            self::vertex($out, [$leftX, $bottom, $leftZ], $color, $normal, [0.0, 1.0]);
            self::vertex($out, [$leftX, $top, $leftZ], $color, $normal, [0.0, 0.0]);
            self::vertex($out, [$rightX, $top, $rightZ], $color, $normal, [1.0, 0.0]);
            self::vertex($out, [$leftX, $bottom, $leftZ], $color, $normal, [0.0, 1.0]);
            self::vertex($out, [$rightX, $top, $rightZ], $color, $normal, [1.0, 0.0]);
            self::vertex($out, [$rightX, $bottom, $rightZ], $color, $normal, [1.0, 1.0]);
        }
        return $out;
    }

    /** @return float[] */
    public static function enemy(string $type): array
    {
        $out = [];
        if ($type === 'runner') {
            self::boxInto($out, [-0.24,-0.25,-0.32], [0.24,0.12,0.32], [75,170,130]);
            self::boxInto($out, [-0.16,0.10,-0.16], [0.16,0.40,0.16], [170,238,128]);
            self::boxInto($out, [-0.36,-0.55,-0.13], [-0.14,-0.20,0.13], [48,95,76]);
            self::boxInto($out, [0.14,-0.55,-0.13], [0.36,-0.20,0.13], [48,95,76]);
            return $out;
        }
        if ($type === 'brute') {
            self::boxInto($out, [-0.46,-0.55,-0.35], [0.46,0.65,0.35], [102,76,152]);
            self::boxInto($out, [-0.29,0.65,-0.24], [0.29,1.0,0.24], [202,147,81]);
            self::boxInto($out, [-0.70,-0.32,-0.22], [-0.46,0.65,0.22], [170,100,47]);
            self::boxInto($out, [0.46,-0.32,-0.22], [0.70,0.65,0.22], [170,100,47]);
            return $out;
        }
        self::boxInto($out, [-0.30, -0.55, -0.20], [0.30, 0.35, 0.20], [120, 50, 48]);
        self::boxInto($out, [-0.20, 0.35, -0.17], [0.20, 0.72, 0.17], [190, 142, 92]);
        self::boxInto($out, [-0.28, 0.66, -0.12], [0.28, 0.80, 0.12], [58, 168, 150]);
        self::boxInto($out, [-0.48, -0.42, -0.12], [-0.30, 0.20, 0.12], [91, 42, 54]);
        self::boxInto($out, [0.30, -0.30, -0.12], [0.52, 0.13, 0.12], [91, 42, 54]);
        return $out;
    }

    /** Original low-poly pickup meshes; no external artwork. */
    public static function pickup(bool $health): array
    {
        $out = [];
        if ($health) {
            self::boxInto($out, [-0.20,-0.08,-0.08], [0.20,0.08,0.08], [235,65,75]);
            self::boxInto($out, [-0.08,-0.20,-0.08], [0.08,0.20,0.08], [235,65,75]);
        } else {
            self::boxInto($out, [-0.10,-0.16,-0.10], [0.10,0.16,0.10], [70,230,225]);
        }
        return $out;
    }

    public static function pulse(): array
    {
        $out = [];
        for ($i = 0; $i < 24; $i++) {
            $a = $i * M_PI / 12; $b = ($i + 1) * M_PI / 12;
            foreach ([[sin($a),0,cos($a)], [sin($b),0,cos($b)], [sin($a)*0.94,0,cos($a)*0.94],
                [sin($b),0,cos($b)], [sin($b)*0.94,0,cos($b)*0.94], [sin($a)*0.94,0,cos($a)*0.94]] as $p) {
                self::vertex($out, $p, [0.2,0.95,1.0], [0,1,0]);
            }
        }
        return $out;
    }

    /** @param float[] $min @param float[] $max @param int[] $rgb */
    private static function boxInto(array &$out, array $min, array $max, array $rgb): void
    {
        [$x0, $y0, $z0] = $min;
        [$x1, $y1, $z1] = $max;
        $c = [$rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255];
        $faces = [
            [[$x0,$y0,$z0],[$x0,$y1,$z0],[$x1,$y1,$z0],[$x1,$y0,$z0],[0,0,-1]],
            [[$x1,$y0,$z1],[$x1,$y1,$z1],[$x0,$y1,$z1],[$x0,$y0,$z1],[0,0,1]],
            [[$x0,$y0,$z1],[$x0,$y1,$z1],[$x0,$y1,$z0],[$x0,$y0,$z0],[-1,0,0]],
            [[$x1,$y0,$z0],[$x1,$y1,$z0],[$x1,$y1,$z1],[$x1,$y0,$z1],[1,0,0]],
            [[$x0,$y1,$z0],[$x0,$y1,$z1],[$x1,$y1,$z1],[$x1,$y1,$z0],[0,1,0]],
            [[$x0,$y0,$z1],[$x0,$y0,$z0],[$x1,$y0,$z0],[$x1,$y0,$z1],[0,-1,0]],
        ];
        foreach ($faces as [$a, $b, $c0, $d, $n]) {
            self::vertex($out, $a, $c, $n);
            self::vertex($out, $b, $c, $n);
            self::vertex($out, $c0, $c, $n);
            self::vertex($out, $a, $c, $n);
            self::vertex($out, $c0, $c, $n);
            self::vertex($out, $d, $c, $n);
        }
    }

    /** @param float[] $p @param float[] $c @param float[] $n */
    private static function vertex(array &$out, array $p, array $c, array $n, ?array $uv = null): void
    {
        [$u, $v] = $uv ?? self::uv($p, $n);
        array_push($out, $p[0], $p[1], $p[2], $c[0], $c[1], $c[2], $n[0], $n[1], $n[2], $u, $v);
    }

    /** Cheap planar mapping: one 64px-ish repeat every two world units. */
    private static function uv(array $p, array $n): array
    {
        $ax = abs($n[0]);
        $ay = abs($n[1]);
        $az = abs($n[2]);
        if ($ay >= $ax && $ay >= $az) return [$p[0] * 0.5, $p[2] * 0.5];
        if ($ax >= $az) return [$p[2] * 0.5, $p[1] * 0.5];
        return [$p[0] * 0.5, $p[1] * 0.5];
    }
}
