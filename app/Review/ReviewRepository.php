<?php

declare(strict_types=1);

// function count_pending_reviews(): int
// {
//     $statement = db_connection()->prepare(
//         'SELECT COUNT(*)
//          FROM seek_uuid_match_review
//          WHERE status = :status'
//     );
//     $statement->execute(['status' => 'pending']);

//     return (int) $statement->fetchColumn();
// }

function count_pending_reviews(string $filter = 'all'): int
{
    $candnoCondition = match ($filter) {
        'matched'   => 'seek_scrap.candno IS NOT NULL AND seek_scrap.candno <> :empty_candno',
        'unmatched' => '(seek_scrap.candno IS NULL OR seek_scrap.candno = :empty_candno)',
        default     => '1=1',
    };

    $statement = db_connection()->prepare(
        "SELECT COUNT(*)
         FROM (
             SELECT seek_uuid_match_review.seekid_detail
             FROM seek_uuid_match_review
             LEFT JOIN seek_scrap_detail
                 ON seek_uuid_match_review.seekid_detail = seek_scrap_detail.seekid_detail
             LEFT JOIN seek_scrap
                 ON seek_scrap_detail.seekid_detail = seek_scrap.seek_scrap_id
             WHERE seek_uuid_match_review.status = :status
               AND {$candnoCondition}
             GROUP BY seek_uuid_match_review.seekid_detail
         ) pending_reviews"
    );

    $statement->bindValue(':status', 'pending', PDO::PARAM_STR);
    if ($filter === 'matched' || $filter === 'unmatched') {
        $statement->bindValue(':empty_candno', '', PDO::PARAM_STR);
    }
    $statement->execute();

    return (int) $statement->fetchColumn();
}

function is_candidate_previously_matched(int $seekidDetail): bool
{
    // TODO(verify): konfirmasi relasi via SHOW CREATE TABLE.
    $statement = db_connection()->prepare(
        'SELECT 1
         FROM seek_scrap_detail
         INNER JOIN seek_scrap
             ON seek_scrap_detail.seekid_detail = seek_scrap.seek_scrap_id
         WHERE seek_scrap_detail.seekid_detail = :seekid_detail
           AND seek_scrap.candno IS NOT NULL
           AND seek_scrap.candno <> :empty_candno
         LIMIT 1'
    );
    $statement->execute([
        'seekid_detail' => $seekidDetail,
        'empty_candno' => '',
    ]);

    return $statement->fetchColumn() !== false;
}

// function find_pending_reviews(int $limit = 25, int $offset = 0): array
// {
//     $statement = db_connection()->prepare(
//         'SELECT review_id, seekid_detail, proposed_uuid, numeric_profile_url,
//                 uuid_profile_url, status, assigned_reviewer, created_at
//          FROM seek_uuid_match_review
//             WHERE status = :status
//             ORDER BY review_id ASC
//             LIMIT :limit OFFSET :offset'
//     );
//         $statement->execute([
//            'status' => 'pending',
//            'limit' => $limit,
//            'offset' => $offset,
//         ]);

//     return $statement->fetchAll();
// }

function find_pending_reviews(int $limit = 25, int $offset = 0, string $filter = 'all'): array
{
    $candnoCondition = match ($filter) {
        'matched'   => 'seek_scrap.candno IS NOT NULL AND seek_scrap.candno <> :empty_candno',
        'unmatched' => '(seek_scrap.candno IS NULL OR seek_scrap.candno = :empty_candno)',
        default     => '1=1', // semua
    };

    $statement = db_connection()->prepare(
        "SELECT seek_uuid_match_review.*, MAX(seek_scrap.candno) AS candno
         FROM seek_uuid_match_review
         INNER JOIN (
             SELECT MIN(seek_uuid_match_review.review_id) AS review_id
             FROM seek_uuid_match_review
             LEFT JOIN seek_scrap_detail
                 ON seek_uuid_match_review.seekid_detail = seek_scrap_detail.seekid_detail
             LEFT JOIN seek_scrap
                 ON seek_scrap_detail.seekid_detail = seek_scrap.seek_scrap_id
             WHERE seek_uuid_match_review.status = :status
               AND {$candnoCondition}
             GROUP BY seek_uuid_match_review.seekid_detail
         ) pending_reviews
             ON pending_reviews.review_id = seek_uuid_match_review.review_id
         LEFT JOIN seek_scrap_detail
             ON seek_uuid_match_review.seekid_detail = seek_scrap_detail.seekid_detail
         LEFT JOIN seek_scrap
             ON seek_scrap_detail.seekid_detail = seek_scrap.seek_scrap_id
         GROUP BY seek_uuid_match_review.review_id
         ORDER BY seek_uuid_match_review.seekid_detail ASC
         LIMIT :limit OFFSET :offset"
    );

    $statement->bindValue(':status', 'pending', PDO::PARAM_STR);
    if ($filter === 'matched' || $filter === 'unmatched') {
        $statement->bindValue(':empty_candno', '', PDO::PARAM_STR);
    }
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);

    $statement->execute();

    return $statement->fetchAll();
}

