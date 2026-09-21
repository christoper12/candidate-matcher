<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../app/Config.php';
require_once __DIR__ . '/../app/Database/Connection.php';
require_once __DIR__ . '/../app/Review/MergeQueue.php';

$paths = ensure_merge_queue_directories();
$workerLock = fopen($paths['worker_lock'], 'c');

if ($workerLock === false || !flock($workerLock, LOCK_EX | LOCK_NB)) {
    exit;
}

try {
    foreach (glob($paths['pending'] . DIRECTORY_SEPARATOR . '*.json') ?: [] as $jobPath) {
        $payload = json_decode((string) file_get_contents($jobPath), true);
        $uuid = is_array($payload) && isset($payload['uuid']) ? (string) $payload['uuid'] : '';

        if ($uuid === '') {
            @unlink($jobPath);
            merge_queue_log('failed', '', 'Invalid merge job payload.');
            continue;
        }

        $uuidLockPath = $paths['locks'] . DIRECTORY_SEPARATOR . hash('sha256', $uuid) . '.lock';
        $uuidLock = fopen($uuidLockPath, 'c');
        if ($uuidLock === false || !flock($uuidLock, LOCK_EX | LOCK_NB)) {
            if (is_resource($uuidLock)) {
                fclose($uuidLock);
            }
            continue;
        }

        try {
            if (!unlink($jobPath)) {
                continue;
            }

            merge_queue_log('started', $uuid);
            $connection = db_connection();
            $statement = $connection->prepare('CALL sp_merge_duplicate(:uuid)');
            $statement->execute(['uuid' => $uuid]);
            while ($statement->nextRowset()) {
            }
            $statement->closeCursor();
            merge_queue_log('completed', $uuid);
        } catch (Throwable $exception) {
            merge_queue_log('failed', $uuid, $exception->getMessage());
        } finally {
            if (isset($uuidLock) && is_resource($uuidLock)) {
                flock($uuidLock, LOCK_UN);
                fclose($uuidLock);
            }
        }
    }
} finally {
    flock($workerLock, LOCK_UN);
    fclose($workerLock);
}