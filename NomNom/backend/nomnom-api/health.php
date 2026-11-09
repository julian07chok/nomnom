<?php

ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    require __DIR__ . '/db.php';

    $statement = $pdo->query(
        'SELECT COUNT(*) FROM nomnom_users'
    );

    echo json_encode([
        'ok' => true,
        'message' => 'Connected to the local NomNom database.',
        'userCount' => (int) $statement->fetchColumn(),
    ]);
} catch (Throwable $error) {
    error_log('NomNom database connection: ' . $error->getMessage());

    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'message' => 'Database connection failed. Check the Apache error log.',
    ]);
}