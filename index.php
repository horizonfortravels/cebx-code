<?php

declare(strict_types=1);

$requestPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$normalizedPath = trim(str_replace('\\', '/', $requestPath), '/');

if ($normalizedPath !== '' && !str_contains($normalizedPath, '..')) {
    $publicFile = resolvePublicFile($normalizedPath);

    if ($publicFile !== null && is_file($publicFile)) {
        servePublicFile($publicFile);

        return;
    }
}

require __DIR__ . '/public/index.php';

/**
 * Serve static assets from the public directory when the project root is the web root.
 */
function servePublicFile(string $path): void
{
    $mimeTypes = [
        'avif' => 'image/avif',
        'css' => 'text/css; charset=UTF-8',
        'gif' => 'image/gif',
        'ico' => 'image/x-icon',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'js' => 'application/javascript; charset=UTF-8',
        'json' => 'application/json; charset=UTF-8',
        'map' => 'application/json; charset=UTF-8',
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'svg' => 'image/svg+xml',
        'txt' => 'text/plain; charset=UTF-8',
        'webmanifest' => 'application/manifest+json; charset=UTF-8',
        'webp' => 'image/webp',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
    ];

    $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
    $mimeType = $mimeTypes[$extension] ?? null;

    if ($mimeType === null && function_exists('mime_content_type')) {
        $detected = mime_content_type($path);
        $mimeType = is_string($detected) && $detected !== '' ? $detected : null;
    }

    header('Content-Type: ' . ($mimeType ?? 'application/octet-stream'));
    header('Content-Length: ' . (string) filesize($path));
    header('Cache-Control: public, max-age=3600');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', (int) filemtime($path)) . ' GMT');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
        readfile($path);
    }
}

/**
 * Resolve request paths to safe, publicly serveable files.
 */
function resolvePublicFile(string $normalizedPath): ?string
{
    $blockedExtensions = [
        'bat',
        'cgi',
        'cmd',
        'phar',
        'php',
        'php3',
        'php4',
        'php5',
        'phtml',
        'pl',
        'ps1',
        'py',
        'sh',
    ];

    $filename = basename($normalizedPath);
    $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

    if ($filename === '' || str_starts_with($filename, '.') || in_array($extension, $blockedExtensions, true)) {
        return null;
    }

    $publicCandidate = __DIR__ . '/public/' . $normalizedPath;

    if (is_file($publicCandidate)) {
        return $publicCandidate;
    }

    if (str_starts_with($normalizedPath, 'storage/')) {
        $storageRelativePath = substr($normalizedPath, strlen('storage/'));
        $storageCandidate = __DIR__ . '/storage/app/public/' . $storageRelativePath;

        if ($storageRelativePath !== '' && is_file($storageCandidate)) {
            return $storageCandidate;
        }
    }

    return null;
}
