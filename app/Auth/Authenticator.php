<?php

declare(strict_types=1);

function authenticate_staff(string $username, string $password): bool
{
    $statement = db_connection()->prepare(
        'SELECT dbstffid, dbstffpswd, dbstfflevel, dbstffsurname, dbstffnames, dbdeactivate, dblock
         FROM ftstaff
         WHERE dbstffid = :username
         LIMIT 1'
    );
    $statement->execute(['username' => $username]);
    $staff = $statement->fetch();

    if ($staff === false || is_blocked_staff($staff)) {
        return false;
    }

    if (!verify_staff_password($password, (string) $staff['dbstffpswd'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['logged_in'] = true;
    $_SESSION['dbstffid'] = (string) $staff['dbstffid'];
    $_SESSION['dbstffnames'] = (string) $staff['dbstffnames'];
    $_SESSION['dbstffsurname'] = (string) $staff['dbstffsurname'];
    $_SESSION['dbstfflevel'] = (string) $staff['dbstfflevel'];

    return true;
}

function is_blocked_staff(array $staff): bool
{
    return legacy_truthy($staff['dbdeactivate'] ?? null) || legacy_truthy($staff['dblock'] ?? null);
}

function legacy_truthy(mixed $value): bool
{
    return in_array(strtolower(trim((string) $value)), ['1', 'y', 'yes', 'true'], true);
}

function verify_staff_password(string $password, string $storedPassword): bool
{
    $passwordInfo = password_get_info($storedPassword);

    if (($passwordInfo['algoName'] ?? 'unknown') !== 'unknown') {
        return password_verify($password, $storedPassword);
    }

    if (app_config()['auth']['legacy_password_mode'] !== 'plaintext') {
        return false;
    }

    return hash_equals($storedPassword, $password);
}