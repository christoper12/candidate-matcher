<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Bootstrap.php';
require_authentication();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Invalid logout request.');
}

destroy_app_session();
header('Location: ' . public_url('login.php'));
exit;