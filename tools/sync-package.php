<?php

/** Keep the exact playground entry point synchronized with root sources. */
$root = dirname(__DIR__);
$package = $root . '/games/slave-rift';
$check = in_array('--check', $argv, true);
$files = ['app.php', 'composer.json', 'LICENSE'];
foreach (['src', 'config', 'assets', 'tests'] as $folder) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $folder, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) if ($file->isFile()) $files[] = substr($file->getPathname(), strlen($root) + 1);
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
if ($errors !== []) {
    fwrite(STDERR, "Playground copy differs: " . implode(', ', $errors) . "\n");
    exit(1);
}
echo ($check ? 'Verified ' : 'Synchronized ') . count($files) . " playground files.\n";
