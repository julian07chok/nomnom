<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/post-photos.php';

function commentIdParameter($name)
{
    $value = isset($_GET[$name]) ? $_GET[$name] : '';

    if (!is_string($value) || !ctype_digit($value) || (int) $value < 1) {
        respond(array(
            'ok' => false,
            'message' => 'Invalid ' . $name . '.',
        ), 400);
    }

    return (int) $value;
}

function readPostComments($pdo, $postId)
{
    $statement = $pdo->prepare(
        'SELECT c.id, c.post_id, c.user_id, c.comment_text,
                c.created_at, c.updated_at,
                u.username, u.display_name, u.avatar_url
         FROM nomnom_comments c
         INNER JOIN nomnom_users u ON u.id = c.user_id
         WHERE c.post_id = ?
         ORDER BY c.created_at DESC, c.id DESC'
    );

    $statement->execute(array($postId));
    $rows = $statement->fetchAll();

    $statement = $pdo->prepare(
        'SELECT ci.comment_id, ci.image_url
         FROM nomnom_comment_images ci
         INNER JOIN nomnom_comments c ON c.id = ci.comment_id
         WHERE c.post_id = ?
         ORDER BY ci.comment_id, ci.sort_order, ci.id'
    );

    $statement->execute(array($postId));

    $images = array();

    foreach ($statement->fetchAll() as $image) {
        $cid = (int) $image['comment_id'];
        if (!isset($images[$cid])) {
            $images[$cid] = array();
        }
        $images[$cid][] = $image['image_url'];
    }

    $comments = array();

    foreach ($rows as $row) {
        $id = (int) $row['id'];

        $comments[] = array(
            'id' => $id,
            'postId' => (int) $row['post_id'],
            'userId' => (int) $row['user_id'],
            'userName' => $row['username'],
            'displayName' => $row['display_name'],
            'userAvatar' => isset($row['avatar_url']) ? $row['avatar_url'] : '',
            'text' => $row['comment_text'],
            'images' => isset($images[$id]) ? $images[$id] : array(),
            'createdAt' => str_replace(' ', 'T', $row['created_at']) . 'Z',
            'updatedAt' => str_replace(' ', 'T', $row['updated_at']) . 'Z',
        );
    }

    return $comments;
}

$method = $_SERVER['REQUEST_METHOD'];

if (!in_array($method, array('GET', 'POST', 'PUT', 'DELETE'), true)) {
    header('Allow: GET, POST, PUT, DELETE');

    respond(array(
        'ok' => false,
        'message' => 'Method not allowed.',
    ), 405);
}

if ($method === 'GET') {
    $postId = commentIdParameter('post_id');

    $statement = $pdo->prepare(
        'SELECT id FROM nomnom_posts WHERE id = ?'
    );

    $statement->execute(array($postId));

    if (!$statement->fetch()) {
        respond(array(
            'ok' => false,
            'message' => 'Post not found.',
        ), 404);
    }

    respond(array(
        'ok' => true,
        'comments' => readPostComments($pdo, $postId),
    ));
}

// All writes require a session and CSRF token.
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

if (!$userId || !findUserById($pdo, $userId)) {
    respond(array(
        'ok' => false,
        'message' => 'Please log in to comment.',
    ), 401);
}

$photoPlan = array();
$newPaths = array();
$removedUrls = array();
$commentId = null;
$text = '';
$data = array();

if ($method === 'POST' || $method === 'PUT') {
    $data = readJsonBody();
    $text = trim(stringField($data, 'text'));
    $length = preg_match_all('/./us', $text);

    if ($length === false || $length > 500) {
        respond(array(
            'ok' => false,
            'message' => 'Comment must be 500 characters or fewer.',
        ), 422);
    }
}

if ($method === 'POST') {
    $postId = commentIdParameter('post_id');
    $images = isset($data['images']) ? $data['images'] : array();

    if (
        !is_array($images)
        || count($images) > 3
        || ($images && array_keys($images) !== range(0, count($images) - 1))
    ) {
        respond(array(
            'ok' => false,
            'message' => 'You can attach up to three photos.',
        ), 422);
    }

    if ($text === '' && !$images) {
        respond(array(
            'ok' => false,
            'message' => 'Write a comment or attach a photo.',
        ), 422);
    }

    if ($images) {
        try {
            $photoPlan = parsePhotoPlan(array(
                'photos' => array_map(function ($image) {
                    return array('dataUrl' => $image);
                }, $images),
            ));
        } catch (InvalidArgumentException $error) {
            respond(array(
                'ok' => false,
                'message' => $error->getMessage(),
            ), 422);
        }
    }
} else {
    $commentId = commentIdParameter('id');

    $statement = $pdo->prepare(
        'SELECT post_id FROM nomnom_comments WHERE id = ?'
    );

    $statement->execute(array($commentId));
    $found = $statement->fetch();

    if (!$found) {
        respond(array(
            'ok' => false,
            'message' => 'Comment not found.',
        ), 404);
    }

    $postId = (int) $found['post_id'];
}

// ── PHP 5.4: no finally, no Throwable. Use catch(Exception) ──
$comments = null;
$error = null;

