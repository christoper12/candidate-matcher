# candidate-matcher
candidate matcher for seek from seek id to seek uuid

## Background merge worker

After a successful MATCH, the application writes a UUID-only job to `storage/merge-jobs/` and starts the PHP CLI worker with Windows `start /B`. The worker uses a separate PDO connection, calls `sp_merge_duplicate(:uuid)`, and logs `queued`, `started`, `completed`, and `failed` events to `storage/merge-jobs/logs/merge.log`.

The web-server account must be able to create and write to `storage/`. PHP CLI must be available to Apache; set `CANDIDATE_PHP_CLI` to the full path of `php.exe` when `PHP_BINARY` is not the CLI executable. The worker can also be drained manually with:

```text
php bin/process-merge-jobs.php
```
