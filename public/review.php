<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Bootstrap.php';
require_authentication();

$reviewId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$allowedFilters = ['all', 'matched', 'unmatched'];
$filter = is_string($_GET['filter'] ?? null) && in_array($_GET['filter'], $allowedFilters, true)
    ? $_GET['filter']
    : 'all';
$pendingPage = filter_var($_GET['pending_page'] ?? 1, FILTER_VALIDATE_INT);
$pendingPage = is_int($pendingPage) && $pendingPage > 0 ? $pendingPage : 1;
$review = is_int($reviewId) && $reviewId > 0 ? find_review_by_id($reviewId) : null;
$seekidDetail = $review === null ? 0 : (int) $review['seekid_detail'];
$previouslyMatched = $review !== null && is_candidate_previously_matched($seekidDetail);
$candidateNumber = $review === null ? null : find_candidate_number($seekidDetail);
$reviewMessage = $_SESSION['review_message'] ?? null;
unset($_SESSION['review_message']);
$reviewTransition = $_SESSION['review_transition'] ?? null;
unset($_SESSION['review_transition']);

if ($review === null) {
    http_response_code(404);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $review === null ? 'Review not found' : 'Review #' . (int) $review['review_id'] ?> | Candidate UUID Match</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(public_url('assets/admin.css'), ENT_QUOTES, 'UTF-8') ?>">
    <script src="<?= htmlspecialchars(public_url('assets/app.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
</head>
<body data-page="detail" data-pending-count="<?= (int) count_pending_reviews() ?>" data-poll-url="<?= htmlspecialchars(public_url('check-new-reviews.php'), ENT_QUOTES, 'UTF-8') ?>" data-queue-url="<?= htmlspecialchars(public_url('index.php?' . http_build_query(['tab' => 'pending', 'pending_page' => $pendingPage, 'filter' => $filter])), ENT_QUOTES, 'UTF-8') ?>">
    <div class="shell">
        <header class="topbar">
            <div>
                <p class="eyebrow">Candidate UUID Match</p>
                <h1>Review detail</h1>
            </div>
            <div class="account">
                <span><?= htmlspecialchars($_SESSION['dbstffnames'] . ' ' . $_SESSION['dbstffsurname'], ENT_QUOTES, 'UTF-8') ?></span>
                <form class="loading-form" data-loading-label="Signing out..." method="post" action="<?= htmlspecialchars(public_url('logout.php'), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <button class="button button-quiet" type="submit">Sign out</button>
                </form>
            </div>
        </header>

        <main class="content">
            <a class="back-link loading-link" data-loading-label="Returning to Queue..." href="<?= htmlspecialchars(public_url('index.php?' . http_build_query(['tab' => 'pending', 'pending_page' => $pendingPage, 'filter' => $filter])), ENT_QUOTES, 'UTF-8') ?>">&larr; Back to queue</a>
            <?php if ($reviewMessage !== null): ?>
                <div class="notice <?= $reviewMessage['type'] === 'success' ? 'notice-success' : 'notice-warning' ?>" role="status">
                    <?= htmlspecialchars((string) $reviewMessage['text'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            <?php if ($review === null): ?>
                <section class="panel empty-state">
                    <strong>Review not found</strong>
                    <span>The requested review does not exist.</span>
                </section>
            <?php else: ?>
                <?php if (is_array($reviewTransition) && isset($reviewTransition['decision'], $reviewTransition['seekid_detail'])): ?>
                    <section class="review-transition" aria-label="Review transition">
                        <div class="transition-step transition-completed">
                            <div class="transition-label">&#10003; PREVIOUS REVIEW</div>
                            <div class="transition-candidate">Candidate #<?= (int) $reviewTransition['seekid_detail'] ?></div>
                            <div class="transition-decision"><?= htmlspecialchars((string) $reviewTransition['decision'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="transition-description">Review completed successfully</div>
                        </div>
                        <div class="transition-arrow" aria-hidden="true">&darr;</div>
                        <div class="transition-step transition-current">
                            <div class="transition-label">&rarr; CURRENT REVIEW</div>
                            <div class="transition-candidate">Candidate #<?= (int) $review['seekid_detail'] ?></div>
                            <div class="transition-description">
                                <?php if ((string) $review['status'] === 'assigned' && (string) ($review['assigned_reviewer'] ?? '') === (string) $_SESSION['dbstffid']): ?>
                                    Assigned to you
                                <?php else: ?>
                                    <?= htmlspecialchars((string) $review['status'], ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>
                <div class="page-heading">
                    <div>
                        <p class="eyebrow">Review #<?= (int) $review['review_id'] ?></p>
                        <h2>UUID match review</h2>
                    </div>
                    <div class="page-heading-badges">
                        <?php if ($candidateNumber !== null): ?>
                            <span class="badge-candidate-number">Candidate No: <span class="uuid"><?= htmlspecialchars($candidateNumber, ENT_QUOTES, 'UTF-8') ?></span></span>
                        <?php endif; ?>
                        <?php if ($previouslyMatched): ?>
                            <span class="badge-previously-matched">Previously Matched</span>
                        <?php endif; ?>
                        <span class="status status-<?= htmlspecialchars((string) $review['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $review['status'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>

                <section class="panel detail-grid">
                    <div class="detail-item"><span>Seek ID</span><strong><?= (int) $review['seekid_detail'] ?></strong></div>
                    <div class="detail-item"><span>Proposed UUID</span><strong class="uuid"><?= htmlspecialchars((string) $review['proposed_uuid'], ENT_QUOTES, 'UTF-8') ?></strong></div>
                    <div class="detail-item"><span>Assigned reviewer</span><strong><?= htmlspecialchars((string) ($review['assigned_reviewer'] ?? 'Unassigned'), ENT_QUOTES, 'UTF-8') ?></strong></div>
                    <div class="detail-item"><span>Created at</span><strong><?= htmlspecialchars((string) $review['created_at'], ENT_QUOTES, 'UTF-8') ?></strong></div>
                    <div class="detail-item"><span>Source table</span><strong><?= htmlspecialchars((string) $review['source_table'], ENT_QUOTES, 'UTF-8') ?></strong></div>
                    <div class="detail-item"><span>Comparison version</span><strong><?= htmlspecialchars((string) $review['comparison_version'], ENT_QUOTES, 'UTF-8') ?></strong></div>
                    <div class="detail-item"><span>Numeric profile</span><a class="button button-secondary" href="<?= htmlspecialchars((string) $review['numeric_profile_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Open Numeric Profile</a></div>
                    <div class="detail-item"><span>UUID profile</span><a class="button button-secondary" href="<?= htmlspecialchars((string) $review['uuid_profile_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Open UUID Profile</a></div>
                </section>

                <?php if ((string) $review['status'] === 'pending'): ?>
                    <section class="review-action">
                        <form class="loading-form" data-loading-label="Taking Review..." method="post" action="<?= htmlspecialchars(public_url('take_review.php'), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="review_id" value="<?= (int) $review['review_id'] ?>">
                            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="pending_page" value="<?= $pendingPage ?>">
                            <button class="button button-primary" type="submit">Take Review</button>
                        </form>
                    </section>
                <?php elseif ((string) $review['status'] === 'assigned' && (string) ($review['assigned_reviewer'] ?? '') === (string) $_SESSION['dbstffid']): ?>
                    <section class="panel decision-panel">
                        <?php if ($previouslyMatched): ?>
                            <div class="banner-warning" role="status">
                                This candidate has been matched before. You only need to verify or update the UUID match review.
                            </div>
                        <?php endif; ?>
                        <div class="panel-header">
                            <h3>Review decision</h3>
                            <span class="muted">Compare both profiles before submitting</span>
                        </div>
                        <form class="decision-form" method="post" action="<?= htmlspecialchars(public_url('review-submit.php'), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="review_id" value="<?= (int) $review['review_id'] ?>">
                            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="pending_page" value="<?= $pendingPage ?>">
                            <label class="reason-label" for="review_reason">Review reason <span>(optional for MATCH, required for UNMATCH)</span></label>
                            <textarea id="review_reason" name="review_reason" rows="4" maxlength="10000" placeholder="Explain the comparison result..."></textarea>
                            <div class="decision-actions">
                                <button class="button button-match" type="submit" name="decision" value="MATCH">MATCH</button>
                                <button class="button button-unmatch" type="submit" name="decision" value="UNMATCH">UNMATCH</button>
                            </div>
                        </form>
                    </section>
                <?php elseif ((string) $review['status'] === 'assigned'): ?>
                    <div class="notice notice-warning" role="status">This review is assigned to another reviewer.</div>
                <?php endif; ?>

                <section class="panel detail-text">
                    <div>
                        <h3>Comparison evidence</h3>
                        <pre><?= htmlspecialchars((string) $review['comparison_evidence'], ENT_QUOTES, 'UTF-8') ?></pre>
                    </div>
                </section>

                <?php if (in_array((string) $review['status'], ['approved', 'rejected'], true)): ?>
                    <section class="panel detail-text decision-summary">
                        <h3>Decision summary</h3>
                        <p><strong><?= (string) $review['status'] === 'approved' ? 'MATCH' : 'UNMATCH' ?></strong> by <?= htmlspecialchars((string) ($review['reviewed_by'] ?? ''), ENT_QUOTES, 'UTF-8') ?> on <?= htmlspecialchars((string) ($review['reviewed_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if ((string) ($review['review_reason'] ?? '') !== ''): ?>
                            <p><?= nl2br(htmlspecialchars((string) $review['review_reason'], ENT_QUOTES, 'UTF-8')) ?></p>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>