<?php

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/create-post.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET, POST');

    respond([
        'ok' => false,
        'message' => 'Method not allowed.',
    ], 405);
}

// Guests can read posts.
// Logged-in users also receive their own like/save status.
$viewerId = (int) ($_SESSION['user_id'] ?? 0);

// Optional ID for reading one specific post.
$postId = null;

if (isset($_GET['id'])) {
    if (
        !is_string($_GET['id'])
        || !ctype_digit($_GET['id'])
        || (int) $_GET['id'] < 1
    ) {
        respond([
            'ok' => false,
            'message' => 'Invalid post ID.',
        ], 400);
    }

    $postId = (int) $_GET['id'];
}

$sql = '
    SELECT
        p.*,
        u.username AS author_name,
        u.display_name AS author_display_name,
        u.avatar_url AS author_avatar,

        (
            SELECT COUNT(*)
            FROM nomnom_post_likes likes
            WHERE likes.post_id = p.id
        ) AS like_count,

        EXISTS (
            SELECT 1
            FROM nomnom_post_likes my_like
            WHERE my_like.post_id = p.id
              AND my_like.user_id = ?
        ) AS liked_by_me,

        EXISTS (
            SELECT 1
            FROM nomnom_saved_posts my_save
            WHERE my_save.post_id = p.id
              AND my_save.user_id = ?
        ) AS saved_by_me

    FROM nomnom_posts p
    INNER JOIN nomnom_users u ON u.id = p.author_id
';

$parameters = [$viewerId, $viewerId];

if ($postId !== null) {
    $sql .= ' WHERE p.id = ?';
    $parameters[] = $postId;
}

$sql .= ' ORDER BY p.created_at DESC, p.id DESC';

$statement = $pdo->prepare($sql);
$statement->execute($parameters);

$rows = $statement->fetchAll();

if ($postId !== null && !$rows) {
    respond([
        'ok' => false,
        'message' => 'Post not found.',
    ], 404);
}

// Fetch images in one query, rather than one query per post.
$imageSql = '
    SELECT post_id, image_url
    FROM nomnom_post_images
';

$imageParameters = [];

if ($postId !== null) {
    $imageSql .= ' WHERE post_id = ?';
    $imageParameters[] = $postId;
}

$imageSql .= ' ORDER BY post_id, sort_order, id';

$imageStatement = $pdo->prepare($imageSql);
$imageStatement->execute($imageParameters);

$imagesByPost = [];

foreach ($imageStatement->fetchAll() as $image) {
    $key = (int) $image['post_id'];

    if (!isset($imagesByPost[$key])) {
        $imagesByPost[$key] = [];
    }

    $imagesByPost[$key][] = $image['image_url'];
}

$posts = [];

foreach ($rows as $row) {
    $id = (int) $row['id'];
    $gallery = $imagesByPost[$id] ?? [];

    $posts[] = [
        'id' => $id,
        'title' => $row['title'],
        'description' => $row['description'],
        'restaurantName' => $row['restaurant_name'],
        'address' => $row['address'],
        'location' => $row['location'],
        'cuisine' => $row['cuisine'],
        'priceRange' => $row['price_range'],

        'latitude' => $row['latitude'] === null
            ? null
            : (float) $row['latitude'],

        'longitude' => $row['longitude'] === null
            ? null
            : (float) $row['longitude'],

        'authorId' => (int) $row['author_id'],
        'authorName' => $row['author_name'],
        'authorDisplayName' => $row['author_display_name'],
        'authorAvatar' => $row['author_avatar'] ?? '',

        'coverImage' => $gallery[0] ?? '',
        'gallery' => $gallery,

        'likes' => (int) $row['like_count'],
        'likedByMe' => (bool) $row['liked_by_me'],
        'savedByMe' => (bool) $row['saved_by_me'],

        'createdAt' => str_replace(
            ' ',
            'T',
            $row['created_at']
        ) . 'Z',

        'updatedAt' => str_replace(
            ' ',
            'T',
            $row['updated_at']
        ) . 'Z',
    ];
}

respond([
    'ok' => true,
    'posts' => $posts,
]);