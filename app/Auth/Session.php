<?php

declare(strict_types=1);

function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name(app_config()['auth']['session_name']);
    session_set_cookie_params([
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax',
    ]);
    session_start();
}

function public_url(string $path): string
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $publicPosition = strpos($scriptName, '/public/');
    $basePath = $publicPosition === false ? '' : substr($scriptName, 0, $publicPosition + 7);

    return $basePath . '/' . ltrim($path, '/');
}

function require_authentication(): void
{
    if (empty($_SESSION['logged_in']) || empty($_SESSION['dbstffid'])) {
        header('Location: ' . public_url('login.php'));
        exit;
    }
}

function destroy_app_session(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}