<?php
declare(strict_types=1);

namespace App\Gfx;

/**
 * MinitelRosePoster — générateur procédural d'affiches Minitel rose.
 * Adaptation directe du générateur DeepSeek fourni pour Slave Rift.
 */
final class MinitelRosePoster
{
    private const NAMES = [
        'Ulla', 'Aline', 'Jane', 'Sarah', 'Mytilène',
        'Aurélie', 'Émilie', 'Céline', 'Élodie', 'Julie', 'Marie',
        'Stéphanie', 'Audrey', 'Laetitia', 'Virginie', 'Sophie',
        'Mélanie', 'Sabrina', 'Vanessa', 'Caroline', 'Delphine',
        'Amandine', 'Jennifer', 'Jessica', 'Nathalie', 'Valérie',
        'Christelle', 'Sandra', 'Karine', 'Séverine', 'Magali',
        'Coralie', 'Marion', 'Lucie', 'Camille', 'Chloé', 'Manon',
        'Léa', 'Anaïs', 'Pauline', 'Justine', 'Océane', 'Charlotte',
        'Mathilde', 'Clémentine', 'Margaux', 'Inès', 'Lou', 'Jade',
        'Lola', 'Zoé', 'Nina', 'Éva', 'Alice', 'Rose', 'Anna',
        'Julia', 'Clara', 'Emma', 'Yasmine', 'Estelle', 'Aurore',
        'Constance', 'Diane', 'Éléonore', 'Fanny', 'Gabrielle',
        'Hélène', 'Iris', 'Jeanne', 'Maëva', 'Noémie', 'Ophélie',
        'Solène', 'Valentine', 'Priscilla', 'Tiffany', 'Leslie',
    ];

    private const CODES = ['3615', '3617'];

    private const PROFILES = [
        [
            '..............', '....HHHHHH....', '...HHHHHHHH...',
            '..HHHHHHHHHH..', '..HHHHHHHHHH..', '..HHFFFFFFHH..',
            '..HFFEFFEFFH..', '..HFFFFFFFFH..', '..HFFFMMFFFH..',
            '..HFFFFFFFFH..', '...FFFFFFFF...', '....FFFFFF....',
            '.....FFFF.....', '..............',
        ],
        [
            '..............', '....HHHHHH....', '...HHHHHHHH...',
            '..HHHHHHHHHH..', '..HHHHHHHHHH..', '..HHFFFFFFHH..',
            '..HFFEFFEFFH..', '..HFFFFFFFFH..', '..HHFFMMFFHH..',
            '..HHFFFFFFHH..', '..HHFFFFFFHH..', '..HHFFFFFFHH..',
            '...HHFFFFHH...', '....HHHHHH....',
        ],
        [
            '......HH......', '......HH......', '....HHHHHH....',
            '...HHHHHHHH...', '..HHHHHHHHHH..', '..HHHHHHHHHH..',
            '..HHFFFFFFHH..', '..HFFEFFEFFH..', '..HFFFFFFFFH..',
            '..HFFFMMFFFH..', '..HFFFFFFFFH..', '...FFFFFFFF...',
            '....FFFFFF....', '..............',
        ],
        [
            '.H.H.H.H.H.H..', 'HHHHHHHHHHHH..', '.HHHHHHHHHH...',
            'HHHHHHHHHHHH..', 'HHFFFFFFFFHH..', 'HHFEFFFFEFHH..',
            '.HFFFFFFFFH...', '.HFFFFFFFFH...', '.HFFFMMFFFH...',
            '.HFFFFFFFFH...', '..HFFFFFFH....', '...FFFFFF.....',
            '....FFFF......', '..............',
        ],
        [
            '..............', '....HHHHHH....', '...HHHHHHHH...',
            '..HHHHHHHHHH..', '..HHHHHHHHHH..', '..HHFFFFFFH...',
            '..HFFEFFEFFH..', '..HFFFFFFFFH..', '..HFFFMMFFFH..',
            '..HFFFFFFFFH..', '...FFFFFFFF..H', '....FFFFFFHHHH',
            '.....FFFFHHHHH', '..........HHHH',
        ],
        [
            '..............', '....HHHHHH....', '...HHHHHHHH...',
            '..HHHHHHHHHH..', '..HHHHHHHHHH..', '..HHFFFFFFHH..',
            '..HFFEFFEFFH..', '..HFFFFFFFFH..', '..HFFFFFFFFH..',
            '..HHFFMMFFHH..', '..HHFFFFFFHH..', '..HHFFFFFFHH..',
            '..HHFFFFFFHH..', '..HHFFFFFFHH..',
        ],
        [
            '..............', '....HHHHHH....', 'H..HHHHHHHH..H',
            'HH.HHHHHHHH.HH', 'HHHHHHHHHHHHHH', '.HHFFFFFFFFHH.',
            '.HHFEFFFFEFHH.', '.HHFFFFFFFFHH.', '..HFFFFFFFFH..',
            '..HFFFMMFFFH..', '..HFFFFFFFFH..', '...FFFFFFFF...',
            '....FFFFFF....', '.....FFFF.....',
        ],
        [
            '..............', '..............', '....HHHHHH....',
            '...HHHHHHHH...', '..HHHHHHHHHH..', '..HHHHHHHHHH..',
            '..HHFFFFFFHH..', '..HFFEFFEFFH..', '..HFFFFFFFFH..',
            '..HFFFMMFFFH..', '..HFFFFFFFFH..', '...FFFFFFFF...',
            '....FFFFFF....', '.....FFFF.....',
        ],
        [
            '..H..H..H..H..', '.HHH.HHH.HHH..', 'HHHHHHHHHHHHH.',
            'HHHHHHHHHHHHHH', 'HHHHHHHHHHHHH.', '.HHFFFFFFFFHH.',
            '..HFEFFFFEFH..', '..HFFFFFFFFH..', '..HFFFFFFFFH..',
            '..HFFFMMFFFH..', '..HFFFFFFFFH..', '...FFFFFFFF...',
            '....FFFFFF....', '.....FFFF.....',
        ],
        [
            '..............', '....HHHHHH....', '...HHHHHHHH...',
            '..HHHHHHHHHH..', '..HHBBBBBBHH..', '..HHFFFFFFHH..',
            '..HFFEFFEFFH..', '..HFFFFFFFFH..', '..HFFFMMFFFH..',
            '..HFFFFFFFFH..', '...FFFFFFFF...', '....FFFFFF....',
            '.....FFFF.....', '..............',
        ],
    ];

