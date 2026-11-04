<?php

/** Keep the exact playground entry point synchronized with root sources. */
$root = dirname(__DIR__);
$package = $root . '/games/slave-rift';
$check = in_array('--check', $argv, true);
$files = ['app.php', 'composer.json', 'LICENSE'];
foreach (['src', 'config', 'assets', 'tests', 'patches'] as $folder) {
    $dir = $root . '/' . $folder;
    if (!is_dir($dir)) continue;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) if ($file->isFile() && !str_contains($file->getPathname(),'/generated/models/') && !str_contains($file->getPathname(),'/generated/.work/')) $files[] = substr($file->getPathname(), strlen($root) + 1);
}
$errors = [];
foreach ($files as $file) {
    $source = $root . '/' . $file;
    $target = $package . '/' . $file;
    if ($check) {
        if (!is_file($target) || hash_file('sha256', $source) !== hash_file('sha256', $target)) $errors[] = $file;
    } else {
        if (!is_dir(dirname($target))) mkdir(dirname($target), 0777, true);
        if (!copy($source, $target)) throw new RuntimeException('Cannot copy ' . $file);
    }
}
// Runtime trees must match in both directions. Historical package-only patch archives are retained.
foreach (['src', 'config', 'assets', 'tests'] as $folder) {
    $dir = $package . '/' . $folder;
    if (!is_dir($dir)) continue;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile()) continue;
        $relative = substr($file->getPathname(), strlen($package) + 1);
        if (!is_file($root . '/' . $relative)) $errors[] = 'stale: ' . $relative;
    }
}
if ($errors !== []) {
    fwrite(STDERR, "Playground copy differs: " . implode(', ', $errors) . "\n");
    exit(1);
}
echo ($check ? 'Verified ' : 'Synchronized ') . count($files) . " playground files.\n";