try {
    $pdo->beginTransaction();

    $statement = $pdo->prepare(
        'SELECT id FROM nomnom_posts WHERE id = ? FOR UPDATE'
    );

    $statement->execute(array($postId));

    if (!$statement->fetch()) {
        $pdo->rollBack();

        respond(array(
            'ok' => false,
            'message' => 'Post no longer exists.',
        ), 404);
    }

    if ($method !== 'POST') {
        $statement = $pdo->prepare(
            'SELECT user_id
             FROM nomnom_comments
             WHERE id = ?
             FOR UPDATE'
        );

        $statement->execute(array($commentId));
        $existing = $statement->fetch();

        if (!$existing) {
            $pdo->rollBack();

            respond(array(
                'ok' => false,
                'message' => 'Comment no longer exists.',
            ), 404);
        }

        if ((int) $existing['user_id'] !== $userId) {
            $pdo->rollBack();

            respond(array(
                'ok' => false,
                'message' => 'You can only edit or delete your own comments.',
            ), 403);
        }
    }

    if ($method === 'POST') {
        $statement = $pdo->prepare(
            'INSERT INTO nomnom_comments
                (post_id, user_id, comment_text, created_at, updated_at)
             VALUES (?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        );

        $statement->execute(array($postId, $userId, $text));
        $commentId = (int) $pdo->lastInsertId();

        if ($photoPlan) {
            $config = require __DIR__ . '/config.local.php';
            $directory = isset($config['upload_dir']) ? $config['upload_dir'] : '';

            if (
                !is_string($directory)
                || !is_dir($directory)
                || !is_writable($directory)
            ) {
                throw new RuntimeException('Upload directory is unavailable.');
            }

            $statement = $pdo->prepare(
                'INSERT INTO nomnom_comment_images
                    (comment_id, image_url, sort_order)
                 VALUES (?, ?, ?)'
            );

            foreach ($photoPlan as $order => $photo) {
                $filename = bin2hex(random_bytes(16))
                    . '.' . $photo['extension'];

                $path = rtrim($directory, '/\\')
                    . DIRECTORY_SEPARATOR
                    . $filename;

                $newPaths[] = $path;

                $written = file_put_contents(
                    $path,
                    $photo['bytes'],
                    LOCK_EX
                );

                if ($written !== strlen($photo['bytes'])) {
                    throw new RuntimeException('Could not save a comment photo.');
                }

                $statement->execute(array(
                    $commentId,
                    'nomnom-api/image.php?name=' . $filename,
                    $order,
                ));
            }
        }
    } elseif ($method === 'PUT') {
        $statement = $pdo->prepare(
            'SELECT COUNT(*)
             FROM nomnom_comment_images
             WHERE comment_id = ?'
        );

        $statement->execute(array($commentId));

        if ($text === '' && (int) $statement->fetchColumn() === 0) {
            $pdo->rollBack();

            respond(array(
                'ok' => false,
                'message' => 'A comment without photos cannot be empty.',
            ), 422);
        }

        $statement = $pdo->prepare(
            'UPDATE nomnom_comments
             SET comment_text = ?, updated_at = UTC_TIMESTAMP()
             WHERE id = ? AND user_id = ?'
        );

        $statement->execute(array($text, $commentId, $userId));
    } else {
        $statement = $pdo->prepare(
            'SELECT image_url
             FROM nomnom_comment_images
             WHERE comment_id = ?'
        );

        $statement->execute(array($commentId));
        $removedUrls = $statement->fetchAll(PDO::FETCH_COLUMN);

        $statement = $pdo->prepare(
            'DELETE FROM nomnom_comments WHERE id = ? AND user_id = ?'
        );

        $statement->execute(array($commentId, $userId));
    }

    $comments = readPostComments($pdo, $postId);

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    foreach ($newPaths as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    $error = $e;
}

if ($error !== null) {
    throw $error;
}

// Best-effort file cleanup after the deletion is committed.
if ($removedUrls) {
    try {
        $config = require __DIR__ . '/config.local.php';
        $directory = isset($config['upload_dir']) ? $config['upload_dir'] : '';

        if (!is_string($directory) || !is_dir($directory)) {
            throw new RuntimeException('Upload directory is unavailable.');
        }

        $check = $pdo->prepare(
            'SELECT
                (SELECT COUNT(*) FROM nomnom_post_images WHERE image_url = ?)
                +
                (SELECT COUNT(*) FROM nomnom_comment_images WHERE image_url = ?)
                +
                (SELECT COUNT(*) FROM nomnom_users WHERE avatar_url = ?)'
        );

        foreach (array_unique($removedUrls) as $url) {
            if (!preg_match(
                '/\Aapi\/image\.php\?name=([a-f0-9]{32}\.(?:jpg|png|webp))\z/',
                $url,
                $matches
            )) {
                continue;
            }

            $check->execute(array($url, $url, $url));

            if ((int) $check->fetchColumn() > 0) {
                continue;
            }

            $path = rtrim($directory, '/\\')
                . DIRECTORY_SEPARATOR
                . $matches[1];

            if (is_file($path) && !@unlink($path)) {
                error_log('NomNom: could not remove comment photo ' . $matches[1]);
            }
        }
    } catch (Exception $e) {
        error_log('NomNom comment cleanup: ' . $e->getMessage());
    }
}

respond(array(
    'ok' => true,
    'comments' => $comments,
), $method === 'POST' ? 201 : 200);