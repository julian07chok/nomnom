<?php

require_once __DIR__ . '/bootstrap.php';

requirePost();

$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

if (!$userId) {
    respond(array(
        'ok' => false,
        'message' => 'Please log in.',
    ), 401);
}

$data = readJsonBody();
$type = isset($data['type']) ? $data['type'] : null;

// Record a viewed post.
if ($type === 'view') {
    $postId = isset($data['postId']) ? $data['postId'] : null;

    if (!is_int($postId) || $postId < 1) {
        respond(array(
            'ok' => false,
            'message' => 'Invalid post ID.',
        ), 422);
    }

    $statement = $pdo->prepare(
        'SELECT author_id
         FROM nomnom_posts
         WHERE id = ?'
    );

    $statement->execute(array($postId));
    $post = $statement->fetch();

    if (!$post) {
        respond(array(
            'ok' => false,
            'message' => 'Post not found.',
        ), 404);
    }

    if ((int) $post['author_id'] === $userId) {
        respond(array(
            'ok' => true,
            'recorded' => false,
        ));
    }

    $statement = $pdo->prepare(
        'INSERT INTO nomnom_post_views
            (user_id, post_id, view_days, last_viewed_at)
         VALUES (?, ?, 1, UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
            view_days = LEAST(
                30,
                view_days + IF(
                    DATE(last_viewed_at) < UTC_DATE(),
                    1,
                    0
                )
            ),
            last_viewed_at = UTC_TIMESTAMP()'
    );

    $statement->execute(array($userId, $postId));

    respond(array(
        'ok' => true,
        'recorded' => true,
    ));
}

// Record a search keyword.
if ($type === 'search') {
    $query = stringField($data, 'query');

    $query = preg_replace('/\s+/u', ' ', trim($query));

    if ($query === null) {
        respond(array(
            'ok' => false,
            'message' => 'Invalid search text.',
        ), 422);
    }

    // PHP 5.4 doesn't always have mbstring — use fallback
    if (function_exists('mb_strtolower')) {
        $query = mb_strtolower($query, 'UTF-8');
        $length = mb_strlen($query, 'UTF-8');
    } else {
        $query = strtolower($query);
        $length = strlen($query);
    }

    if ($length < 2 || $length > 100) {
        respond(array(
            'ok' => false,
            'message' => 'Search text must contain 2–100 characters.',
        ), 422);
    }

    $statement = $pdo->prepare(
        'INSERT INTO nomnom_search_history
            (user_id, search_query, search_days, last_searched_at)
         VALUES (?, ?, 1, UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
            search_days = LEAST(
                30,
                search_days + IF(
                    DATE(last_searched_at) < UTC_DATE(),
                    1,
                    0
                )
            ),
            last_searched_at = UTC_TIMESTAMP()'
    );

    $statement->execute(array($userId, $query));

    respond(array(
        'ok' => true,
        'recorded' => true,
    ));
}

respond(array(
    'ok' => false,
    'message' => 'Invalid interaction type.',
), 422);