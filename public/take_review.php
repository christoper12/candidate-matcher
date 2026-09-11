<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Bootstrap.php';
require_authentication();

$redirectToQueue = static function (string $type, string $text): never {
    $_SESSION['queue_message'] = [
        'type' => $type,
        'text' => $text,
    ];
    header('Location: ' . public_url('index.php'));
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $redirectToQueue('warning', 'Invalid review action.');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    $redirectToQueue('warning', 'The review action has expired. Please try again.');
}

$reviewId = filter_var($_POST['review_id'] ?? null, FILTER_VALIDATE_INT);
$reviewerId = (string) ($_SESSION['dbstffid'] ?? '');

if ($reviewId === false || $reviewId <= 0 || $reviewerId === '') {
    $redirectToQueue('warning', 'Invalid review selection.');
}

try {
    $result = take_review((int) $reviewId, $reviewerId);
} catch (Throwable $exception) {
    error_log('Unable to take review: ' . $exception->getMessage());
    $redirectToQueue('warning', 'The review could not be taken. Please try again.');
}

if ($result === 'claimed' || $result === 'already_assigned_to_you') {
    header('Location: ' . public_url('review.php?id=' . (int) $reviewId));
    exit;
}

$messages = [
    'not_found' => 'Review not found.',
    'already_assigned' => 'This review has already been taken by another reviewer.',
    'completed' => 'This review has already been completed and cannot be taken.',
    'unavailable' => 'This review is not available to be taken.',
];
$redirectToQueue('warning', $messages[$result] ?? 'This review cannot be taken.');