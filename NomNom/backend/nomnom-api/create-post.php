<?php

require_once __DIR__ . '/bootstrap.php';

requirePost();

$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

if (!$userId || !findUserById($pdo, $userId)) {
    respond(array(
        'ok' => false,
        'message' => 'Please log in before publishing.',
    ), 401);
}

// Validate text fields on the server too.
$limits = array(
    'title' => 100,
    'description' => 2000,
    'restaurantName' => 120,
    'address' => 250,
);

$values = array();

foreach ($limits as $field => $maximum) {
    $value = trim(stringField($_POST, $field));

    // Counts Unicode characters without requiring mbstring.
    $length = preg_match_all('/./us', $value);

    if (
        $value === ''
        || $length === false
        || $length > $maximum
    ) {
        respond(array(
            'ok' => false,
            'message' => $field . ' is required and must be within '
                . $maximum . ' characters.',
        ), 422);
    }

    $values[$field] = $value;
}

$cuisines = array(
    'Malaysian', 'Chinese', 'Indian', 'Japanese', 'Korean',
    'Thai', 'Vietnamese', 'Italian', 'Western', 'Cafe',
    'Dessert', 'Fusion', 'Middle Eastern',
);

$locations = array(
    'Kuala Lumpur' => array(3.1390, 101.6869),
    'Petaling Jaya' => array(3.1073, 101.6068),
    'Subang Jaya' => array(3.0567, 101.5851),
    'Cyberjaya' => array(2.9181, 101.6547),
    'Penang' => array(5.4141, 100.3288),
    'Johor Bahru' => array(1.4927, 103.7414),
    'Melaka' => array(2.1896, 102.2501),
    'Ipoh' => array(4.5975, 101.0901),
);

$location = trim(stringField($_POST, 'location'));

$latitude = isset($_POST['latitude']) && is_numeric($_POST['latitude'])
    ? (float) $_POST['latitude']
    : null;
$longitude = isset($_POST['longitude']) && is_numeric($_POST['longitude'])
    ? (float) $_POST['longitude']
    : null;

if (
    $latitude === null
    || $longitude === null
    || $latitude < -90
    || $latitude > 90
    || $longitude < -180
    || $longitude > 180
) {
    respond(array(
        'ok' => false,
        'message' => 'Please pin the restaurant location on the map.',
    ), 422);
}

$cuisine = trim(stringField($_POST, 'cuisine'));
$priceRange = trim(stringField($_POST, 'priceRange'));

if (!isset($locations[$location])) {
    respond(array(
        'ok' => false,
        'message' => 'Please select a valid location.',
    ), 422);
}

if (!in_array($cuisine, $cuisines, true)) {
    respond(array(
        'ok' => false,
        'message' => 'Please select a valid cuisine.',
    ), 422);
}

if (!in_array($priceRange, array('$', '$$', '$$$'), true)) {
    respond(array(
        'ok' => false,
        'message' => 'Please select a valid price range.',
    ), 422);
}

// Validate every photo before saving anything.
$photos = isset($_FILES['photos']) ? $_FILES['photos'] : null;

if (
    !is_array($photos)
    || !isset($photos['error'])
    || !is_array($photos['error'])
    || count($photos['error']) < 1
    || count($photos['error']) > 5
) {
    respond(array(
        'ok' => false,
        'message' => 'Please upload between one and five photos.',
    ), 422);
}

$allowedTypes = array(
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
);

$finfo = new finfo(FILEINFO_MIME_TYPE);
$validatedPhotos = array();

foreach ($photos['error'] as $index => $error) {
    $temporaryPath = isset($photos['tmp_name'][$index]) ? $photos['tmp_name'][$index] : null;

    if (
        $error !== UPLOAD_ERR_OK
        || !is_string($temporaryPath)
        || !is_uploaded_file($temporaryPath)
    ) {
        respond(array(
            'ok' => false,
            'message' => 'A photo could not be uploaded. Try selecting it again.',
        ), 422);
    }

    $size = filesize($temporaryPath);

    if ($size === false || $size < 1 || $size > 500 * 1024) {
        respond(array(
            'ok' => false,
            'message' => 'Each photo must be 500 KB or smaller.',
        ), 422);
    }

    $mimeType = $finfo->file($temporaryPath);
    $imageInfo = @getimagesize($temporaryPath);

    if (
        !isset($allowedTypes[$mimeType])
        || !$imageInfo
        || (!isset($imageInfo['mime']) || $imageInfo['mime'] !== $mimeType)
    ) {
        respond(array(
            'ok' => false,
            'message' => 'Photos must be valid JPG, PNG, or WebP images.',
        ), 422);
    }

    if (
        $imageInfo[0] > 8000
        || $imageInfo[1] > 8000
        || $imageInfo[0] * $imageInfo[1] > 25000000
    ) {
        respond(array(
            'ok' => false,
            'message' => 'A photo is too large in dimensions. Resize it first.',
        ), 422);
    }

    $validatedPhotos[] = array(
        'temporaryPath' => $temporaryPath,
        'filename' => bin2hex(random_bytes(16))
            . '.' . $allowedTypes[$mimeType],
    );
}

$config = require __DIR__ . '/config.local.php';
$uploadDirectory = isset($config['upload_dir']) ? $config['upload_dir'] : '';

if (
    !is_string($uploadDirectory)
    || !is_dir($uploadDirectory)
    || !is_writable($uploadDirectory)
) {
    throw new RuntimeException('Upload directory is missing or not writable.');
}

$savedPaths = array();
$postId = null;
$error = null;

try {
    $pdo->beginTransaction();

    $statement = $pdo->prepare(
        'INSERT INTO nomnom_posts (
            author_id, title, description, restaurant_name,
            address, location, cuisine, price_range,
            latitude, longitude, created_at, updated_at
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                UTC_TIMESTAMP(), UTC_TIMESTAMP())'
    );

    $statement->execute(array(
        $userId,
        $values['title'],
        $values['description'],
        $values['restaurantName'],
        $values['address'],
        $location,
        $cuisine,
        $priceRange,
        $latitude,
        $longitude,
    ));

    $postId = (int) $pdo->lastInsertId();

    $imageStatement = $pdo->prepare(
        'INSERT INTO nomnom_post_images
            (post_id, image_url, sort_order)
         VALUES (?, ?, ?)'
    );

    foreach ($validatedPhotos as $order => $photo) {
        $destination = rtrim($uploadDirectory, '/\\')
            . DIRECTORY_SEPARATOR
            . $photo['filename'];

        if (!move_uploaded_file($photo['temporaryPath'], $destination)) {
            throw new RuntimeException('Could not save an uploaded photo.');
        }

        $savedPaths[] = $destination;

        $imageStatement->execute(array(
            $postId,
            'api/image.php?name=' . $photo['filename'],
            $order,
        ));
    }

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    foreach ($savedPaths as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    $error = $e;
}

if ($error !== null) {
    throw $error;
}

respond(array(
    'ok' => true,
    'postId' => $postId,
), 201);