function find_next_pending_review_id(string $context = 'all'): ?int
{
    // TODO(verify): konfirmasi relasi via SHOW CREATE TABLE.
    $primaryCondition = in_array($context, ['unmatch', 'unmatched'], true)
        ? '(seek_scrap.candno IS NULL OR seek_scrap.candno = :empty_candno)'
        : 'seek_scrap.candno IS NOT NULL AND seek_scrap.candno <> :empty_candno';
    $statement = db_connection()->prepare(
        "SELECT review_id
         FROM seek_uuid_match_review AS pending_review
         WHERE status = :status
         ORDER BY CASE WHEN EXISTS (
             SELECT 1
             FROM seek_scrap_detail
             INNER JOIN seek_scrap
                 ON seek_scrap_detail.seekid_detail = seek_scrap.seek_scrap_id
             WHERE seek_scrap_detail.seekid_detail = pending_review.seekid_detail
               AND {$primaryCondition}
         ) THEN 0 ELSE 1 END,
         review_id ASC
         LIMIT 1"
    );
    $statement->execute([
        'status' => 'pending',
        'empty_candno' => '',
    ]);
    $reviewId = $statement->fetchColumn();

    return $reviewId === false ? null : (int) $reviewId;
}

function count_assigned_reviews(string $reviewerId): int
{
    $statement = db_connection()->prepare(
        'SELECT COUNT(*)
         FROM seek_uuid_match_review
         WHERE status = :status
           AND assigned_reviewer = :assigned_reviewer'
    );
    $statement->execute([
        'status' => 'assigned',
        'assigned_reviewer' => $reviewerId,
    ]);

    return (int) $statement->fetchColumn();
}

function find_candidate_number(int $seekidDetail): ?string
{
    // TODO(verify): konfirmasi relasi via SHOW CREATE TABLE.
    $statement = db_connection()->prepare(
        'SELECT seek_scrap.candno
         FROM seek_scrap_detail
         INNER JOIN seek_scrap
             ON seek_scrap_detail.seekid_detail = seek_scrap.seek_scrap_id
         WHERE seek_scrap_detail.seekid_detail = :seekid_detail
         LIMIT 1'
    );
    $statement->execute(['seekid_detail' => $seekidDetail]);
    $candidateNumber = $statement->fetchColumn();

    if ($candidateNumber === false || $candidateNumber === null || (string) $candidateNumber === '') {
        return null;
    }

    return (string) $candidateNumber;
}

function find_assigned_reviews(string $reviewerId, int $limit = 25, int $offset = 0): array
{
    $statement = db_connection()->prepare(
        'SELECT review_id, seekid_detail, proposed_uuid, numeric_profile_url,
                uuid_profile_url, status, assigned_reviewer, created_at
         FROM seek_uuid_match_review
                 WHERE status = :status
                     AND assigned_reviewer = :assigned_reviewer
                 ORDER BY review_id ASC
                 LIMIT :limit OFFSET :offset'
    );
    $statement->execute([
        'status' => 'assigned',
        'assigned_reviewer' => $reviewerId,
        'limit' => $limit,
        'offset' => $offset,
    ]);

    return $statement->fetchAll();
}

function count_completed_reviews(string $reviewerId): int
{
    $statement = db_connection()->prepare(
        'SELECT COUNT(*)
         FROM seek_uuid_match_review
         WHERE status IN (:approved_status, :rejected_status)
           AND reviewed_by = :reviewed_by'
    );
    $statement->execute([
        'approved_status' => 'approved',
        'rejected_status' => 'rejected',
        'reviewed_by' => $reviewerId,
    ]);

    return (int) $statement->fetchColumn();
}

