<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Bootstrap.php';
require_authentication();
$pageSize = 10;
$readQueryValue = static function (string $key): string {
    $value = $_GET[$key] ?? '';

    return is_string($value) ? trim($value) : '';
};
$readPageValue = static function (string $key) use ($readQueryValue): int {
    $value = $readQueryValue($key);

    return preg_match('/^\d+$/', $value) === 1 ? max(1, (int) $value) : 1;
};
$requestedTab = $readQueryValue('tab') ?: 'pending';
$activeTab = in_array($requestedTab, ['pending', 'active', 'history', 'all_history'], true) ? $requestedTab : 'pending';
$pendingPage = $readPageValue('pending_page');
$activePage = $readPageValue('active_page');
$historyPage = $readPageValue('history_page');
$allHistoryPage = $readPageValue('all_history_page');
$requestedResult = $readQueryValue('result') ?: 'all';
$allHistoryResult = in_array($requestedResult, ['all', 'match', 'unmatch'], true) ? $requestedResult : 'all';
$allHistorySeekId = $readQueryValue('seek_id');
$allHistorySeekId = substr($allHistorySeekId, 0, 100);
$allHistoryProposedUuid = substr($readQueryValue('proposed_uuid'), 0, 100);
$allHistoryReviewedBy = substr($readQueryValue('reviewed_by'), 0, 100);
$reviewerId = (string) $_SESSION['dbstffid'];
$allowedFilters = ['matched', 'unmatched', 'all'];
$currentFilter = $readQueryValue('filter') ?: 'all';
if (!in_array($currentFilter, $allowedFilters, true)) {
    $currentFilter = 'all';
}
$pendingTotal = count_pending_reviews($currentFilter);
$pendingPages = max(1, (int) ceil($pendingTotal / $pageSize));
if ($pendingPage > $pendingPages) {
    header('Location: ' . public_url('index.php?' . http_build_query([
        'tab' => 'pending',
        'pending_page' => $pendingPages,
        'filter' => $currentFilter,
    ])));
    exit;
}

