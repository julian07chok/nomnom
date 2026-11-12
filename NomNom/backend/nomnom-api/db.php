<?php

$configPath = __DIR__ . '/config.local.php';

if (!is_file($configPath)) {
    throw new RuntimeException(
        'Missing local database configuration.'
    );
}

$config = require $configPath;

$pdo = new PDO(
    'mysql:host=' . $config['host']
        . ';port=' . (int) $config['port']
        . ';dbname=' . $config['database']
        . ';charset=utf8mb4',
    $config['username'],
    $config['password'],
    array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    )
);

$pdo->exec("SET time_zone = '+00:00'");