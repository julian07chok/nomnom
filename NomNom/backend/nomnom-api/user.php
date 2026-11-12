<?php

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');

    respond(array(
        'ok' => false,
        'message' => 'Method not allowed.',
    ), 405);
}

$username = isset($_GET['username']) ? $_GET['username'] : '';

if (
    !is_string($username)
    || !preg_match('/\A[a-zA-Z0-9_]{3,50}\z/', $username)
) {
    respond(array(
        'ok' => false,
        'message' => 'Invalid username.',
    ), 422);
}

$statement = $pdo->prepare(
    'SELECT id, username, display_name, avatar_url, bio, created_at
     FROM nomnom_users
     WHERE username = ?
     LIMIT 1'
);

$statement->execute(array(strtolower($username)));
$user = $statement->fetch();

if (!$user) {
    respond(array(
        'ok' => false,
        'message' => 'User not found.',
    ), 404);
}

respond(array(
    'ok' => true,
    'user' => array(
        'id' => (int) $user['id'],
        'username' => $user['username'],
        'displayName' => $user['display_name'],
        'avatar' => isset($user['avatar_url']) ? $user['avatar_url'] : '',
        'bio' => isset($user['bio']) ? $user['bio'] : '',
        'createdAt' => str_replace(' ', 'T', $user['created_at']) . 'Z',
    ),
));