$reviews = find_pending_reviews($pageSize, ($pendingPage - 1) * $pageSize, $currentFilter);
$assignedTotal = count_assigned_reviews($reviewerId);
$assignedPages = max(1, (int) ceil($assignedTotal / $pageSize));
$activePage = min($activePage, $assignedPages);
$assignedReviews = find_assigned_reviews($reviewerId, $pageSize, ($activePage - 1) * $pageSize);
$completedTotal = count_completed_reviews($reviewerId);
$historyPages = max(1, (int) ceil($completedTotal / $pageSize));
$historyPage = min($historyPage, $historyPages);
$completedReviews = find_completed_reviews($reviewerId, $pageSize, ($historyPage - 1) * $pageSize);
$allHistoryTotal = count_all_review_history($allHistoryResult, $allHistorySeekId, $allHistoryProposedUuid, $allHistoryReviewedBy);
$allHistoryPages = max(1, (int) ceil($allHistoryTotal / $pageSize));
$allHistoryPage = min($allHistoryPage, $allHistoryPages);
$allHistoryReviews = find_all_review_history(
    $allHistoryResult,
    $allHistorySeekId,
    $allHistoryProposedUuid,
    $allHistoryReviewedBy,
    $pageSize,
    ($allHistoryPage - 1) * $pageSize
);
$queueMessage = $_SESSION['queue_message'] ?? null;
unset($_SESSION['queue_message']);
$reviewTransition = $_SESSION['review_transition'] ?? null;
unset($_SESSION['review_transition']);
$pageUrl = static function (string $tab, int $page) use ($allHistoryResult, $allHistorySeekId, $allHistoryProposedUuid, $allHistoryReviewedBy, $currentFilter): string {
    $parameter = match ($tab) {
        'pending' => 'pending_page',
        'active' => 'active_page',
        'history' => 'history_page',
        default => 'all_history_page',
    };

    $query = [
        'tab' => $tab,
        $parameter => $page,
    ];

    if ($tab === 'all_history') {
        $query['result'] = $allHistoryResult;
        if ($allHistorySeekId !== '') {
            $query['seek_id'] = $allHistorySeekId;
        }
        if ($allHistoryProposedUuid !== '') {
            $query['proposed_uuid'] = $allHistoryProposedUuid;
        }
        if ($allHistoryReviewedBy !== '') {
            $query['reviewed_by'] = $allHistoryReviewedBy;
        }
    }

    if ($tab === 'pending') {
        $query['filter'] = $currentFilter;
    }

    return public_url('index.php?' . http_build_query($query));
};
$paginationItems = static function (int $currentPage, int $totalPages): array {
    if ($totalPages <= 7) {
        return range(1, $totalPages);
    }

    if ($currentPage <= 4) {
        $endPage = min($totalPages - 1, $currentPage + 4);

        return array_merge(range(1, $endPage), ['ellipsis', $totalPages]);
    }

    if ($currentPage > 4 && $currentPage >= $totalPages - 4) {
        $startPage = max(2, min($currentPage - 1, $totalPages - 4));

        return array_merge([1, 'ellipsis'], range($startPage, $totalPages));
    }

    return array_merge([1, 'ellipsis'], range($currentPage - 1, $currentPage + 3), ['ellipsis', $totalPages]);
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Review Queue | Candidate UUID Match</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(public_url('assets/admin.css'), ENT_QUOTES, 'UTF-8') ?>">
    <script src="<?= htmlspecialchars(public_url('assets/app.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
</head>
<body data-page="queue" data-pending-count="<?= (int) $pendingTotal ?>" data-poll-url="<?= htmlspecialchars(public_url('check-new-reviews.php'), ENT_QUOTES, 'UTF-8') ?>" data-queue-url="<?= htmlspecialchars(public_url('index.php?tab=pending&pending_page=1&filter=all'), ENT_QUOTES, 'UTF-8') ?>">
    <div class="shell">
        <header class="topbar">
            <div>
                <p class="eyebrow">Candidate UUID Match</p>
                <h1>Review queue</h1>
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
            <?php if ($queueMessage !== null): ?>
                <div class="notice <?= $queueMessage['type'] === 'success' ? 'notice-success' : 'notice-warning' ?>" role="status">
                    <?= htmlspecialchars((string) $queueMessage['text'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            <?php if (is_array($reviewTransition) && isset($reviewTransition['decision'], $reviewTransition['seekid_detail'])): ?>
                <div class="notice notice-success" role="status">
                    <strong>Previous review completed:</strong>
                    Candidate #<?= (int) $reviewTransition['seekid_detail'] ?> &middot;
                    <?= htmlspecialchars((string) $reviewTransition['decision'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <div class="page-heading">
                <div>
                    <p class="eyebrow">Review workspace</p>
                    <h2>UUID match review queue</h2>
                </div>
                <span class="count-badge"><?= $pendingTotal ?> pending</span>
            </div>

            <section class="review-tabs" aria-label="Review views">
                <a class="review-tab <?= $activeTab === 'pending' ? 'is-active' : '' ?>" data-loading-label="Loading..." href="<?= htmlspecialchars($pageUrl('pending', 1), ENT_QUOTES, 'UTF-8') ?>">Needs attention <span><?= $pendingTotal ?></span></a>
                <a class="review-tab <?= $activeTab === 'active' ? 'is-active' : '' ?>" data-loading-label="Loading..." href="<?= htmlspecialchars($pageUrl('active', 1), ENT_QUOTES, 'UTF-8') ?>">My active reviews <span><?= $assignedTotal ?></span></a>
                <a class="review-tab <?= $activeTab === 'history' ? 'is-active' : '' ?>" data-loading-label="Loading..." href="<?= htmlspecialchars($pageUrl('history', 1), ENT_QUOTES, 'UTF-8') ?>">My review history <span><?= $completedTotal ?></span></a>
                <a class="review-tab <?= $activeTab === 'all_history' ? 'is-active' : '' ?>" data-loading-label="Loading..." href="<?= htmlspecialchars($pageUrl('all_history', 1), ENT_QUOTES, 'UTF-8') ?>">All Review History <span><?= $allHistoryTotal ?></span></a>
            </section>

            <?php if ($activeTab === 'pending'): ?>
                <section class="panel tab-panel" aria-labelledby="queue-title">
                    <div class="panel-header">
                        <h3 id="queue-title">Needs attention</h3>

                        <!-- === FILTER UI === -->
                        <div class="panel-filters" role="group" aria-label="Filter pending reviews">
                            <a class="filter-chip loading-link <?= $currentFilter === 'all' ? 'is-active' : '' ?>"
                            data-loading-label="Loading..."
                            href="<?= htmlspecialchars(public_url('index.php?' . http_build_query(['tab' => 'pending', 'pending_page' => 1, 'filter' => 'all'])), ENT_QUOTES, 'UTF-8') ?>">
                                All
                            </a>
                            <a class="filter-chip loading-link <?= $currentFilter === 'matched' ? 'is-active' : '' ?>"
                            data-loading-label="Loading..."
                            href="<?= htmlspecialchars(public_url('index.php?' . http_build_query(['tab' => 'pending', 'pending_page' => 1, 'filter' => 'matched'])), ENT_QUOTES, 'UTF-8') ?>">
                                Matched
                            </a>
                            <a class="filter-chip loading-link <?= $currentFilter === 'unmatched' ? 'is-active' : '' ?>"
                            data-loading-label="Loading..."
                            href="<?= htmlspecialchars(public_url('index.php?' . http_build_query(['tab' => 'pending', 'pending_page' => 1, 'filter' => 'unmatched'])), ENT_QUOTES, 'UTF-8') ?>">
                                Unmatched
                            </a>
                        </div>

                        <span class="muted">Page <?= $pendingPage ?> of <?= $pendingPages ?></span>
                    </div>

                    <?php if ($reviews === []): ?>
                        <div class="empty-state">
                            <strong>No pending reviews</strong>
                            <span>The queue is clear right now.</span>
                        </div>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table>
                            <thead>
                                <tr>
                                    <th>Review ID</th>
                                    <th>Seek ID</th>
                                    <th>Candidate</th>          <!-- === KOLOM BARU === -->
                                    <th>Proposed UUID</th>
                                    <th>Numeric profile</th>
                                    <th>UUID profile</th>
                                    <th>Status</th>
                                    <th>Assigned reviewer</th>
                                    <th>Created</th>
                                    <th><span class="visually-hidden">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reviews as $review): ?>
                                    <tr>
                                        <td>
                                            <a class="review-link loading-link"
                                            data-loading-label="Opening Review..."
                                            href="<?= htmlspecialchars(public_url('review.php?' . http_build_query(['id' => (int) $review['review_id'], 'filter' => $currentFilter, 'pending_page' => $pendingPage])), ENT_QUOTES, 'UTF-8') ?>">
                                                #<?= (int) $review['review_id'] ?>
                                            </a>
                                        </td>
                                        <td><?= (int) $review['seekid_detail'] ?></td>

                                        <!-- === CANDNO === -->
                                        <td>
                                            <?php if (!empty($review['candno'])): ?>
                                                <span class="badge badge-matched"><?= htmlspecialchars((string) $review['candno'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php else: ?>
                                                <span class="badge badge-unmatched">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="uuid"><?= htmlspecialchars((string) $review['proposed_uuid'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><a href="<?= htmlspecialchars((string) $review['numeric_profile_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Open profile</a></td>
                                        <td><a href="<?= htmlspecialchars((string) $review['uuid_profile_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Open profile</a></td>
                                        <td><span class="status status-pending"><?= htmlspecialchars((string) $review['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                        <td><?= htmlspecialchars((string) ($review['assigned_reviewer'] ?? 'Unassigned'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) $review['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <form class="loading-form" data-loading-label="Taking Review..." method="post" action="<?= htmlspecialchars(public_url('take_review.php'), ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="review_id" value="<?= (int) $review['review_id'] ?>">
                                                <input type="hidden" name="filter" value="<?= htmlspecialchars($currentFilter, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="pending_page" value="<?= $pendingPage ?>">
                                                <button class="button button-primary" type="submit">Take Review</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            </table>
                        </div>

                        <?php if ($pendingPages > 1): ?>
                            <nav class="pagination" aria-label="Pending review pages">
                                <a class="pagination-link loading-link" data-loading-label="Loading..."
                                href="<?= htmlspecialchars($pageUrl('pending', 1), ENT_QUOTES, 'UTF-8') ?>">FIRST</a>

                                <?php if ($pendingPage > 1): ?>
                                    <a class="pagination-control loading-link" data-loading-label="Loading..."
                                    href="<?= htmlspecialchars($pageUrl('pending', $pendingPage - 1), ENT_QUOTES, 'UTF-8') ?>">Previous</a>
                                <?php else: ?>
                                    <span class="pagination-control is-disabled" aria-disabled="true">Previous</span>
                                <?php endif; ?>

                                <?php foreach ($paginationItems($pendingPage, $pendingPages) as $page): ?>
                                    <?php if ($page === 'ellipsis'): ?>
                                        <span class="pagination-ellipsis" aria-hidden="true">...</span>
                                    <?php else: ?>
                                        <a class="pagination-link loading-link <?= $page === $pendingPage ? 'is-current' : '' ?>"
                                        data-loading-label="Loading..."
                                        href="<?= htmlspecialchars($pageUrl('pending', $page), ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $page === $pendingPage ? 'aria-current="page"' : '' ?>><?= $page ?></a>
                                    <?php endif; ?>
                                <?php endforeach; ?>

                                <?php if ($pendingPage < $pendingPages): ?>
                                    <a class="pagination-control loading-link" data-loading-label="Loading..."
                                    href="<?= htmlspecialchars($pageUrl('pending', $pendingPage + 1), ENT_QUOTES, 'UTF-8') ?>">Next</a>
                                <?php else: ?>
                                    <span class="pagination-control is-disabled" aria-disabled="true">Next</span>
                                <?php endif; ?>

                                <a class="pagination-link loading-link" data-loading-label="Loading..."
                                href="<?= htmlspecialchars($pageUrl('pending', $pendingPages), ENT_QUOTES, 'UTF-8') ?>">END</a>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            <?php elseif ($activeTab === 'active'): ?>

                <section class="panel tab-panel" aria-labelledby="assigned-title">
                    <div class="panel-header">
                        <h3 id="assigned-title">My active reviews</h3>
                        <span class="muted">Page <?= $activePage ?> of <?= $assignedPages ?></span>
                    </div>
                    <?php if ($assignedReviews === []): ?>
                        <div class="empty-state">
                            <strong>No active reviews</strong>
                            <span>Reviews you take will remain available here until completed.</span>
                        </div>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table>
                            <thead>
                                <tr>
                                    <th>Review ID</th>
                                    <th>Seek ID</th>
                                    <th>Proposed UUID</th>
                                    <th>Numeric profile</th>
                                    <th>UUID profile</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th><span class="visually-hidden">Action</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assignedReviews as $review): ?>
                                    <tr>
                                        <td><a class="review-link loading-link" data-loading-label="Opening Review..." href="<?= htmlspecialchars(public_url('review.php?id=' . (int) $review['review_id']), ENT_QUOTES, 'UTF-8') ?>">#<?= (int) $review['review_id'] ?></a></td>
                                        <td><?= (int) $review['seekid_detail'] ?></td>
                                        <td class="uuid"><?= htmlspecialchars((string) $review['proposed_uuid'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><a href="<?= htmlspecialchars((string) $review['numeric_profile_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Open profile</a></td>
                                        <td><a href="<?= htmlspecialchars((string) $review['uuid_profile_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Open profile</a></td>
                                        <td><span class="status status-assigned"><?= htmlspecialchars((string) $review['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                        <td><?= htmlspecialchars((string) $review['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <a class="button button-primary loading-link" data-loading-label="Opening Review..." href="<?= htmlspecialchars(public_url('review.php?id=' . (int) $review['review_id']), ENT_QUOTES, 'UTF-8') ?>">Continue Review</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            </table>
                        </div>
                        <?php if ($assignedPages > 1): ?>
                            <nav class="pagination" aria-label="Active review pages">
                                <?php if ($activePage > 1): ?>
                                    <a class="pagination-control loading-link" data-loading-label="Loading..." href="<?= htmlspecialchars($pageUrl('active', $activePage - 1), ENT_QUOTES, 'UTF-8') ?>">Previous</a>
                                <?php else: ?>
                                    <span class="pagination-control is-disabled" aria-disabled="true">Previous</span>
                                <?php endif; ?>
                                <a class="pagination-link loading-link" data-loading-label="Loading..." href="<?= htmlspecialchars($pageUrl('active', 1), ENT_QUOTES, 'UTF-8') ?>">FIRST</a>
                                <?php foreach ($paginationItems($activePage, $assignedPages) as $page): ?>
                                    <?php if ($page === 'ellipsis'): ?>
                                        <span class="pagination-ellipsis" aria-hidden="true">...</span>
                                    <?php else: ?>
                                        <a class="pagination-link loading-link <?= $page === $activePage ? 'is-current' : '' ?>" data-loading-label="Loading..." href="<?= htmlspecialchars($pageUrl('active', $page), ENT_QUOTES, 'UTF-8') ?>" <?= $page === $activePage ? 'aria-current="page"' : '' ?>><?= $page ?></a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <a class="pagination-link loading-link" data-loading-label="Loading..." href="<?= htmlspecialchars($pageUrl('active', $assignedPages), ENT_QUOTES, 'UTF-8') ?>">END</a>
                                <?php if ($activePage < $assignedPages): ?>
                                    <a class="pagination-control loading-link" data-loading-label="Loading..." href="<?= htmlspecialchars($pageUrl('active', $activePage + 1), ENT_QUOTES, 'UTF-8') ?>">Next</a>
                                <?php else: ?>
                                    <span class="pagination-control is-disabled" aria-disabled="true">Next</span>
                                <?php endif; ?>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            <?php elseif ($activeTab === 'history'): ?>
                <section class="panel tab-panel" aria-labelledby="history-title">
                    <div class="panel-header">
                        <h3 id="history-title">My review history</h3>
                        <span class="muted">Page <?= $historyPage ?> of <?= $historyPages ?></span>
                    </div>
                    <?php if ($completedReviews === []): ?>
                        <div class="empty-state">
                            <strong>No completed reviews</strong>
                            <span>Your MATCH and UNMATCH decisions will appear here.</span>
                        </div>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table>
                            <thead>
                                <tr>
                                    <th>Review ID</th>
                                    <th>Seek ID</th>
                                    <th>Proposed UUID</th>
                                    <th>Decision</th>
                                    <th>Reviewed By</th>
                                    <th>Reviewed at</th>
                                    <th>Reason</th>
                                    <th><span class="visually-hidden">Action</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($completedReviews as $review): ?>
                                    <tr>
                                        <td><a class="review-link loading-link" data-loading-label="Opening Review..." href="<?= htmlspecialchars(public_url('review.php?id=' . (int) $review['review_id']), ENT_QUOTES, 'UTF-8') ?>">#<?= (int) $review['review_id'] ?></a></td>
                                        <td><?= (int) $review['seekid_detail'] ?></td>
                                        <td class="uuid"><?= htmlspecialchars((string) $review['proposed_uuid'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><span class="status <?= (string) $review['status'] === 'approved' ? 'status-approved' : 'status-rejected' ?>"><?= (string) $review['status'] === 'approved' ? 'MATCH' : 'UNMATCH' ?></span></td>
                                        <td><?= htmlspecialchars((string) ($review['reviewed_by'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) $review['reviewed_at'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="reason-cell"><?= htmlspecialchars((string) ($review['review_reason'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <a class="button button-secondary loading-link" data-loading-label="Opening Details..." href="<?= htmlspecialchars(public_url('review.php?id=' . (int) $review['review_id']), ENT_QUOTES, 'UTF-8') ?>">View Details</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            </table>
                        </div>
                        <?php if ($historyPages > 1): ?>
                            <nav class="pagination" aria-label="Review history pages">
                                <?php if ($historyPage > 1): ?>
                                    <a class="pagination-control loading-link" data-loading-label="Loading..." href="<?= htmlspecialchars($pageUrl('history', $historyPage - 1), ENT_QUOTES, 'UTF-8') ?>">Previous</a>
                                <?php else: ?>
                                    <span class="pagination-control is-disabled" aria-disabled="true">Previous</span>
                                <?php endif; ?>
                                <a class="pagination-link loading-link" data-loading-label="Loading..." href="<?= htmlspecialchars($pageUrl('history', 1), ENT_QUOTES, 'UTF-8') ?>">FIRST</a>
                                <?php foreach ($paginationItems($historyPage, $historyPages) as $page): ?>
                                    <?php if ($page === 'ellipsis'): ?>
                                        <span class="pagination-ellipsis" aria-hidden="true">...</span>
                                    <?php else: ?>
                                        <a class="pagination-link loading-link <?= $page === $historyPage ? 'is-current' : '' ?>" data-loading-label="Loading..." href="<?= htmlspecialchars($pageUrl('history', $page), ENT_QUOTES, 'UTF-8') ?>" <?= $page === $historyPage ? 'aria-current="page"' : '' ?>><?= $page ?></a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <a class="pagination-link loading-link" data-loading-label="Loading..." href="<?= htmlspecialchars($pageUrl('history', $historyPages), ENT_QUOTES, 'UTF-8') ?>">END</a>
                                <?php if ($historyPage < $historyPages): ?>
                                    <a class="pagination-control loading-link" data-loading-label="Loading..." href="<?= htmlspecialchars($pageUrl('history', $historyPage + 1), ENT_QUOTES, 'UTF-8') ?>">Next</a>
                                <?php else: ?>
                                    <span class="pagination-control is-disabled" aria-disabled="true">Next</span>
                                <?php endif; ?>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            <?php else: ?>
                <section class="panel tab-panel" aria-labelledby="all-history-title">
                    <div class="panel-header">
                        <div>
                            <h3 id="all-history-title">All Review History</h3>
                            <span class="muted">Completed reviews from all reviewers</span>
                        </div>
                        <span class="muted">Page <?= $allHistoryPage ?> of <?= $allHistoryPages ?></span>
                    </div>
                    <form class="history-filter" method="get" action="<?= htmlspecialchars(public_url('index.php'), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="tab" value="all_history">
                        <label for="history-result">Result</label>
                        <select id="history-result" name="result">
                            <option value="all" <?= $allHistoryResult === 'all' ? 'selected' : '' ?>>All</option>
                            <option value="match" <?= $allHistoryResult === 'match' ? 'selected' : '' ?>>MATCH</option>
                            <option value="unmatch" <?= $allHistoryResult === 'unmatch' ? 'selected' : '' ?>>UNMATCH</option>
                        </select>
                        <label for="history-seek-id">Seek ID</label>
                        <input id="history-seek-id" name="seek_id" type="search" inputmode="numeric" maxlength="20" value="<?= htmlspecialchars($allHistorySeekId, ENT_QUOTES, 'UTF-8') ?>">
                        <label for="history-proposed-uuid">Proposed UUID</label>
                        <input id="history-proposed-uuid" name="proposed_uuid" type="search" maxlength="100" value="<?= htmlspecialchars($allHistoryProposedUuid, ENT_QUOTES, 'UTF-8') ?>">
                        <label for="history-reviewed-by">Reviewed By</label>
                        <input id="history-reviewed-by" name="reviewed_by" type="search" maxlength="100" value="<?= htmlspecialchars($allHistoryReviewedBy, ENT_QUOTES, 'UTF-8') ?>">
                        <button class="button button-secondary" type="submit">Apply</button>
                        <a class="button button-quiet" href="<?= htmlspecialchars(public_url('index.php?tab=all_history&all_history_page=1&result=all'), ENT_QUOTES, 'UTF-8') ?>">Clear Filters</a>
                    </form>
                    <?php if ($allHistoryReviews === []): ?>
                        <div class="empty-state">
                            <strong><?= $allHistoryResult === 'match' ? 'No MATCH reviews found.' : ($allHistoryResult === 'unmatch' ? 'No UNMATCH reviews found.' : 'No completed reviews found.') ?></strong>
                        </div>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Review ID</th>
                                        <th>Seek ID</th>
                                        <th>Proposed UUID</th>
                                        <th>Result</th>
                                        <th>Reviewed By</th>
                                        <th>Reviewed At</th>
                                        <th>Review Reason</th>
                                        <th>Source Table</th>
                                        <th>Created At</th>
                                        <th><span class="visually-hidden">Action</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allHistoryReviews as $review): ?>
                                        <tr>
                                            <td><a class="review-link loading-link" data-loading-label="Opening Review..." href="<?= htmlspecialchars(public_url('review.php?id=' . (int) $review['review_id']), ENT_QUOTES, 'UTF-8') ?>">#<?= (int) $review['review_id'] ?></a></td>
                                            <td><?= (int) $review['seekid_detail'] ?></td>
                                            <td class="uuid uuid-history" title="<?= htmlspecialchars((string) $review['proposed_uuid'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $review['proposed_uuid'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><span class="status <?= (string) $review['status'] === 'approved' ? 'status-approved' : 'status-rejected' ?>"><?= (string) $review['status'] === 'approved' ? 'MATCH' : 'UNMATCH' ?></span></td>
                                            <td><?= htmlspecialchars((string) ($review['reviewed_by'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) ($review['reviewed_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="reason-cell"><?= htmlspecialchars((string) ($review['review_reason'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) $review['source_table'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars((string) $review['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><a class="button button-secondary loading-link" data-loading-label="Opening Details..." href="<?= htmlspecialchars(public_url('review.php?id=' . (int) $review['review_id']), ENT_QUOTES, 'UTF-8') ?>">View Details</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($allHistoryPages > 1): ?>
                            <nav class="pagination" aria-label="All review history pages">
                                <?php if ($allHistoryPage > 1): ?>
                                    <a class="pagination-control loading-link" data-loading-label="Loading..." href="<?= htmlspecialchars($pageUrl('all_history', $allHistoryPage - 1), ENT_QUOTES, 'UTF-8') ?>">Previous</a>
                                <?php else: ?>
                                    <span class="pagination-control is-disabled" aria-disabled="true">Previous</span>
                                <?php endif; ?>
                                <a class="pagination-link loading-link" data-loading-label="Loading..." href="<?= htmlspecialchars($pageUrl('all_history', 1), ENT_QUOTES, 'UTF-8') ?>">FIRST</a>
                                <?php foreach ($paginationItems($allHistoryPage, $allHistoryPages) as $page): ?>
                                    <?php if ($page === 'ellipsis'): ?>
                                        <span class="pagination-ellipsis" aria-hidden="true">...</span>
                                    <?php else: ?>
                                        <a class="pagination-link loading-link <?= $page === $allHistoryPage ? 'is-current' : '' ?>" data-loading-label="Loading..." href="<?= htmlspecialchars($pageUrl('all_history', $page), ENT_QUOTES, 'UTF-8') ?>" <?= $page === $allHistoryPage ? 'aria-current="page"' : '' ?>><?= $page ?></a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <a class="pagination-link loading-link" data-loading-label="Loading..." href="<?= htmlspecialchars($pageUrl('all_history', $allHistoryPages), ENT_QUOTES, 'UTF-8') ?>">END</a>
                                <?php if ($allHistoryPage < $allHistoryPages): ?>
                                    <a class="pagination-control loading-link" data-loading-label="Loading..." href="<?= htmlspecialchars($pageUrl('all_history', $allHistoryPage + 1), ENT_QUOTES, 'UTF-8') ?>">Next</a>
                                <?php else: ?>
                                    <span class="pagination-control is-disabled" aria-disabled="true">Next</span>
                                <?php endif; ?>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>