    private const SKIN_COLORS = [
        [0xF8, 0xD5, 0xC0], [0xF0, 0xB8, 0xA0], [0xE8, 0xA8, 0x88],
        [0xD8, 0x90, 0x70], [0xFC, 0xE0, 0xD0], [0xF5, 0xC8, 0xB0],
    ];

    private const HAIR_COLORS = [
        [0xE8, 0xC8, 0x70], [0xE8, 0xE0, 0xD0], [0x7A, 0x50, 0x30],
        [0x2B, 0x20, 0x20], [0xA0, 0x40, 0x20], [0xFF, 0x90, 0xB0],
        [0xD0, 0x80, 0x70],
    ];

    private const TAGLINES = [
        'vous attend en direct',
        'messagerie rose 24/24',
        'rencontres coquines',
        'en direct sur minitel',
        'tapez vite!',
    ];

    public const WIDTH = 400;
    public const HEIGHT = 580;
    private const PIXEL_SIZE = 20;
    private const PORTRAIT_W = 280;
    private const PORTRAIT_H = 280;

    private const COLOR_BG = [0x10, 0x08, 0x18];
    private const COLOR_PINK = [0xFF, 0x60, 0xA0];
    private const COLOR_BRIGHT_PINK = [0xFF, 0x90, 0xC0];
    private const COLOR_HOT_PINK = [0xFF, 0x40, 0x80];
    private const COLOR_EYE = [0x00, 0x00, 0x00];
    private const COLOR_MOUTH = [0x90, 0x20, 0x30];

    public function __construct()
    {
        if (!extension_loaded('gd')) {
            throw new \RuntimeException('ext-gd est requis pour MinitelRosePoster.');
        }
    }

    public function generate(?int $seed = null): \GdImage
    {
        if ($seed !== null) mt_srand($seed);
        return $this->render(
            self::NAMES[array_rand(self::NAMES)],
            self::CODES[array_rand(self::CODES)],
            self::PROFILES[array_rand(self::PROFILES)],
            self::SKIN_COLORS[array_rand(self::SKIN_COLORS)],
            self::HAIR_COLORS[array_rand(self::HAIR_COLORS)],
            self::TAGLINES[array_rand(self::TAGLINES)],
        );
    }

    public function generateAndSave(string $path, ?int $seed = null): string
    {
        $img = $this->generate($seed);
        $dir = dirname($path);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        imagepng($img, $path);
        imagedestroy($img);
        return $path;
    }

    public function generateBatch(string $dir, int $count = 20, int $startSeed = 0): array
    {
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $paths = [];
        for ($i = 0; $i < $count; $i++) {
            $seed = $startSeed + $i;
            $path = rtrim($dir, '/') . "/poster_{$seed}.png";
            $this->generateAndSave($path, $seed);
            $paths[] = $path;
        }
        return $paths;
    }

