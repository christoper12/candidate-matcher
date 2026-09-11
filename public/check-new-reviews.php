<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Bootstrap.php';
require_authentication();

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'pending_count' => count_pending_reviews(),
], JSON_THROW_ON_ERROR);
