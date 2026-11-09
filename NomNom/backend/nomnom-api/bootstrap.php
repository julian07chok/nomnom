<?php

ini_set('display_errors', '0');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$isHttps = !empty($_SERVER['HTTPS'])
    && $_SERVER['HTTPS'] !== 'off';

session_name('nomnom_session');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

function respond($data, $status = 200)
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

set_exception_handler(function ($error) {
    error_log('NomNom API: ' . $error->getMessage());

    respond([
        'ok' => false,
        'message' => 'A server error occurred. Check the Apache error log.',
    ], 500);
});

require __DIR__ . '/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function publicUser($row)
{
    return [
        'id' => (int) $row['id'],
        'username' => $row['username'],
        'displayName' => $row['display_name'],
        'email' => $row['email'],
        'avatar' => $row['avatar_url'] ?? '',
        'bio' => $row['bio'] ?? '',
        'createdAt' => str_replace(' ', 'T', $row['created_at']) . 'Z',
    ];
}

// helpers check request method anf CRSF token,
// read JSOn and create a fresh authenticated session
function requirePost()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');

        respond([
            'ok' => false,
            'message' => 'Method not allowed.',
        ], 405);
    }

    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if (
        !is_string($token)
        || !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        respond([
            'ok' => false,
            'message' => 'Your security token expired. Refresh and try again.',
        ], 403);
    }
}

function readJsonBody()
{
    $data = json_decode(file_get_contents('php://input'), true);

    if (!is_array($data)) {
        respond([
            'ok' => false,
            'message' => 'Invalid request data.',
        ], 400);
    }

    return $data;
}

function stringField($data, $name)
{
    if (!isset($data[$name]) || !is_string($data[$name])) {
        respond([
            'ok' => false,
            'message' => 'Invalid or missing field: ' . $name,
        ], 422);
    }

    return $data[$name];
}

function startUserSession($userId)
{
    session_regenerate_id(true);

    $_SESSION = [
        'user_id' => (int) $userId,
        'csrf_token' => bin2hex(random_bytes(32)),
    ];
}

function findUserById($pdo, $userId)
{
    $statement = $pdo->prepare(
        'SELECT id, username, display_name, email,
                avatar_url, bio, created_at
         FROM nomnom_users
         WHERE id = ?'
    );

    $statement->execute([$userId]);

    return $statement->fetch();
}