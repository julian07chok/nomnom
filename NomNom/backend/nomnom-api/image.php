<?php

ini_set('display_errors', '0');

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit;
}

$name = $_GET['name'] ?? '';

if (
    !is_string($name)
    || !preg_match('/\A[a-f0-9]{32}\.(jpg|png|webp)\z/', $name)
) {
    http_response_code(404);
    exit;
}

$config = require __DIR__ . '/config.local.php';
$directory = $config['upload_dir'] ?? '';

if (!is_string($directory) || !is_dir($directory)) {
    http_response_code(503);
    exit;
}

$path = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $name;

if (!is_file($path)) {
    http_response_code(404);
    exit;
}

$contentTypes = [
    'jpg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
];

$extension = pathinfo($name, PATHINFO_EXTENSION);

header('Content-Type: ' . $contentTypes[$extension]);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=86400');

if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    exit;
}

readfile($path);