<?php

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');

    respond([
        'ok' => false,
        'message' => 'Method not allowed.',
    ], 405);
}

$username = $_GET['username'] ?? '';

if (
    !is_string($username)
    || !preg_match('/\A[a-zA-Z0-9_]{3,50}\z/', $username)
) {
    respond([
        'ok' => false,
        'message' => 'Invalid username.',
    ], 422);
}

$statement = $pdo->prepare(
    'SELECT id, username, display_name, avatar_url, bio, created_at
     FROM nomnom_users
     WHERE username = ?
     LIMIT 1'
);

$statement->execute([strtolower($username)]);
$user = $statement->fetch();

if (!$user) {
    respond([
        'ok' => false,
        'message' => 'User not found.',
    ], 404);
}

// Only public profile information is returned.
// Do not use bootstrap's publicUser() here because it includes email.
respond([
    'ok' => true,
    'user' => [
        'id' => (int) $user['id'],
        'username' => $user['username'],
        'displayName' => $user['display_name'],
        'avatar' => $user['avatar_url'] ?? '',
        'bio' => $user['bio'] ?? '',
        'createdAt' => str_replace(' ', 'T', $user['created_at']) . 'Z',
    ],
]);
