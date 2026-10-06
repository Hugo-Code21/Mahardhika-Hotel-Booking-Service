<?php
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestPath = is_string($requestPath) ? rawurldecode($requestPath) : '/';
$normalizedPath = str_replace('\\', '/', $requestPath);
$segments = explode('/', trim($normalizedPath, '/'));
$blocked = false;

foreach ($segments as $segment) {
    if ($segment === '..' || ($segment !== '' && $segment[0] === '.')) {
        $blocked = true;
        break;
    }
}

$fileName = strtolower(basename($normalizedPath));
if (in_array($fileName, ['database.sqlite', 'database.sqlite-shm', 'database.sqlite-wal', 'database.sqlite-journal', 'cookies.txt', 'database.sql', 'migrate_sqlite_to_mysql.php'], true)) {
    $blocked = true;
}

if ($blocked) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Not found.';
    return true;
}

if ($normalizedPath !== '/') {
    $projectRoot = realpath(__DIR__);
    $requestedFile = $projectRoot === false
        ? false
        : realpath($projectRoot . DIRECTORY_SEPARATOR . ltrim($normalizedPath, '/'));
    $rootPrefix = $projectRoot === false
        ? ''
        : rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    if ($requestedFile !== false && stripos($requestedFile, $rootPrefix) === 0 && is_file($requestedFile)) {
        return false;
    }
}

require __DIR__ . '/index.php';
