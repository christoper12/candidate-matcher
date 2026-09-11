<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Bootstrap.php';
require_authentication();

$reviewId = filter_var($_POST['review_id'] ?? null, FILTER_VALIDATE_INT);
$decision = (string) ($_POST['decision'] ?? '');
$reviewReason = trim((string) ($_POST['review_reason'] ?? ''));
$redirectToReview = static function (int $id, string $type, string $text): never {
    $_SESSION['review_message'] = [
        'type' => $type,
        'text' => $text,
    ];
    header('Location: ' . public_url('review.php?id=' . $id));
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . public_url('index.php'));
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    $redirectToReview(is_int($reviewId) && $reviewId > 0 ? $reviewId : 0, 'warning', 'The decision form has expired. Please try again.');
}

if ($reviewId === false || $reviewId <= 0 || !in_array($decision, ['MATCH', 'UNMATCH'], true)) {
    $redirectToReview(is_int($reviewId) && $reviewId > 0 ? $reviewId : 0, 'warning', 'Invalid review decision.');
}

if ($decision === 'UNMATCH' && $reviewReason === '') {
    $redirectToReview($reviewId, 'warning', 'A review reason is required for UNMATCH.');
}

try {
    $result = decide_review(
        $reviewId,
        (string) $_SESSION['dbstffid'],
        $decision,
        $reviewReason
    );
} catch (Throwable $exception) {
    error_log('Unable to submit review decision: ' . $exception->getMessage());
    $redirectToReview($reviewId, 'warning', 'The review decision could not be saved. Please try again.');
}

$messages = [
    'matched' => 'Review completed: MATCH.',
    'unmatched' => 'Review completed: UNMATCH.',
    'not_found' => 'Review not found.',
    'not_owner' => 'This review is assigned to another reviewer.',
    'completed' => 'This review has already been completed.',
    'candidate_not_found' => 'The candidate record was not found. The review was not completed.',
    'unavailable' => 'This review is not available for a decision.',
    'invalid_decision' => 'Invalid review decision.',
];
$messageType = in_array($result, ['matched', 'unmatched'], true) ? 'success' : 'warning';
$message = $messages[$result] ?? 'The review decision was rejected.';

if ($messageType !== 'success') {
    $redirectToReview($reviewId, $messageType, $message);
}

try {
    $completedReview = find_review_by_id($reviewId);
    if ($completedReview !== null) {
        $_SESSION['review_transition'] = [
            'decision' => $decision,
            'seekid_detail' => (int) $completedReview['seekid_detail'],
        ];
    }
} catch (Throwable $exception) {
    error_log('Unable to load completed review transition: ' . $exception->getMessage());
}

$reviewerId = (string) $_SESSION['dbstffid'];

try {
    $assignedReviewId = find_next_assigned_review_id($reviewerId);
} catch (Throwable $exception) {
    error_log('Unable to find next assigned review: ' . $exception->getMessage());
    $assignedReviewId = null;
}

if ($assignedReviewId !== null) {
    $_SESSION['review_message'] = [
        'type' => 'success',
        'text' => $message,
    ];
    header('Location: ' . public_url('review.php?id=' . $assignedReviewId));
    exit;
}

for ($attempt = 0; $attempt < 3; $attempt++) {
    try {
        $nextReviewId = find_next_pending_review_id();
    } catch (Throwable $exception) {
        error_log('Unable to find next pending review: ' . $exception->getMessage());
        break;
    }

    if ($nextReviewId === null) {
        $_SESSION['queue_message'] = [
            'type' => 'success',
            'text' => $message . ' There are no more pending reviews.',
        ];
        header('Location: ' . public_url('index.php'));
        exit;
    }

    try {
        $takeResult = take_review($nextReviewId, $reviewerId);
    } catch (Throwable $exception) {
        error_log('Unable to take next review: ' . $exception->getMessage());
        continue;
    }

    if ($takeResult === 'claimed' || $takeResult === 'already_assigned_to_you') {
        $_SESSION['review_message'] = [
            'type' => 'success',
            'text' => $message,
        ];
        header('Location: ' . public_url('review.php?id=' . $nextReviewId));
        exit;
    }
}

$_SESSION['queue_message'] = [
    'type' => 'success',
    'text' => $message . ' The next review could not be claimed, so please choose another one from the queue.',
];
header('Location: ' . public_url('index.php'));
exit;