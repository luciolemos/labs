#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Convert image files to WebP using GD.
 *
 * Usage examples:
 *   php scripts/convert-webp.php
 *   php scripts/convert-webp.php --path=public/assets/img --quality=82
 *   php scripts/convert-webp.php --path=public/assets/img --dry-run
 *   php scripts/convert-webp.php --path=public/assets/img --force
 *   php scripts/convert-webp.php --path=public/assets/img --no-recursive
 *   php scripts/convert-webp.php --extensions=png,jpg,jpeg
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run in CLI mode.\n");
    exit(1);
}

$options = getopt('', [
    'path::',
    'quality::',
    'dry-run',
    'force',
    'no-recursive',
    'extensions::',
    'help',
]);

if (isset($options['help'])) {
    echo <<<TXT
Convert images to WebP using GD.

Options:
  --path=DIR           Base directory to scan (default: public/assets/img)
  --quality=0-100      WebP quality (default: 82)
  --dry-run            Show what would be converted without writing files
  --force              Recreate .webp even if it already exists
  --no-recursive       Do not scan subdirectories
  --extensions=list    Comma-separated extensions (default: png,jpg,jpeg)
  --help               Show this help

TXT;
    exit(0);
}

if (!extension_loaded('gd')) {
    fwrite(STDERR, "GD extension is required.\n");
    exit(1);
}

if (!function_exists('imagewebp')) {
    fwrite(STDERR, "This PHP build does not support imagewebp().\n");
    exit(1);
}

$basePath = (string)($options['path'] ?? 'public/assets/img');
$quality = (int)($options['quality'] ?? 82);
$dryRun = isset($options['dry-run']);
$force = isset($options['force']);
$recursive = !isset($options['no-recursive']);

if ($quality < 0 || $quality > 100) {
    fwrite(STDERR, "Invalid --quality value. Use 0-100.\n");
    exit(1);
}

$defaultExtensions = ['png', 'jpg', 'jpeg'];
$extensions = $defaultExtensions;
if (isset($options['extensions']) && is_string($options['extensions']) && trim($options['extensions']) !== '') {
    $parts = array_map('trim', explode(',', strtolower($options['extensions'])));
    $parts = array_values(array_filter($parts, static fn(string $ext): bool => $ext !== ''));
    if ($parts !== []) {
        $extensions = array_values(array_unique($parts));
    }
}

$basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
if (!is_dir($basePath)) {
    fwrite(STDERR, "Directory not found: {$basePath}\n");
    exit(1);
}

$stats = [
    'scanned' => 0,
    'converted' => 0,
    'skipped' => 0,
    'errors' => 0,
    'bytes_before' => 0,
    'bytes_after' => 0,
];

$iterator = $recursive
    ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS))
    : new IteratorIterator(new DirectoryIterator($basePath));

foreach ($iterator as $fileInfo) {
    if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
        continue;
    }

    $stats['scanned']++;
    $ext = strtolower($fileInfo->getExtension());
    if (!in_array($ext, $extensions, true)) {
        continue;
    }

    $srcPath = $fileInfo->getPathname();
    $dstPath = preg_replace('/\.[^.]+$/', '.webp', $srcPath);
    if (!is_string($dstPath)) {
        fwrite(STDERR, "[error] Failed to resolve destination path for: {$srcPath}\n");
        $stats['errors']++;
        continue;
    }

    if (is_file($dstPath) && !$force) {
        $stats['skipped']++;
        echo "[skip] {$srcPath} -> {$dstPath} (already exists)\n";
        continue;
    }

    $sourceSize = filesize($srcPath);
    if ($sourceSize === false) {
        fwrite(STDERR, "[error] Could not read source size: {$srcPath}\n");
        $stats['errors']++;
        continue;
    }

    if ($dryRun) {
        $stats['converted']++;
        $stats['bytes_before'] += $sourceSize;
        echo "[dry ] {$srcPath} -> {$dstPath}\n";
        continue;
    }

    $image = null;
    if ($ext === 'png') {
        $image = @imagecreatefrompng($srcPath);
        if ($image !== false) {
            imagepalettetotruecolor($image);
            imagealphablending($image, true);
            imagesavealpha($image, true);
        }
    } elseif ($ext === 'jpg' || $ext === 'jpeg') {
        $image = @imagecreatefromjpeg($srcPath);
    }

    if ($image === false || $image === null) {
        fwrite(STDERR, "[error] Unsupported/corrupt image: {$srcPath}\n");
        $stats['errors']++;
        continue;
    }

    $ok = @imagewebp($image, $dstPath, $quality);
    imagedestroy($image);

    if (!$ok || !is_file($dstPath)) {
        fwrite(STDERR, "[error] Failed converting: {$srcPath}\n");
        $stats['errors']++;
        continue;
    }

    $targetSize = filesize($dstPath);
    if ($targetSize === false) {
        fwrite(STDERR, "[error] Could not read target size: {$dstPath}\n");
        $stats['errors']++;
        continue;
    }

    $stats['converted']++;
    $stats['bytes_before'] += $sourceSize;
    $stats['bytes_after'] += $targetSize;

    $saved = $sourceSize - $targetSize;
    $pct = $sourceSize > 0 ? (100 * $saved / $sourceSize) : 0;
    printf(
        "[ok  ] %s -> %s | %s -> %s | saved %s (%.1f%%)\n",
        $srcPath,
        $dstPath,
        formatBytes($sourceSize),
        formatBytes($targetSize),
        formatBytes(max(0, $saved)),
        $pct
    );
}

$effectiveAfter = $dryRun ? 0 : $stats['bytes_after'];
$savedTotal = max(0, $stats['bytes_before'] - $effectiveAfter);
$pctTotal = $stats['bytes_before'] > 0 ? (100 * $savedTotal / $stats['bytes_before']) : 0;

echo "\nSummary\n";
echo "  scanned:   {$stats['scanned']}\n";
echo "  converted: {$stats['converted']}\n";
echo "  skipped:   {$stats['skipped']}\n";
echo "  errors:    {$stats['errors']}\n";
if ($stats['converted'] > 0) {
    if ($dryRun) {
        echo "  total source (would process): " . formatBytes($stats['bytes_before']) . "\n";
    } else {
        echo "  total before: " . formatBytes($stats['bytes_before']) . "\n";
        echo "  total after:  " . formatBytes($stats['bytes_after']) . "\n";
        echo "  total saved:  " . formatBytes($savedTotal) . sprintf(" (%.1f%%)\n", $pctTotal);
    }
}

exit($stats['errors'] > 0 ? 2 : 0);

function formatBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $value = (float)$bytes;
    $idx = 0;
    while ($value >= 1024 && $idx < count($units) - 1) {
        $value /= 1024;
        $idx++;
    }
    return sprintf('%.2f %s', $value, $units[$idx]);
}