function find_completed_reviews(string $reviewerId, int $limit = 25, int $offset = 0): array
{
    $statement = db_connection()->prepare(
        'SELECT review_id, seekid_detail, proposed_uuid, status,
                assigned_reviewer, reviewed_by, reviewed_at, review_reason,
                created_at
         FROM seek_uuid_match_review
                 WHERE status IN (:approved_status, :rejected_status)
                     AND reviewed_by = :reviewed_by
                 ORDER BY reviewed_at DESC, review_id DESC
                 LIMIT :limit OFFSET :offset'
    );
    $statement->execute([
        'approved_status' => 'approved',
        'rejected_status' => 'rejected',
        'reviewed_by' => $reviewerId,
        'limit' => $limit,
        'offset' => $offset,
    ]);

    return $statement->fetchAll();
}

function all_review_history_filter_parts(
    string $resultFilter = 'all',
    string $seekId = '',
    string $proposedUuid = '',
    string $reviewedBy = ''
): array
{
    $conditions = ['status IN (:approved_status, :rejected_status)'];
    $parameters = [
        'approved_status' => 'approved',
        'rejected_status' => 'rejected',
    ];

    if ($resultFilter === 'match') {
        $conditions = ['status = :approved_status'];
        $parameters = ['approved_status' => 'approved'];
    } elseif ($resultFilter === 'unmatch') {
        $conditions = ['status = :rejected_status'];
        $parameters = ['rejected_status' => 'rejected'];
    }

    if ($seekId !== '') {
        $conditions[] = 'LOWER(CAST(seekid_detail AS CHAR)) LIKE :history_seek_id';
        $parameters['history_seek_id'] = '%' . strtolower($seekId) . '%';
    }

    if ($proposedUuid !== '') {
        $conditions[] = 'LOWER(proposed_uuid) LIKE :history_proposed_uuid';
        $parameters['history_proposed_uuid'] = '%' . strtolower($proposedUuid) . '%';
    }

    if ($reviewedBy !== '') {
        $conditions[] = 'LOWER(reviewed_by) LIKE :history_reviewed_by';
        $parameters['history_reviewed_by'] = '%' . strtolower($reviewedBy) . '%';
    }

    return [implode(' AND ', $conditions), $parameters];
}

function count_all_review_history(
    string $resultFilter = 'all',
    string $seekId = '',
    string $proposedUuid = '',
    string $reviewedBy = ''
): int
{
    [$where, $parameters] = all_review_history_filter_parts($resultFilter, $seekId, $proposedUuid, $reviewedBy);

    $statement = db_connection()->prepare(
        'SELECT COUNT(*)
         FROM seek_uuid_match_review
         WHERE ' . $where
    );
    $statement->execute($parameters);

    return (int) $statement->fetchColumn();
}

function find_all_review_history(
    string $resultFilter = 'all',
    string $seekId = '',
    string $proposedUuid = '',
    string $reviewedBy = '',
    int $limit = 25,
    int $offset = 0
): array
{
    [$where, $parameters] = all_review_history_filter_parts($resultFilter, $seekId, $proposedUuid, $reviewedBy);

    $statement = db_connection()->prepare(
        'SELECT review_id, source_table, seekid_detail, proposed_uuid,
                status, reviewed_by, reviewed_at, review_reason, created_at
         FROM seek_uuid_match_review
         WHERE ' . $where . '
         ORDER BY reviewed_at DESC, review_id DESC
         LIMIT :limit OFFSET :offset'
    );
    $parameters['limit'] = $limit;
    $parameters['offset'] = $offset;
    $statement->execute($parameters);

    return $statement->fetchAll();
}

function find_review_by_id(int $reviewId): ?array
{
    $statement = db_connection()->prepare(
        'SELECT review_id, source_table, seekid_detail, proposed_uuid,
                numeric_profile_url, uuid_profile_url, numeric_profile_snapshot,
                uuid_profile_snapshot, comparison_evidence, exact_content_match,
                comparison_version, status, assigned_reviewer, reviewed_by,
                reviewed_at, review_reason, created_by, created_at, applied_at,
                row_version
         FROM seek_uuid_match_review
         WHERE review_id = :review_id
         LIMIT 1'
    );
    $statement->execute(['review_id' => $reviewId]);
    $review = $statement->fetch();

    return $review === false ? null : $review;
}

