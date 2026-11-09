<?php

require __DIR__ . '/bootstrap.php';

requirePost();
$data = readJsonBody();

$identifier = strtolower(trim(stringField($data, 'identifier')));
$password = stringField($data, 'password');

if (
    $identifier === ''
    || strlen($identifier) > 190
    || $password === ''
    || strlen($password) > 72
    || strpos($password, "\0") !== false
) {
    respond([
        'ok' => false,
        'message' => 'Incorrect email/username or password.',
    ], 401);
}

$statement = $pdo->prepare(
    'SELECT *
     FROM nomnom_users
     WHERE email = ? OR username = ?
     LIMIT 1'
);

$statement->execute([$identifier, $identifier]);
$user = $statement->fetch();

// A valid dummy bcrypt hash keeps password verification running
// even when no account matches.
$hash = $user
    ? $user['password_hash']
    : '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

$passwordMatches = password_verify($password, $hash);

if (!$user || !$passwordMatches) {
    respond([
        'ok' => false,
        'message' => 'Incorrect email/username or password.',
    ], 401);
}

startUserSession($user['id']);

respond([
    'ok' => true,
    'user' => publicUser($user),
    'csrfToken' => $_SESSION['csrf_token'],
]);