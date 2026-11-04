<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;

final class PackageParityTest extends TestCase
{
    public function testRootAndPlaygroundContainTheSameFilesAndBytes(): void
    {
        $root = dirname(__DIR__);
        if (!is_dir($root . '/games/slave-rift')) $root = dirname(__DIR__, 3);
        // A standalone engine install has no authoring checkout. CI checks parity before staging it.
        if (!is_file($root . '/tools/sync-package.php')) {
            self::assertTrue(is_file(dirname(__DIR__) . '/src/Game/Run.php'));
            return;
        }
        $package = $root . '/games/slave-rift';
        // Blender .blend files are editable authoring sources, not runtime assets.
        self::assertDirectoryDoesNotExist($package . '/assets/generated/models');
        foreach (['src', 'config', 'tests', 'assets'] as $folder) {
            foreach ([[$root, $package], [$package, $root]] as [$source, $target]) {
                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source . '/' . $folder, \FilesystemIterator::SKIP_DOTS));
                foreach ($iterator as $file) {
                    if (!$file->isFile()) continue;
                    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($source) + 1));
                    if (str_starts_with($relative, 'assets/generated/models/')) continue;
                    self::assertFileExists($target . '/' . $relative);
                    self::assertSame(hash_file('sha256', $file->getPathname()), hash_file('sha256', $target . '/' . $relative), $relative);
                }
            }
        }
    }
}
