<?php

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/create-post.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET, POST');

    respond(array(
        'ok' => false,
        'message' => 'Method not allowed.',
    ), 405);
}

$viewerId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

$postId = null;

if (isset($_GET['id'])) {
    if (
        !is_string($_GET['id'])
        || !ctype_digit($_GET['id'])
        || (int) $_GET['id'] < 1
    ) {
        respond(array(
            'ok' => false,
            'message' => 'Invalid post ID.',
        ), 400);
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

$parameters = array($viewerId, $viewerId);

if ($postId !== null) {
    $sql .= ' WHERE p.id = ?';
    $parameters[] = $postId;
}

$sql .= ' ORDER BY p.created_at DESC, p.id DESC';

$statement = $pdo->prepare($sql);
$statement->execute($parameters);

$rows = $statement->fetchAll();

if ($postId !== null && !$rows) {
    respond(array(
        'ok' => false,
        'message' => 'Post not found.',
    ), 404);
}

$imageSql = '
    SELECT id, post_id, image_url
    FROM nomnom_post_images
';

$imageParameters = array();

if ($postId !== null) {
    $imageSql .= ' WHERE post_id = ?';
    $imageParameters[] = $postId;
}

$imageSql .= ' ORDER BY post_id, sort_order, id';

$imageStatement = $pdo->prepare($imageSql);
$imageStatement->execute($imageParameters);

$imagesByPost = array();

foreach ($imageStatement->fetchAll() as $image) {
    $key = (int) $image['post_id'];

    if (!isset($imagesByPost[$key])) {
        $imagesByPost[$key] = array();
    }

    $imagesByPost[$key][] = array(
        'id' => (int) $image['id'],
        'url' => $image['image_url'],
    );
}

$posts = array();

foreach ($rows as $row) {
    $id = (int) $row['id'];
    $galleryItems = isset($imagesByPost[$id]) ? $imagesByPost[$id] : array();
    $gallery = array_column($galleryItems, 'url');

    $posts[] = array(
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
        'authorAvatar' => isset($row['author_avatar']) ? $row['author_avatar'] : '',

        'coverImage' => isset($gallery[0]) ? $gallery[0] : '',
        'gallery' => $gallery,
        'galleryItems' => $galleryItems,

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
    );
}

respond(array(
    'ok' => true,
    'posts' => $posts,
));