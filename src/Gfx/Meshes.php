<?php

namespace App\Gfx;

use App\Game\BrushMap;

/** Procedural meshes and deliberately simple Saturn-ish shaders. */
final class Meshes
{
    public const STRIDE = 9;

    public const VERTEX = <<<'GLSL'
        attribute vec3 a_pos;
        attribute vec3 a_color;
        attribute vec3 a_normal;
        uniform mat4 u_viewProj;
        uniform mat4 u_model;
        varying vec3 v_color;
        varying vec3 v_normal;
        varying vec3 v_world;
        void main() {
            vec4 world = u_model * vec4(a_pos, 1.0);
            v_world = world.xyz;
            v_normal = (u_model * vec4(a_normal, 0.0)).xyz;
            v_color = a_color;
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
        varying vec3 v_color;
        varying vec3 v_normal;
        varying vec3 v_world;
        void main() {
            vec3 n = normalize(v_normal);
            vec3 lightDir = normalize(vec3(-0.45, 0.80, -0.30));
            float diffuse = max(dot(n, lightDir), 0.0);
            float band = floor((0.28 + diffuse * 0.82) * 5.0) / 5.0;
            float dist = length(u_eye - v_world);
            float fog = clamp((dist - 7.0) / 15.0, 0.0, 1.0);
            vec3 c = v_color * band + vec3(u_flash * 0.75, u_flash * 0.18, u_flash * 0.08);
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
    public static function guardian(): array
    {
        $out = [];
        self::boxInto($out, [-0.30, -0.55, -0.20], [0.30, 0.35, 0.20], [120, 50, 48]);
        self::boxInto($out, [-0.20, 0.35, -0.17], [0.20, 0.72, 0.17], [190, 142, 92]);
        self::boxInto($out, [-0.28, 0.66, -0.12], [0.28, 0.80, 0.12], [58, 168, 150]);
        self::boxInto($out, [-0.48, -0.42, -0.12], [-0.30, 0.20, 0.12], [91, 42, 54]);
        self::boxInto($out, [0.30, -0.30, -0.12], [0.52, 0.13, 0.12], [91, 42, 54]);
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
    private static function vertex(array &$out, array $p, array $c, array $n): void
    {
        array_push($out, $p[0], $p[1], $p[2], $c[0], $c[1], $c[2], $n[0], $n[1], $n[2]);
    }
}
