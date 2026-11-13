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

// ─── Near Me filtering ────────────────────────────
// If the client passes ?lat=...&lng=...&radius=50, the backend
// calculates Haversine distance and filters / sorts accordingly.
$nearLat = null;
$nearLng = null;
$radiusKm = 50.0;
$nearMeEnabled = false;

if (isset($_GET['lat']) && is_numeric($_GET['lat'])
    && isset($_GET['lng']) && is_numeric($_GET['lng'])) {
    $nearLat = (float) $_GET['lat'];
    $nearLng = (float) $_GET['lng'];

    if (isset($_GET['radius']) && is_numeric($_GET['radius'])) {
        $radiusKm = (float) $_GET['radius'];
    }

    // Guard against out-of-range coordinates.
    if ($nearLat >= -90 && $nearLat <= 90
        && $nearLng >= -180 && $nearLng <= 180
        && $radiusKm > 0 && $radiusKm <= 500) {
        $nearMeEnabled = true;
    }
}

// Haversine expression — Earth's mean radius is 6371 km.
// Returns the great-circle distance in kilometres.
$distanceSql = $nearMeEnabled
    ? ',
        (6371 * ACOS(
            GREATEST(-1, LEAST(1,
                COS(RADIANS(?)) * COS(RADIANS(p.latitude))
                * COS(RADIANS(p.longitude) - RADIANS(?))
                + SIN(RADIANS(?)) * SIN(RADIANS(p.latitude))
            ))
        )) AS distance_km'
    : ', NULL AS distance_km';

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
        ' . $distanceSql . '

    FROM nomnom_posts p
    INNER JOIN nomnom_users u ON u.id = p.author_id
';

$parameters = array($viewerId, $viewerId);

// Lat/lng params belong to the Haversine expression, which sits
// inside the SELECT clause — so they go BEFORE the WHERE params.
if ($nearMeEnabled) {
    $parameters[] = $nearLat; // COS(RADIANS(?))
    $parameters[] = $nearLng; // COS(... - RADIANS(?))
    $parameters[] = $nearLat; // SIN(RADIANS(?))
}

if ($postId !== null) {
    $sql .= ' WHERE p.id = ?';
    $parameters[] = $postId;
}

// Filter by radius after the distance has been computed.
// We use a subquery wrapper so distance_km is filterable in WHERE.
if ($nearMeEnabled) {
    if ($postId !== null) {
        // Both conditions — combine carefully.
        $sql = '
            SELECT * FROM (' . $sql . ') AS filtered
            WHERE distance_km IS NOT NULL AND distance_km <= ?
        ';
        // Rebuild parameters in correct order:
        // 1) viewerId x2 (from inner query)
        // 2) lat, lng, lat (Haversine)
        // 3) postId (WHERE p.id)
        // 4) radius (outer WHERE)
        $parameters = array($viewerId, $viewerId);
        $parameters[] = $nearLat;
        $parameters[] = $nearLng;
        $parameters[] = $nearLat;
        $parameters[] = $postId;
        $parameters[] = $radiusKm;
    } else {
        // No single-post filter — append the radius filter.
        $sql .= ' HAVING distance_km IS NOT NULL AND distance_km <= ?';
        $parameters[] = $radiusKm;
    }

    $sql .= ' ORDER BY distance_km ASC, p.created_at DESC';
} else {
    $sql .= ' ORDER BY p.created_at DESC, p.id DESC';
}

$statement = $pdo->prepare($sql);
$statement->execute($parameters);

$rows = $statement->fetchAll();

if ($postId !== null && !$rows) {
    respond(array(
        'ok' => false,
        'message' => 'Post not found.',
    ), 404);
}

// ─── Images (unchanged) ──────────────────────────────────────
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

// ─── Response mapping ────────────────────────────────────────
$posts = array();

foreach ($rows as $row) {
    $id = (int) $row['id'];
    $galleryItems = isset($imagesByPost[$id]) ? $imagesByPost[$id] : array();
    $gallery = array_column($galleryItems, 'url');

    // Distance is only meaningful when Near Me is active.
    $distanceKm = null;
    if ($nearMeEnabled && isset($row['distance_km']) && $row['distance_km'] !== null) {
        $distanceKm = round((float) $row['distance_km'], 2);
    }

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

        // NEW: distance in km (null when Near Me is off).
        'distanceKm' => $distanceKm,

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