<?php

declare(strict_types=1);

function merge_queue_paths(): array
{
    $root = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'merge-jobs';

    return [
        'root' => $root,
        'pending' => $root . DIRECTORY_SEPARATOR . 'pending',
        'locks' => $root . DIRECTORY_SEPARATOR . 'locks',
        'logs' => $root . DIRECTORY_SEPARATOR . 'logs',
        'worker_lock' => $root . DIRECTORY_SEPARATOR . 'worker.lock',
    ];
}

function ensure_merge_queue_directories(): array
{
    $paths = merge_queue_paths();

    foreach (['root', 'pending', 'locks', 'logs'] as $pathKey) {
        if (!is_dir($paths[$pathKey]) && !mkdir($paths[$pathKey], 0775, true) && !is_dir($paths[$pathKey])) {
            throw new RuntimeException('Unable to create merge queue directory.');
        }
    }

    return $paths;
}

function merge_queue_log(string $event, string $uuid, ?string $error = null): void
{
    $paths = ensure_merge_queue_directories();
    $entry = [
        'timestamp' => date('c'),
        'event' => $event,
        'uuid' => $uuid,
    ];

    if ($error !== null) {
        $entry['error'] = $error;
    }

    error_log(json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, 3, $paths['logs'] . DIRECTORY_SEPARATOR . 'merge.log');
}

function queue_merge_job(string $uuid): void
{
    if ($uuid === '') {
        throw new InvalidArgumentException('Cannot queue a merge job without a UUID.');
    }

    $paths = ensure_merge_queue_directories();
    $jobId = bin2hex(random_bytes(16));
    $temporaryPath = $paths['pending'] . DIRECTORY_SEPARATOR . '.' . $jobId . '.tmp';
    $jobPath = $paths['pending'] . DIRECTORY_SEPARATOR . $jobId . '.json';
    $payload = json_encode(['uuid' => $uuid], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

    if (file_put_contents($temporaryPath, $payload, LOCK_EX) === false || !rename($temporaryPath, $jobPath)) {
        @unlink($temporaryPath);
        throw new RuntimeException('Unable to write merge queue job.');
    }

    merge_queue_log('queued', $uuid);
    start_merge_worker();
}

function start_merge_worker(): void
{
    $worker = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'process-merge-jobs.php';
    $phpBinary = getenv('CANDIDATE_PHP_CLI') ?: PHP_BINARY;

    if (!is_file($worker)) {
        throw new RuntimeException('Merge worker script was not found.');
    }

    $quoteWindowsArgument = static function (string $value): string {
        return '"' . str_replace(['%', '"'], ['%%', '\\"'], $value) . '"';
    };
    $command = 'start "" /B ' . $quoteWindowsArgument($phpBinary) . ' ' . $quoteWindowsArgument($worker);
    $process = proc_open($command, [], $pipes);

    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start the merge worker process.');
    }

    proc_close($process);
}