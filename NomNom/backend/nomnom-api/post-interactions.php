<?php

require_once __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

if (!in_array($method, array('PUT', 'DELETE'), true)) {
    header('Allow: PUT, DELETE');

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

if (!$userId || !findUserById($pdo, $userId)) {
    respond(array(
        'ok' => false,
        'message' => 'Please log in to continue.',
    ), 401);
}

$id = isset($_GET['id']) ? $_GET['id'] : '';
$type = isset($_GET['type']) ? $_GET['type'] : '';

$tables = array(
    'like' => 'nomnom_post_likes',
    'save' => 'nomnom_saved_posts',
);

if (!is_string($id) || !ctype_digit($id) || (int) $id < 1) {
    respond(array(
        'ok' => false,
        'message' => 'Invalid post ID.',
    ), 400);
}

if (!is_string($type) || !isset($tables[$type])) {
    respond(array(
        'ok' => false,
        'message' => 'Invalid interaction type.',
    ), 400);
}

$postId = (int) $id;
$table = $tables[$type];
$error = null;
$state = null;

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
            'message' => 'This post no longer exists.',
        ), 404);
    }

    if ($method === 'PUT') {
        $statement = $pdo->prepare(
            "INSERT INTO $table (user_id, post_id, created_at)
             VALUES (?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE created_at = created_at"
        );

        $statement->execute(array($userId, $postId));
    } else {
        $statement = $pdo->prepare(
            "DELETE FROM $table WHERE user_id = ? AND post_id = ?"
        );

        $statement->execute(array($userId, $postId));
    }

    $statement = $pdo->prepare(
        'SELECT
            (
                SELECT COUNT(*)
                FROM nomnom_post_likes
                WHERE post_id = ?
            ) AS like_count,

            EXISTS (
                SELECT 1
                FROM nomnom_post_likes
                WHERE user_id = ? AND post_id = ?
            ) AS liked_by_me,

            EXISTS (
                SELECT 1
                FROM nomnom_saved_posts
                WHERE user_id = ? AND post_id = ?
            ) AS saved_by_me'
    );

    $statement->execute(array(
        $postId,
        $userId,
        $postId,
        $userId,
        $postId,
    ));

    $state = $statement->fetch();

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $error = $e;
}

if ($error !== null) {
    throw $error;
}

respond(array(
    'ok' => true,
    'post' => array(
        'id' => $postId,
        'likes' => (int) $state['like_count'],
        'likedByMe' => (bool) $state['liked_by_me'],
        'savedByMe' => (bool) $state['saved_by_me'],
    ),
));