    private function render(string $name, string $code, array $profile, array $skin, array $hair, string $tagline): \GdImage
    {
        $img = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagesavealpha($img, true);
        imagealphablending($img, true);
        $bg = $this->alloc($img, self::COLOR_BG);
        imagefill($img, 0, 0, $bg);
        $this->drawScanlines($img);
        $this->drawTextScaled($img, '* MINITEL ROSE *', intdiv(self::WIDTH, 2), 15, self::COLOR_BRIGHT_PINK, 1, true);

        $px = self::PIXEL_SIZE;
        $pxX = intdiv(self::WIDTH - self::PORTRAIT_W, 2);
        $pxY = 50;
        $this->drawPortrait($img, $profile, $pxX, $pxY, $px, $skin, $hair);
        $frame = $this->alloc($img, self::COLOR_PINK);
        imagerectangle($img, $pxX - 2, $pxY - 2, $pxX + self::PORTRAIT_W + 1, $pxY + self::PORTRAIT_H + 1, $frame);

        $nameL1 = $this->toLatin1($name);
        $nameW = imagefontwidth(5) * strlen($nameL1);
        $nameScale = max(2, min(4, intdiv(self::WIDTH - 40, max(1, $nameW))));
        $this->drawTextScaled($img, $nameL1, intdiv(self::WIDTH, 2), 350, self::COLOR_BRIGHT_PINK, $nameScale, true);
        $this->drawTextScaled($img, $tagline, intdiv(self::WIDTH, 2), 420, self::COLOR_PINK, 1, true);

        $codeLine = $code . ' ' . $nameL1;
        $codeW = imagefontwidth(5) * strlen($codeLine);
        $codeScale = max(2, min(3, intdiv(self::WIDTH - 40, max(1, $codeW))));
        $this->drawTextScaled($img, $codeLine, intdiv(self::WIDTH, 2), 455, self::COLOR_HOT_PINK, $codeScale, true);
        $this->drawTextScaled($img, '* * *', intdiv(self::WIDTH, 2), 540, self::COLOR_BRIGHT_PINK, 1, true);
        return $img;
    }

    private function drawPortrait(\GdImage $img, array $profile, int $x, int $y, int $px, array $skin, array $hair): void
    {
        $cHair = $this->alloc($img, $hair);
        $cSkin = $this->alloc($img, $skin);
        $cEye = $this->alloc($img, self::COLOR_EYE);
        $cMouth = $this->alloc($img, self::COLOR_MOUTH);
        $cBand = $this->alloc($img, self::COLOR_HOT_PINK);
        foreach ($profile as $r => $row) {
            for ($c = 0, $len = strlen($row); $c < $len; $c++) {
                $col = match ($row[$c]) {
                    'H' => $cHair, 'F' => $cSkin, 'E' => $cEye, 'M' => $cMouth, 'B' => $cBand, default => null,
                };
                if ($col === null) continue;
                imagefilledrectangle($img, $x + $c * $px, $y + $r * $px, $x + ($c + 1) * $px - 1, $y + ($r + 1) * $px - 1, $col);
            }
        }
    }

    private function drawTextScaled(\GdImage $img, string $text, int $cx, int $cy, array $color, int $scale, bool $center): void
    {
        $fw = imagefontwidth(5);
        $fh = imagefontheight(5);
        $tw = $fw * strlen($text);
        if ($tw === 0) return;
        $tmp = imagecreatetruecolor($tw, $fh);
        $white = imagecolorallocate($tmp, 255, 255, 255);
        $black = imagecolorallocate($tmp, 0, 0, 0);
        imagefill($tmp, 0, 0, $white);
        imagestring($tmp, 5, 0, 0, $text, $black);
        $dw = $tw * $scale;
        $dx = $center ? $cx - intdiv($dw, 2) : $cx;
        $fill = $this->alloc($img, $color);
        for ($y = 0; $y < $fh; $y++) {
            for ($x = 0; $x < $tw; $x++) {
                if ((imagecolorat($tmp, $x, $y) & 0xFF) < 128) {
                    imagefilledrectangle($img, $dx + $x * $scale, $cy + $y * $scale, $dx + ($x + 1) * $scale - 1, $cy + ($y + 1) * $scale - 1, $fill);
                }
            }
        }
        imagedestroy($tmp);
    }

    private function drawScanlines(\GdImage $img): void
    {
        $dark = imagecolorallocatealpha($img, 0, 0, 0, 55);
        for ($y = 0; $y < self::HEIGHT; $y += 3) imageline($img, 0, $y, self::WIDTH - 1, $y, $dark);
    }

    private function alloc(\GdImage $img, array $rgb): int
    {
        return imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
    }

    private function toLatin1(string $s): string
    {
        if (function_exists('mb_convert_encoding')) {
            $r = @mb_convert_encoding($s, 'ISO-8859-1', 'UTF-8');
            if (is_string($r)) return $r;
        }
        if (function_exists('iconv')) {
            $r = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
            if (is_string($r)) return $r;
        }
        return strtr($s, [
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','à'=>'a','â'=>'a','ä'=>'a',
            'î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u',
            'ç'=>'c','É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E','À'=>'A','Â'=>'A',
            'Ä'=>'A','Î'=>'I','Ï'=>'I','Ô'=>'O','Ö'=>'O','Ù'=>'U','Û'=>'U',
            'Ü'=>'U','Ç'=>'C','œ'=>'oe','æ'=>'ae','Œ'=>'OE','Æ'=>'AE',
        ]);
    }
}
