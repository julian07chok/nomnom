<?php

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');

    respond([
        'ok' => false,
        'message' => 'Method not allowed.',
    ], 405);
}

$user = null;

if (isset($_SESSION['user_id'])) {
    $statement = $pdo->prepare(
        'SELECT id, username, display_name, email,
                avatar_url, bio, created_at
         FROM nomnom_users
         WHERE id = ?'
    );

    $statement->execute([$_SESSION['user_id']]);
    $row = $statement->fetch();

    if ($row) {
        $user = publicUser($row);
    } else {
        unset($_SESSION['user_id']);
    }
}

respond([
    'ok' => true,
    'user' => $user,
    'csrfToken' => $_SESSION['csrf_token'],
]);