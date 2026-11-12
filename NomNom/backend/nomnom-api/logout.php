<?php

require __DIR__ . '/bootstrap.php';

requirePost();

// Remove authenticated state and issue a fresh anonymous session.
$_SESSION = array();

session_regenerate_id(true);

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

respond(array(
    'ok' => true,
    'user' => null,
    'csrfToken' => $_SESSION['csrf_token'],
));