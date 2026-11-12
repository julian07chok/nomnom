<?php

// ═══════════════════════════════════════════════════════════
// PHP 5.4 COMPATIBILITY LAYER
// ═══════════════════════════════════════════════════════════

if (!function_exists('random_bytes')) {
    function random_bytes($length) {
        if (function_exists('openssl_random_pseudo_bytes')) {
            $strong = false;
            $bytes = openssl_random_pseudo_bytes($length, $strong);
            if ($bytes !== false && $strong) {
                return $bytes;
            }
        }
        throw new RuntimeException(
            'No cryptographically secure random source available.'
        );
    }
}

if (!function_exists('hash_equals')) {
    function hash_equals($known_string, $user_string) {
        if (!is_string($known_string) || !is_string($user_string)) {
            return false;
        }
        $known_len = strlen($known_string);
        $user_len = strlen($user_string);

        if ($known_len !== $user_len) {
            return false;
        }

        $result = 0;
        for ($i = 0; $i < $known_len; $i++) {
            $result |= ord($known_string[$i]) ^ ord($user_string[$i]);
        }
        return $result === 0;
    }
}

if (!function_exists('password_hash')) {
    if (!defined('PASSWORD_BCRYPT')) {
        define('PASSWORD_BCRYPT', 1);
    }
    if (!defined('PASSWORD_DEFAULT')) {
        define('PASSWORD_DEFAULT', PASSWORD_BCRYPT);
    }

    function password_hash($password, $algo, $options = array()) {
        if ($algo !== PASSWORD_BCRYPT) {
            throw new RuntimeException(
                'Only PASSWORD_BCRYPT is supported on this server.'
            );
        }

        $cost = isset($options['cost']) ? (int) $options['cost'] : 10;
        if ($cost < 4 || $cost > 31) {
            $cost = 10;
        }

        $salt_alphabet = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $salt = '';
        for ($i = 0; $i < 22; $i++) {
            $salt .= $salt_alphabet[ord(random_bytes(1)) % 64];
        }

        return crypt($password, sprintf('$2y$%02d$', $cost) . $salt);
    }
}

if (!function_exists('password_verify')) {
    function password_verify($password, $hash) {
        if (!is_string($password) || !is_string($hash)) {
            return false;
        }
        return hash_equals($hash, crypt($password, $hash));
    }
}

// PHP 5.4 polyfill: array_column (added in PHP 5.5)
if (!function_exists('array_column')) {
    function array_column($input, $column_key, $index_key = null) {
        $result = array();

        if (!is_array($input)) {
            return $result;
        }

        foreach ($input as $row) {
            if (!is_array($row) || !isset($row[$column_key])) {
                continue;
            }

            if ($index_key !== null && isset($row[$index_key])) {
                $result[$row[$index_key]] = $row[$column_key];
            } else {
                $result[] = $row[$column_key];
            }
        }

        return $result;
    }
}

// PHP 5.4 polyfill: mb_strtolower (approximate, ASCII only)
if (!function_exists('mb_strtolower')) {
    function mb_strtolower($string, $encoding = 'UTF-8') {
        return strtolower($string);
    }
}

// PHP 5.4 polyfill: mb_strlen (approximate, byte length)
if (!function_exists('mb_strlen')) {
    function mb_strlen($string, $encoding = 'UTF-8') {
        return strlen($string);
    }
}

// ═══════════════════════════════════════════════════════════
// NOMNOM BOOTSTRAP (PHP 5.4 compatible)
// ═══════════════════════════════════════════════════════════

ini_set('display_errors', '0');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

session_name('nomnom_session');

session_set_cookie_params(
    0,
    '/',
    '',
    $isHttps,
    true
);

session_start();

function respond($data, $status = 200)
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

set_exception_handler(function ($error) {
    error_log('NomNom API: ' . $error->getMessage());

    respond(array(
        'ok' => false,
        'message' => 'A server error occurred. Check the Apache error log.',
    ), 500);
});

require __DIR__ . '/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function publicUser($row)
{
    return array(
        'id' => (int) $row['id'],
        'username' => $row['username'],
        'displayName' => $row['display_name'],
        'email' => $row['email'],
        'avatar' => isset($row['avatar_url']) ? $row['avatar_url'] : '',
        'bio' => isset($row['bio']) ? $row['bio'] : '',
        'createdAt' => str_replace(' ', 'T', $row['created_at']) . 'Z',
    );
}

function requirePost()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        respond(array(
            'ok' => false,
            'message' => 'Method not allowed.',
        ), 405);
    }

    $token = isset($_SERVER['HTTP_X_CSRF_TOKEN'])
        ? $_SERVER['HTTP_X_CSRF_TOKEN']
        : '';

    if (
        !is_string($token)
        || !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        respond(array(
            'ok' => false,
            'message' => 'Your security token expired. Refresh and try again.',
        ), 403);
    }
}

function readJsonBody()
{
    $data = json_decode(file_get_contents('php://input'), true);

    if (!is_array($data)) {
        respond(array(
            'ok' => false,
            'message' => 'Invalid request data.',
        ), 400);
    }

    return $data;
}

function stringField($data, $name)
{
    if (!isset($data[$name]) || !is_string($data[$name])) {
        respond(array(
            'ok' => false,
            'message' => 'Invalid or missing field: ' . $name,
        ), 422);
    }

    return $data[$name];
}

function startUserSession($userId)
{
    session_regenerate_id(true);

    $_SESSION = array(
        'user_id' => (int) $userId,
        'csrf_token' => bin2hex(random_bytes(32)),
    );
}

function findUserById($pdo, $userId)
{
    $statement = $pdo->prepare(
        'SELECT id, username, display_name, email,
                avatar_url, bio, created_at
         FROM nomnom_users
         WHERE id = ?'
    );

    $statement->execute(array($userId));

    return $statement->fetch();
}