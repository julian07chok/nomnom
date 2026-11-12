<?php

function parsePhotoPlan($data)
{
    // Allows existing text-only API requests to keep photos unchanged.
    if (!array_key_exists('photos', $data)) {
        return null;
    }

    $photos = $data['photos'];

    if (
        !is_array($photos)
        || count($photos) < 1
        || count($photos) > 5
        || array_keys($photos) !== range(0, count($photos) - 1)
    ) {
        throw new InvalidArgumentException(
            'Keep between one and five photos.'
        );
    }

    $allowedTypes = array(
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    );

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $plan = array();
    $seenIds = array();

    foreach ($photos as $photo) {
        if (!is_array($photo) || count($photo) !== 1) {
            throw new InvalidArgumentException('Invalid photo selection.');
        }

        if (isset($photo['id'])) {
            $id = $photo['id'];

            if (
                !is_int($id)
                || $id < 1
                || in_array($id, $seenIds, true)
            ) {
                throw new InvalidArgumentException(
                    'Invalid or duplicate existing photo.'
                );
            }

            $seenIds[] = $id;
            $plan[] = array('id' => $id);
            continue;
        }

        $dataUrl = isset($photo['dataUrl']) ? $photo['dataUrl'] : null;

        if (
            !is_string($dataUrl)
            || strlen($dataUrl) > 700000
            || !preg_match(
                '/\Adata:(image\/(?:jpeg|png|webp));base64,/',
                $dataUrl,
                $matches
            )
        ) {
            throw new InvalidArgumentException(
                'Use JPG, PNG, or WebP photos up to 500 KB each.'
            );
        }

        $bytes = base64_decode(
            substr($dataUrl, strlen($matches[0])),
            true
        );

        if (
            $bytes === false
            || strlen($bytes) < 1
            || strlen($bytes) > 500 * 1024
        ) {
            throw new InvalidArgumentException(
                'Each photo must be 500 KB or smaller.'
            );
        }

        $mimeType = $finfo->buffer($bytes);
        $info = @getimagesizefromstring($bytes);

        if (
            !isset($allowedTypes[$mimeType])
            || $mimeType !== $matches[1]
            || !$info
            || (!isset($info['mime']) || $info['mime'] !== $mimeType)
        ) {
            throw new InvalidArgumentException('Invalid image file.');
        }

        if (
            $info[0] > 8000
            || $info[1] > 8000
            || $info[0] * $info[1] > 25000000
        ) {
            throw new InvalidArgumentException(
                'A photo is too large in dimensions. Resize it first.'
            );
        }

        $plan[] = array(
            'bytes' => $bytes,
            'extension' => $allowedTypes[$mimeType],
        );
    }

    return $plan;
}

function applyPhotoPlan($pdo, $postId, $plan, &$newPaths)
{
    $statement = $pdo->prepare(
        'SELECT id, image_url
         FROM nomnom_post_images
         WHERE post_id = ?
         FOR UPDATE'
    );

    $statement->execute(array($postId));

    $existing = array();

    foreach ($statement->fetchAll() as $row) {
        $existing[(int) $row['id']] = $row['image_url'];
    }

    $keptIds = array();

    foreach ($plan as $photo) {
        if (isset($photo['id'])) {
            if (!isset($existing[$photo['id']])) {
                throw new InvalidArgumentException(
                    'A photo is no longer available. Refresh the post.'
                );
            }

            $keptIds[] = $photo['id'];
        }
    }

    $removedUrls = array();

    foreach ($existing as $id => $url) {
        if (!in_array($id, $keptIds, true)) {
            $removedUrls[] = $url;
        }
    }

    $statement = $pdo->prepare(
        'DELETE FROM nomnom_post_images WHERE post_id = ?'
    );

    $statement->execute(array($postId));

    $insertExisting = $pdo->prepare(
        'INSERT INTO nomnom_post_images
            (id, post_id, image_url, sort_order)
         VALUES (?, ?, ?, ?)'
    );

    $insertNew = $pdo->prepare(
        'INSERT INTO nomnom_post_images
            (post_id, image_url, sort_order)
         VALUES (?, ?, ?)'
    );

    foreach ($plan as $order => $photo) {
        if (isset($photo['id'])) {
            $insertExisting->execute(array(
                $photo['id'],
                $postId,
                $existing[$photo['id']],
                $order,
            ));

            continue;
        }

        $config = require __DIR__ . '/config.local.php';
        $directory = isset($config['upload_dir']) ? $config['upload_dir'] : '';

        if (
            !is_string($directory)
            || !is_dir($directory)
            || !is_writable($directory)
        ) {
            throw new RuntimeException(
                'Upload directory is missing or not writable.'
            );
        }

        $filename = bin2hex(random_bytes(16))
            . '.' . $photo['extension'];

        $path = rtrim($directory, '/\\')
            . DIRECTORY_SEPARATOR
            . $filename;

        $newPaths[] = $path;

        $written = file_put_contents(
            $path,
            $photo['bytes'],
            LOCK_EX
        );

        if ($written !== strlen($photo['bytes'])) {
            throw new RuntimeException('Could not save a photo.');
        }

        $insertNew->execute(array(
            $postId,
            'api/image.php?name=' . $filename,
            $order,
        ));
    }

    return $removedUrls;
}