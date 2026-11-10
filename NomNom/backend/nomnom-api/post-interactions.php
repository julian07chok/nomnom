<?php

require_once __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

if (!in_array($method, ['PUT', 'DELETE'], true)) {
    header('Allow: PUT, DELETE');

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

$userId = (int) ($_SESSION['user_id'] ?? 0);

if (!$userId || !findUserById($pdo, $userId)) {
    respond([
        'ok' => false,
        'message' => 'Please log in to continue.',
    ], 401);
}

$id = $_GET['id'] ?? '';
$type = $_GET['type'] ?? '';

$tables = [
    'like' => 'nomnom_post_likes',
    'save' => 'nomnom_saved_posts',
];

if (!is_string($id) || !ctype_digit($id) || (int) $id < 1) {
    respond([
        'ok' => false,
        'message' => 'Invalid post ID.',
    ], 400);
}

if (!is_string($type) || !isset($tables[$type])) {
    respond([
        'ok' => false,
        'message' => 'Invalid interaction type.',
    ], 400);
}

$postId = (int) $id;

// The table name comes only from our fixed whitelist.
$table = $tables[$type];

try {
    $pdo->beginTransaction();

    // Serialize interaction changes for this post.
    $statement = $pdo->prepare(
        'SELECT id FROM nomnom_posts WHERE id = ? FOR UPDATE'
    );

    $statement->execute([$postId]);

    if (!$statement->fetch()) {
        $pdo->rollBack();

        respond([
            'ok' => false,
            'message' => 'This post no longer exists.',
        ], 404);
    }

    if ($method === 'PUT') {
        $statement = $pdo->prepare(
            "INSERT INTO $table (user_id, post_id, created_at)
             VALUES (?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE created_at = created_at"
        );

        $statement->execute([$userId, $postId]);
    } else {
        $statement = $pdo->prepare(
            "DELETE FROM $table WHERE user_id = ? AND post_id = ?"
        );

        $statement->execute([$userId, $postId]);
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

    $statement->execute([
        $postId,
        $userId,
        $postId,
        $userId,
        $postId,
    ]);

    $state = $statement->fetch();

    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $error;
}

respond([
    'ok' => true,
    'post' => [
        'id' => $postId,
        'likes' => (int) $state['like_count'],
        'likedByMe' => (bool) $state['liked_by_me'],
        'savedByMe' => (bool) $state['saved_by_me'],
    ],
]);
