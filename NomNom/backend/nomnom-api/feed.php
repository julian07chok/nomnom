<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/recommendation-scores.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');

    respond(array(
        'ok' => false,
        'message' => 'Method not allowed.',
    ), 405);
}

// Validate numeric batch parameters before using them in SQL.
function feedInteger($name, $default, $minimum, $maximum)
{
    $value = isset($_GET[$name]) ? $_GET[$name] : (string) $default;

    if (
        !is_string($value)
        || !ctype_digit($value)
        || strlen($value) > 7
        || (int) $value < $minimum
        || (int) $value > $maximum
    ) {
        respond(array(
            'ok' => false,
            'message' => 'Invalid ' . $name . '.',
        ), 400);
    }

    return (int) $value;
}

// Offset is the number of posts to skip; limit is the requested batch size.
$offset = feedInteger('offset', 0, 0, 1000000);
$limit = feedInteger('limit', 6, 1, 12);

$cuisine = isset($_GET['cuisine']) ? $_GET['cuisine'] : 'All';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

$cuisines = array(
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
);

// Only allow predefined sorting expressions.
$sortOptions = array(
    'newest' => 'feed.created_at DESC, feed.id DESC',
    'most-liked' => 'feed.like_count DESC, feed.created_at DESC, feed.id DESC',
    'recommended' => 'feed.recommendation_score DESC, feed.created_at DESC, feed.id DESC',
    'near-me' => 'feed.distance_km ASC, feed.created_at DESC, feed.id DESC',
);

if (!is_string($cuisine) || !in_array($cuisine, $cuisines, true)) {
    respond(array('ok' => false, 'message' => 'Invalid cuisine.'), 400);
}

if (!is_string($sort) || !isset($sortOptions[$sort])) {
    respond(array('ok' => false, 'message' => 'Invalid sort option.'), 400);
}

$viewerId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

// Guests use newest-first ordering instead of personalised scores.
if ($sort === 'recommended' && $viewerId === 0) {
    $sort = 'newest';
}

$recommendationScores = array();

// Calculate this account's cuisine scores for the For You feed.
if ($sort === 'recommended') {
    $recommendationScores = getRecommendationScores($pdo, $viewerId);
}

$parameters = array($viewerId, $viewerId);

// ─── Near Me parameters ──────────────────────────────────────
$distanceSql = 'NULL';
$nearMe = false;
$radiusKm = 50.0;

if (isset($_GET['lat']) && isset($_GET['lng'])) {
    $lat = $_GET['lat'];
    $lng = $_GET['lng'];

    if (
        !is_string($lat) || !is_string($lng)
        || !is_numeric($lat) || !is_numeric($lng)
        || !is_finite((float) $lat) || !is_finite((float) $lng)
        || (float) $lat < -90 || (float) $lat > 90
        || (float) $lng < -180 || (float) $lng > 180
    ) {
        respond(array('ok' => false, 'message' => 'Invalid coordinates.'), 400);
    }

    // Optional radius (defaults to 50km).
    if (isset($_GET['radius'])) {
        $rawRadius = $_GET['radius'];

        if (
            is_string($rawRadius)
            && is_numeric($rawRadius)
            && is_finite((float) $rawRadius)
            && (float) $rawRadius > 0
            && (float) $rawRadius <= 500
        ) {
            $radiusKm = (float) $rawRadius;
        }
    }

    $nearMe = true;

    // Calculate distance in kilometres for posts with restaurant coordinates.
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

// Assign each post its cuisine's score; unscored cuisines receive zero.
$recommendationSql = '0';

if ($recommendationScores) {
    $cases = array();

    foreach ($recommendationScores as $preferredCuisine => $score) {
        $cases[] = 'WHEN ? THEN ' . (int) $score;
        $parameters[] = $preferredCuisine;
    }

    $recommendationSql = 'CASE p.cuisine '
        . implode(' ', $cases)
        . ' ELSE 0 END';
}

// Collect card details, interaction flags, scores, and the first photo.
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

// Apply cuisine and nearby filters before sorting and selecting a batch.
$sql = "SELECT * FROM ($baseSql) AS feed";
$conditions = array();

if ($cuisine !== 'All') {
    $conditions[] = 'feed.cuisine = ?';
    $parameters[] = $cuisine;
}

if ($nearMe) {
    $conditions[] = 'feed.distance_km IS NOT NULL';
    $conditions[] = 'feed.distance_km <= ?';
    $parameters[] = $radiusKm;
}

if ($conditions) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}

// When Near Me is active, sort by proximity instead of the
// user-selected sort. Otherwise, respect the sort parameter.
$effectiveSort = $nearMe ? 'near-me' : $sort;

// Rank all matching posts before applying LIMIT and OFFSET.
$sql .= ' ORDER BY ' . $sortOptions[$effectiveSort];

// Fetch one extra row to check whether another batch is available.
$fetchLimit = $limit + 1;
$sql .= " LIMIT $fetchLimit OFFSET $offset";

$statement = $pdo->prepare($sql);
$statement->execute($parameters);
$rows = $statement->fetchAll();

$hasMore = count($rows) > $limit;

// The extra row is only a check, so leave it for the next request.
if ($hasMore) {
    array_pop($rows);
}

// Convert database fields into the JSON format expected by Vue.
$posts = array();

foreach ($rows as $row) {
    $posts[] = array(
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
            ? null : round((float) $row['distance_km'], 2),
        'authorId' => (int) $row['author_id'],
        'authorName' => $row['author_name'],
        'authorDisplayName' => $row['author_display_name'],
        'authorAvatar' => isset($row['author_avatar']) ? $row['author_avatar'] : '',
        'coverImage' => isset($row['cover_image']) ? $row['cover_image'] : '',
        'likes' => (int) $row['like_count'],
        'recommendationScore' => (int) $row['recommendation_score'],
        'likedByMe' => (bool) $row['liked_by_me'],
        'savedByMe' => (bool) $row['saved_by_me'],
        'createdAt' => str_replace(' ', 'T', $row['created_at']) . 'Z',
    );
}

// Tell Vue where the next batch starts and whether loading should continue.
respond(array(
    'ok' => true,
    'posts' => $posts,
    'hasMore' => $hasMore,
    'nextOffset' => $offset + count($posts),
));
