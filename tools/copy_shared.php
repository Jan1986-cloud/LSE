<?php
declare(strict_types=1);

$serviceRoot = getcwd();
if ($serviceRoot === false) {
    fwrite(STDERR, "Unable to determine working directory.\n");
    exit(1);
}

$sharedSource = realpath(__DIR__ . '/../shared');
if ($sharedSource === false || !is_dir($sharedSource)) {
    fwrite(STDERR, "Shared directory not found.\n");
    exit(1);
}

$target = $serviceRoot . DIRECTORY_SEPARATOR . 'shared';

if (is_dir($target)) {
    deleteDirectory($target);
}

copyDirectory($sharedSource, $target);

echo "Shared assets copied to {$target}" . PHP_EOL;

function copyDirectory(string $source, string $destination): void
{
    if (!mkdir($destination, 0777, true) && !is_dir($destination)) {
        throw new RuntimeException("Failed to create directory: {$destination}");
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $destPath = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
        if ($item->isDir()) {
            if (!mkdir($destPath, 0777, true) && !is_dir($destPath)) {
                throw new RuntimeException("Failed to create directory: {$destPath}");
            }
        } else {
            if (!copy($item->getPathname(), $destPath)) {
                throw new RuntimeException("Failed to copy file to {$destPath}");
            }
        }
    }
}

function deleteDirectory(string $path): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            if (!rmdir($item->getPathname())) {
                throw new RuntimeException("Failed to remove directory: {$item->getPathname()}");
            }
        } else {
            if (!unlink($item->getPathname())) {
                throw new RuntimeException("Failed to remove file: {$item->getPathname()}");
            }
        }
    }

    if (!rmdir($path)) {
        throw new RuntimeException("Failed to remove directory: {$path}");
    }
}
