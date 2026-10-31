<?php

namespace App\Gfx;

/** Build-time-free procedural wall posters, shared by native OpenGL and WASM/WebGL. */
final class PosterFactory
{
    public static function advert(): \GdImage
    {
        $im = imagecreatetruecolor(128, 128);
        imagealphablending($im, true);
        imagesavealpha($im, true);

        $ink = imagecolorallocate($im, 18, 10, 28);
        $paper = imagecolorallocate($im, 245, 214, 70);
        $pink = imagecolorallocate($im, 242, 52, 151);
        $cyan = imagecolorallocate($im, 55, 226, 226);
        $white = imagecolorallocate($im, 250, 245, 224);

        imagefilledrectangle($im, 0, 0, 127, 127, $paper);
        imagefilledrectangle($im, 5, 5, 122, 122, $ink);
        imagefilledrectangle($im, 9, 9, 118, 31, $pink);
        imagefilledrectangle($im, 9, 35, 118, 111, $white);
        imagefilledrectangle($im, 9, 115, 118, 118, $cyan);

        imagestring($im, 5, 35, 13, 'VOTRE', $white);
        imagestring($im, 5, 45, 48, 'PUB', $ink);
        imagestring($im, 5, 48, 72, 'ICI', $pink);
        imagestring($im, 2, 31, 98, '3615 / RESERVE', $ink);

        // Deliberately chunky registration marks: the procedural modules can reuse
        // the same graphic at several scales without it turning into visual noise.
        imagefilledrectangle($im, 12, 38, 18, 44, $cyan);
        imagefilledrectangle($im, 109, 102, 115, 108, $cyan);
        imagerectangle($im, 2, 2, 125, 125, $pink);
        return $im;
    }
}
