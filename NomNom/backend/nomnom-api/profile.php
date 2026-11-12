<?php

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    header('Allow: PUT');

    respond(array(
        'ok' => false,
        'message' => 'Method not allowed.',
    ), 405);
}

$token = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '';

if (
    !is_string($token)
    || !hash_equals($_SESSION['csrf_token'], $token)
) {
    respond(array(
        'ok' => false,
        'message' => 'Your security token expired. Refresh and try again.',
    ), 403);
}

$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

if (!$userId) {
    respond(array(
        'ok' => false,
        'message' => 'Please log in to update your profile.',
    ), 401);
}

$data = readJsonBody();

$displayName = trim(stringField($data, 'displayName'));
$bio = trim(stringField($data, 'bio'));

$nameLength = preg_match_all('/./us', $displayName);
$bioLength = preg_match_all('/./us', $bio);

if ($nameLength === false || $nameLength < 2 || $nameLength > 50) {
    respond(array(
        'ok' => false,
        'message' => 'Display name must contain 2–50 characters.',
    ), 422);
}

if ($bioLength === false || $bioLength > 500) {
    respond(array(
        'ok' => false,
        'message' => 'Bio must be 500 characters or fewer.',
    ), 422);
}

$changeAvatar = array_key_exists('avatar', $data);
$avatarValue = null;
$avatarBytes = null;
$extension = null;

if ($changeAvatar) {
    $avatarValue = trim(stringField($data, 'avatar'));

    if (strpos($avatarValue, 'data:') === 0) {
        if (
            strlen($avatarValue) > 2800000
            || !preg_match(
                '/\Adata:(image\/(?:jpeg|png|webp));base64,/',
                $avatarValue,
                $matches
            )
        ) {
            respond(array(
                'ok' => false,
                'message' => 'Use a JPG, PNG, or WebP avatar up to 2 MB.',
            ), 422);
        }

        $avatarBytes = base64_decode(
            substr($avatarValue, strlen($matches[0])),
            true
        );

        if (
            $avatarBytes === false
            || strlen($avatarBytes) < 1
            || strlen($avatarBytes) > 2 * 1024 * 1024
        ) {
            respond(array(
                'ok' => false,
                'message' => 'Avatar must be 2 MB or smaller.',
            ), 422);
        }

        $types = array(
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        );

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($avatarBytes);
        $info = @getimagesizefromstring($avatarBytes);

        if (
            !isset($types[$mimeType])
            || $mimeType !== $matches[1]
            || !$info
            || (!isset($info['mime']) || $info['mime'] !== $mimeType)
        ) {
            respond(array(
                'ok' => false,
                'message' => 'Please choose a valid image file.',
            ), 422);
        }

        if (
            $info[0] > 8000
            || $info[1] > 8000
            || $info[0] * $info[1] > 25000000
        ) {
            respond(array(
                'ok' => false,
                'message' => 'Avatar dimensions are too large. Resize it first.',
            ), 422);
        }

        $extension = $types[$mimeType];
    } elseif ($avatarValue !== '') {
        $scheme = strtolower(
            (string) parse_url($avatarValue, PHP_URL_SCHEME)
        );

        if (
            strlen($avatarValue) > 2048
            || !filter_var($avatarValue, FILTER_VALIDATE_URL)
            || !in_array($scheme, array('http', 'https'), true)
        ) {
            respond(array(
                'ok' => false,
                'message' => 'Enter a valid HTTP or HTTPS image URL.',
            ), 422);
        }
    }
}

$newPath = null;
$oldAvatar = '';
$newAvatar = '';
$user = null;
$error = null;

try {
    $pdo->beginTransaction();

    $statement = $pdo->prepare(
        'SELECT avatar_url
         FROM nomnom_users
         WHERE id = ?
         FOR UPDATE'
    );

    $statement->execute(array($userId));
    $existing = $statement->fetch();

    if (!$existing) {
        $pdo->rollBack();

        respond(array(
            'ok' => false,
            'message' => 'Account no longer exists. Please log in again.',
        ), 401);
    }

    $oldAvatar = isset($existing['avatar_url']) ? $existing['avatar_url'] : '';
    $newAvatar = $changeAvatar ? $avatarValue : $oldAvatar;

    if ($avatarBytes !== null) {
        $config = require __DIR__ . '/config.local.php';
        $directory = isset($config['upload_dir']) ? $config['upload_dir'] : '';

        if (
            !is_string($directory)
            || !is_dir($directory)
            || !is_writable($directory)
        ) {
            throw new RuntimeException('Upload directory is unavailable.');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $extension;

        $newPath = rtrim($directory, '/\\')
            . DIRECTORY_SEPARATOR
            . $filename;

        $written = file_put_contents(
            $newPath,
            $avatarBytes,
            LOCK_EX
        );

        if ($written !== strlen($avatarBytes)) {
            throw new RuntimeException('Could not save the avatar.');
        }

        $newAvatar = 'api/image.php?name=' . $filename;
    }

    $statement = $pdo->prepare(
        'UPDATE nomnom_users
         SET display_name = ?,
             bio = ?,
             avatar_url = ?,
             updated_at = UTC_TIMESTAMP()
         WHERE id = ?'
    );

    $statement->execute(array(
        $displayName,
        $bio,
        $newAvatar === '' ? null : $newAvatar,
        $userId,
    ));

    $user = findUserById($pdo, $userId);

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($newPath !== null && is_file($newPath)) {
        @unlink($newPath);
    }

    $error = $e;
}

if ($error !== null) {
    throw $error;
}

// Clean up the replaced avatar after the database save succeeds.
if ($changeAvatar && $oldAvatar !== $newAvatar) {
    try {
        if (preg_match(
            '/\Aapi\/image\.php\?name=([a-f0-9]{32}\.(?:jpg|png|webp))\z/',
            $oldAvatar,
            $matches
        )) {
            $check = $pdo->prepare(
                'SELECT
                    (SELECT COUNT(*) FROM nomnom_users WHERE avatar_url = ?)
                    +
                    (SELECT COUNT(*) FROM nomnom_post_images WHERE image_url = ?)
                    +
                    (SELECT COUNT(*) FROM nomnom_comment_images WHERE image_url = ?)'
            );

            $check->execute(array($oldAvatar, $oldAvatar, $oldAvatar));

            if ((int) $check->fetchColumn() === 0) {
                $config = require __DIR__ . '/config.local.php';
                $directory = isset($config['upload_dir']) ? $config['upload_dir'] : '';

                if (!is_string($directory) || !is_dir($directory)) {
                    throw new RuntimeException('Upload directory is unavailable.');
                }

                $path = rtrim($directory, '/\\')
                    . DIRECTORY_SEPARATOR
                    . $matches[1];

                if (is_file($path) && !@unlink($path)) {
                    error_log('NomNom: could not remove old avatar ' . $matches[1]);
                }
            }
        }
    } catch (Exception $e) {
        error_log('NomNom avatar cleanup: ' . $e->getMessage());
    }
}

respond(array(
    'ok' => true,
    'user' => publicUser($user),
    'csrfToken' => $_SESSION['csrf_token'],
));