function take_review(int $reviewId, string $reviewerId): string
{
    $connection = db_connection();
    $connection->beginTransaction();

    try {
        $statement = $connection->prepare(
            'SELECT status, assigned_reviewer
             FROM seek_uuid_match_review
             WHERE review_id = :review_id
             FOR UPDATE'
        );
        $statement->execute(['review_id' => $reviewId]);
        $review = $statement->fetch();

        if ($review === false) {
            $connection->rollBack();
            return 'not_found';
        }

        $status = (string) $review['status'];

        if ($status === 'pending') {
            $update = $connection->prepare(
                'UPDATE seek_uuid_match_review
                 SET status = :assigned_status,
                     assigned_reviewer = :assigned_reviewer,
                     row_version = row_version + 1
                 WHERE review_id = :review_id'
            );
            $update->execute([
                'assigned_status' => 'assigned',
                'assigned_reviewer' => $reviewerId,
                'review_id' => $reviewId,
            ]);
            $connection->commit();

            return 'claimed';
        }

        $connection->rollBack();

        if ($status === 'assigned') {
            return (string) $review['assigned_reviewer'] === $reviewerId
                ? 'already_assigned_to_you'
                : 'already_assigned';
        }

        if (in_array($status, ['approved', 'rejected'], true)) {
            return 'completed';
        }

        return 'unavailable';
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        throw $exception;
    }
}

function decide_review(int $reviewId, string $reviewerId, string $decision, string $reviewReason): string
{
    if (!in_array($decision, ['MATCH', 'UNMATCH'], true)) {
        return 'invalid_decision';
    }

    $connection = db_connection();
    $connection->beginTransaction();

    try {
        $reviewStatement = $connection->prepare(
            'SELECT status, assigned_reviewer, seekid_detail, proposed_uuid, row_version
             FROM seek_uuid_match_review
             WHERE review_id = :review_id
             FOR UPDATE'
        );
        $reviewStatement->execute(['review_id' => $reviewId]);
        $review = $reviewStatement->fetch();

        if ($review === false) {
            $connection->rollBack();
            return 'not_found';
        }

        $status = (string) $review['status'];
        if ($status !== 'assigned') {
            $connection->rollBack();

            return in_array($status, ['approved', 'rejected'], true)
                ? 'completed'
                : 'unavailable';
        }

        if ((string) $review['assigned_reviewer'] !== $reviewerId) {
            $connection->rollBack();
            return 'not_owner';
        }

        if ($decision === 'MATCH') {
            $candidateStatement = $connection->prepare(
                'SELECT id_detail
                 FROM seek_scrap_detail
                 WHERE seekid_detail = :seekid_detail
                 FOR UPDATE'
            );
            $candidateStatement->execute(['seekid_detail' => $review['seekid_detail']]);
            $candidate = $candidateStatement->fetch();

            if ($candidate === false) {
                $connection->rollBack();
                return 'candidate_not_found';
            }

            $candidateUpdate = $connection->prepare(
                'UPDATE seek_scrap_detail
                 SET uuid = :proposed_uuid
                 WHERE seekid_detail = :seekid_detail'
            );
            $candidateUpdate->execute([
                'proposed_uuid' => $review['proposed_uuid'],
                'seekid_detail' => $review['seekid_detail'],
            ]);
        }

        $reviewUpdate = $connection->prepare(
            'UPDATE seek_uuid_match_review
             SET status = :status,
                 assigned_reviewer = :assigned_reviewer,
                 reviewed_by = :reviewed_by,
                 reviewed_at = CURRENT_TIMESTAMP,
                 review_reason = :review_reason,
                 applied_at = CURRENT_TIMESTAMP,
                 row_version = row_version + 1
             WHERE review_id = :review_id'
        );
        $reviewUpdate->execute([
            'status' => $decision === 'MATCH' ? 'approved' : 'rejected',
            'assigned_reviewer' => $reviewerId,
            'reviewed_by' => $reviewerId,
            'review_reason' => $reviewReason,
            'review_id' => $reviewId,
        ]);
        $connection->commit();

        return $decision === 'MATCH' ? 'matched' : 'unmatched';
    } catch (Throwable $exception) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        throw $exception;
    }
}