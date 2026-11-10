<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/post-photos.php';

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

if (!is_string($id) || !ctype_digit($id) || (int) $id < 1) {
    respond([
        'ok' => false,
        'message' => 'Invalid post ID.',
    ], 400);
}

$postId = (int) $id;
$values = [];
$imageUrls = [];
$photoPlan = null;
$newPaths = [];

// Validate update data before starting the transaction.
if ($method === 'PUT') {
    $data = readJsonBody();

    try {
        $photoPlan = parsePhotoPlan($data);
    } catch (InvalidArgumentException $error) {
        respond([
            'ok' => false,
            'message' => $error->getMessage(),
        ], 422);
    }

    $limits = [
        'title' => 100,
        'description' => 2000,
        'restaurantName' => 120,
        'address' => 250,
    ];

    foreach ($limits as $field => $maximum) {
        $value = trim(stringField($data, $field));
        $length = preg_match_all('/./us', $value);

        if (
            $value === ''
            || $length === false
            || $length > $maximum
        ) {
            respond([
                'ok' => false,
                'message' => $field . ' is required and must be within '
                    . $maximum . ' characters.',
            ], 422);
        }

        $values[$field] = $value;
    }

    $cuisines = [
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

    $locations = [
        'Kuala Lumpur' => [3.1390, 101.6869],
        'Petaling Jaya' => [3.1073, 101.6068],
        'Subang Jaya' => [3.0567, 101.5851],
        'Cyberjaya' => [2.9181, 101.6547],
        'Penang' => [5.4141, 100.3288],
        'Johor Bahru' => [1.4927, 103.7414],
        'Melaka' => [2.1896, 102.2501],
        'Ipoh' => [4.5975, 101.0901],
    ];

    $location = trim(stringField($data, 'location'));
    $cuisine = trim(stringField($data, 'cuisine'));
    $priceRange = trim(stringField($data, 'priceRange'));

    if (!isset($locations[$location])) {
        respond([
            'ok' => false,
            'message' => 'Please select a valid location.',
        ], 422);
    }

    if (!in_array($cuisine, $cuisines, true)) {
        respond([
            'ok' => false,
            'message' => 'Please select a valid cuisine.',
        ], 422);
    }

    if (!in_array($priceRange, ['$', '$$', '$$$'], true)) {
        respond([
            'ok' => false,
            'message' => 'Please select a valid price range.',
        ], 422);
    }
}

try {
    $pdo->beginTransaction();

    // Lock and check the actual database record.
    $statement = $pdo->prepare(
        'SELECT author_id
         FROM nomnom_posts
         WHERE id = ?
         FOR UPDATE'
    );

    $statement->execute([$postId]);
    $existing = $statement->fetch();

    if (!$existing) {
        $pdo->rollBack();

        respond([
            'ok' => false,
            'message' => 'This post no longer exists.',
        ], 404);
    }

    if ((int) $existing['author_id'] !== $userId) {
        $pdo->rollBack();

        respond([
            'ok' => false,
            'message' => 'You can only edit or delete your own posts.',
        ], 403);
    }

    if ($method === 'PUT') {
        $statement = $pdo->prepare(
            'UPDATE nomnom_posts
             SET title = ?,
                 description = ?,
                 restaurant_name = ?,
                 address = ?,
                 location = ?,
                 cuisine = ?,
                 price_range = ?,
                 latitude = ?,
                 longitude = ?,
                 updated_at = UTC_TIMESTAMP()
             WHERE id = ? AND author_id = ?'
        );

        $statement->execute([
            $values['title'],
            $values['description'],
            $values['restaurantName'],
            $values['address'],
            $location,
            $cuisine,
            $priceRange,
            $locations[$location][0],
            $locations[$location][1],
            $postId,
            $userId,
        ]);
        if ($photoPlan !== null) {
            $imageUrls = applyPhotoPlan(
                $pdo,
                $postId,
                $photoPlan,
                $newPaths
            );
        }
    } else {
        // Remember image paths before cascading deletion removes rows.
        $statement = $pdo->prepare(
            'SELECT image_url
             FROM nomnom_post_images
             WHERE post_id = ?

             UNION ALL

             SELECT ci.image_url
             FROM nomnom_comment_images ci
             INNER JOIN nomnom_comments c ON c.id = ci.comment_id
             WHERE c.post_id = ?'
        );

        $statement->execute([$postId, $postId]);
        $imageUrls = $statement->fetchAll(PDO::FETCH_COLUMN);

        $statement = $pdo->prepare(
            'DELETE FROM nomnom_posts
             WHERE id = ? AND author_id = ?'
        );

        $statement->execute([$postId, $userId]);
    }

    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    foreach ($newPaths as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    if ($error instanceof InvalidArgumentException) {
        respond([
            'ok' => false,
            'message' => $error->getMessage(),
        ], 422);
    }

    throw $error;
}

// Remove uploaded files only after database deletion succeeds.
// File cleanup failure must not report the database deletion as failed.
if (!empty($imageUrls)) {
    try {
        $config = require __DIR__ . '/config.local.php';
        $directory = $config['upload_dir'] ?? '';

        if (!is_string($directory) || !is_dir($directory)) {
            throw new RuntimeException('Upload directory is unavailable.');
        }

        $referenceCheck = $pdo->prepare(
            'SELECT
                (SELECT COUNT(*) FROM nomnom_post_images
                 WHERE image_url = ?)
                +
                (SELECT COUNT(*) FROM nomnom_comment_images
                 WHERE image_url = ?)
                +
                (SELECT COUNT(*) FROM nomnom_users
                 WHERE avatar_url = ?)'
        );

        foreach (array_unique($imageUrls) as $url) {
            // Only delete files generated by our upload system.
            if (!preg_match(
                '/\Aapi\/image\.php\?name=([a-f0-9]{32}\.(?:jpg|png|webp))\z/',
                $url,
                $matches
            )) {
                continue;
            }

            $referenceCheck->execute([$url, $url, $url]);

            if ((int) $referenceCheck->fetchColumn() > 0) {
                continue;
            }

            $path = rtrim($directory, '/\\')
                . DIRECTORY_SEPARATOR
                . $matches[1];

            if (is_file($path) && !@unlink($path)) {
                error_log('NomNom: could not remove uploaded file ' . $matches[1]);
            }
        }
    } catch (Throwable $error) {
        error_log('NomNom image cleanup: ' . $error->getMessage());
    }
}

respond([
    'ok' => true,
    'postId' => $postId,
]);
