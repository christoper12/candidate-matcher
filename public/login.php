<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Bootstrap.php';

if (!empty($_SESSION['logged_in'])) {
    header('Location: ' . public_url('index.php'));
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['dbstffid'] ?? ''));
    $password = (string) ($_POST['dbstffpswd'] ?? '');

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'The login form has expired. Please try again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Username and password are required.';
    } elseif (authenticate_staff($username, $password)) {
        header('Location: ' . public_url('index.php'));
        exit;
    } else {
        $error = 'Invalid credentials.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in | Candidate UUID Match</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(public_url('assets/auth.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-card" aria-labelledby="login-title">
            <div class="auth-brand">
                <span class="brand-mark" aria-hidden="true">CU</span>
                <div>
                    <p class="auth-eyebrow">Internal workspace</p>
                    <p class="auth-product">Candidate UUID Match</p>
                </div>
            </div>
            <div class="auth-heading">
                <p class="auth-eyebrow">Reviewer access</p>
                <h1 id="login-title">Sign in to continue</h1>
                <p>Review candidate UUID matches securely from one queue.</p>
            </div>
        <?php if ($error !== null): ?>
            <div class="auth-error" role="alert">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
            <form class="auth-form" method="post" action="<?= htmlspecialchars(public_url('login.php'), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <label for="dbstffid">Username</label>
                <input id="dbstffid" type="text" name="dbstffid" autocomplete="username" required autofocus>
                <label for="dbstffpswd">Password</label>
                <input id="dbstffpswd" type="password" name="dbstffpswd" autocomplete="current-password" required>
                <button class="auth-submit" type="submit">Sign in</button>
            </form>
            <p class="auth-footer">Authorized reviewers only</p>
        </section>
    </main>
</body>
</html>