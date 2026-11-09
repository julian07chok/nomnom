<?php

require __DIR__ . '/bootstrap.php';

requirePost();
$data = readJsonBody();

$originalUsername = trim(stringField($data, 'username'));
$username = strtolower($originalUsername);
$email = strtolower(trim(stringField($data, 'email')));
$password = stringField($data, 'password');
$confirmation = stringField($data, 'confirmPassword');

if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
    respond([
        'ok' => false,
        'message' => 'Username must contain 3–20 letters, numbers, or underscores.',
    ], 422);
}

if (
    strlen($email) > 190
    || !filter_var($email, FILTER_VALIDATE_EMAIL)
) {
    respond([
        'ok' => false,
        'message' => 'Enter a valid email address of 190 characters or fewer.',
    ], 422);
}

if (
    !preg_match('/^.{8,}$/us', $password)
    || strlen($password) > 72
    || strpos($password, "\0") !== false
    || !preg_match('/[A-Za-z]/', $password)
    || !preg_match('/[0-9]/', $password)
) {
    respond([
        'ok' => false,
        'message' => 'Password must have at least 8 characters, include letters and numbers, and fit within 72 bytes.',
    ], 422);
}

if ($password !== $confirmation) {
    respond([
        'ok' => false,
        'message' => 'Passwords do not match.',
    ], 422);
}

if (($data['acceptedTerms'] ?? false) !== true) {
    respond([
        'ok' => false,
        'message' => 'Please accept the terms to create an account.',
    ], 422);
}

$passwordHash = password_hash($password, PASSWORD_BCRYPT);

try {
    $statement = $pdo->prepare(
        'INSERT INTO nomnom_users
            (username, display_name, email, password_hash,
             created_at, updated_at)
         VALUES (?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
    );

    $statement->execute([
        $username,
        $originalUsername,
        $email,
        $passwordHash,
    ]);
} catch (PDOException $error) {
    if ((int) ($error->errorInfo[1] ?? 0) === 1062) {
        respond([
            'ok' => false,
            'message' => 'That username or email is already registered.',
        ], 409);
    }

    throw $error;
}

$userId = (int) $pdo->lastInsertId();
$user = findUserById($pdo, $userId);

startUserSession($userId);

respond([
    'ok' => true,
    'user' => publicUser($user),
    'csrfToken' => $_SESSION['csrf_token'],
], 201);