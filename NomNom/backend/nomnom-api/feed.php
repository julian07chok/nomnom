<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/recommendation-scores.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');

    respond([
        'ok' => false,
        'message' => 'Method not allowed.',
    ], 405);
}

function feedInteger($name, $default, $minimum, $maximum)
{
    $value = $_GET[$name] ?? (string) $default;

    if (
        !is_string($value)
        || !ctype_digit($value)
        || strlen($value) > 7
        || (int) $value < $minimum
        || (int) $value > $maximum
    ) {
        respond([
            'ok' => false,
            'message' => 'Invalid ' . $name . '.',
        ], 400);
    }

    return (int) $value;
}

$offset = feedInteger('offset', 0, 0, 1000000);
$limit = feedInteger('limit', 6, 1, 12);

$cuisine = $_GET['cuisine'] ?? 'All';
$sort = $_GET['sort'] ?? 'newest';

$cuisines = [
    'All',
    'Malaysian',
    'Chinese',
    'Indian',
    'Japanese',
    'Korean',
    'Thai',
    'Vietnamese',
    'Italian',
    'Western',
    'Cafe',
    'Dessert',
    'Fusion',
    'Middle Eastern',
];

$sortOptions = [
    'newest' =>
    'feed.created_at DESC, feed.id DESC',

    'most-liked' =>
    'feed.like_count DESC, feed.created_at DESC, feed.id DESC',

    'recommended' =>
    'feed.recommendation_score DESC, feed.created_at DESC, feed.id DESC',
];

if (!is_string($cuisine) || !in_array($cuisine, $cuisines, true)) {
    respond(['ok' => false, 'message' => 'Invalid cuisine.'], 400);
}

if (!is_string($sort) || !isset($sortOptions[$sort])) {
    respond(['ok' => false, 'message' => 'Invalid sort option.'], 400);
}

$viewerId = (int) ($_SESSION['user_id'] ?? 0);

// Guests receive the newest feed.
if ($sort === 'recommended' && $viewerId === 0) {
    $sort = 'newest';
}

$recommendationScores = [];

if ($sort === 'recommended') {
    $recommendationScores = getRecommendationScores(
        $pdo,
        $viewerId
    );
}

$parameters = [$viewerId, $viewerId];

$distanceSql = 'NULL';
$nearMe = isset($_GET['lat']) || isset($_GET['lng']);

if ($nearMe) {
    $lat = $_GET['lat'] ?? null;
    $lng = $_GET['lng'] ?? null;

    if (
        !is_string($lat)
        || !is_string($lng)
        || !is_numeric($lat)
        || !is_numeric($lng)
        || !is_finite((float) $lat)
        || !is_finite((float) $lng)
        || (float) $lat < -90
        || (float) $lat > 90
        || (float) $lng < -180
        || (float) $lng > 180
    ) {
        respond(['ok' => false, 'message' => 'Invalid coordinates.'], 400);
    }

    $distanceSql = '
        CASE
            WHEN p.latitude IS NULL OR p.longitude IS NULL THEN NULL
            ELSE 6371 * ACOS(
                LEAST(1, GREATEST(-1,
                    COS(RADIANS(?))
                    * COS(RADIANS(p.latitude))
                    * COS(RADIANS(p.longitude) - RADIANS(?))
                    +
                    SIN(RADIANS(?))
                    * SIN(RADIANS(p.latitude))
                ))
            )
        END
    ';

    $parameters[] = (float) $lat;
    $parameters[] = (float) $lng;
    $parameters[] = (float) $lat;
}

$recommendationSql = '0';

if ($recommendationScores) {
    $cases = [];

    foreach ($recommendationScores as $preferredCuisine => $score) {
        // Cuisine names are bound parameters.
        // Scores are explicitly converted to integers.
        $cases[] = 'WHEN ? THEN ' . (int) $score;
        $parameters[] = $preferredCuisine;
    }

    $recommendationSql =
        'CASE p.cuisine '
        . implode(' ', $cases)
        . ' ELSE 0 END';
}

$baseSql = "
    SELECT
        p.id, p.title, p.restaurant_name, p.location,
        p.cuisine, p.price_range, p.latitude, p.longitude,
        p.author_id, p.created_at,

        u.username AS author_name,
        u.display_name AS author_display_name,
        u.avatar_url AS author_avatar,

        (
            SELECT COUNT(*)
            FROM nomnom_post_likes likes
            WHERE likes.post_id = p.id
        ) AS like_count,

        EXISTS (
            SELECT 1 FROM nomnom_post_likes my_like
            WHERE my_like.post_id = p.id AND my_like.user_id = ?
        ) AS liked_by_me,

        EXISTS (
            SELECT 1 FROM nomnom_saved_posts my_save
            WHERE my_save.post_id = p.id AND my_save.user_id = ?
        ) AS saved_by_me,

        $distanceSql AS distance_km,
        $recommendationSql AS recommendation_score,

        (
            SELECT image_url
            FROM nomnom_post_images images
            WHERE images.post_id = p.id
            ORDER BY images.sort_order, images.id
            LIMIT 1
        ) AS cover_image

    FROM nomnom_posts p
    INNER JOIN nomnom_users u ON u.id = p.author_id
";

$sql = "SELECT * FROM ($baseSql) AS feed";
$conditions = [];

if ($cuisine !== 'All') {
    $conditions[] = 'feed.cuisine = ?';
    $parameters[] = $cuisine;
}

if ($nearMe) {
    $conditions[] = 'feed.distance_km IS NOT NULL';
    $conditions[] = 'feed.distance_km <= 50';
}

if ($conditions) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}

$sql .= ' ORDER BY ' . $sortOptions[$sort];

// Validated integers only. One extra row tells us whether more exist.
$fetchLimit = $limit + 1;
$sql .= " LIMIT $fetchLimit OFFSET $offset";

$statement = $pdo->prepare($sql);
$statement->execute($parameters);
$rows = $statement->fetchAll();

$hasMore = count($rows) > $limit;

if ($hasMore) {
    array_pop($rows);
}

$posts = [];

foreach ($rows as $row) {
    $posts[] = [
        'id' => (int) $row['id'],
        'title' => $row['title'],
        'restaurantName' => $row['restaurant_name'],
        'location' => $row['location'],
        'cuisine' => $row['cuisine'],
        'priceRange' => $row['price_range'],
        'latitude' => $row['latitude'] === null
            ? null : (float) $row['latitude'],
        'longitude' => $row['longitude'] === null
            ? null : (float) $row['longitude'],
        'distanceKm' => $row['distance_km'] === null
            ? null : (float) $row['distance_km'],
        'authorId' => (int) $row['author_id'],
        'authorName' => $row['author_name'],
        'authorDisplayName' => $row['author_display_name'],
        'authorAvatar' => $row['author_avatar'] ?? '',
        'coverImage' => $row['cover_image'] ?? '',
        'likes' => (int) $row['like_count'],
        'recommendationScore' =>
        (int) $row['recommendation_score'],
        'likedByMe' => (bool) $row['liked_by_me'],
        'savedByMe' => (bool) $row['saved_by_me'],
        'createdAt' => str_replace(' ', 'T', $row['created_at']) . 'Z',
    ];
}

respond([
    'ok' => true,
    'posts' => $posts,
    'hasMore' => $hasMore,
    'nextOffset' => $offset + count($posts),
]);
