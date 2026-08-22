<?php
require __DIR__ . '/auth.php';

// Se già autenticato, vai alla home
if (isAuthenticated()) {
    header('Location: ' . BASE_PATH . 'index.php');
    exit;
}

$result = handleLogin();
$error  = $result['error'] ?? null;
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? BASE_PATH . 'index.php';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accesso – F1 Content Hub</title>
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/style.css">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #0f0f0f; }
        .login-box { background: #1a1a1a; border: 1px solid #333; border-radius: 8px; padding: 2rem 2.5rem; width: 100%; max-width: 360px; }
        .login-box h1 { margin: 0 0 1.5rem; font-size: 1.4rem; color: #e10600; text-align: center; }
        .login-box label { display: block; margin-bottom: 1rem; color: #ccc; font-size: .9rem; }
        .login-box input { display: block; width: 100%; margin-top: .3rem; padding: .55rem .75rem; background: #111; border: 1px solid #444; border-radius: 4px; color: #fff; font-size: 1rem; box-sizing: border-box; }
        .login-box input:focus { outline: none; border-color: #e10600; }
        .login-box button { width: 100%; margin-top: 1.2rem; padding: .65rem; background: #e10600; border: none; border-radius: 4px; color: #fff; font-size: 1rem; cursor: pointer; }
        .login-box button:hover { background: #b30500; }
        .login-error { background: #3a0000; border: 1px solid #e10600; color: #ff6b6b; border-radius: 4px; padding: .6rem .9rem; margin-bottom: 1rem; font-size: .9rem; }
    </style>
</head>
<body>
<div class="login-box">
    <h1>🏎 F1 Content Hub</h1>
    <?php if ($error): ?>
        <div class="login-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES) ?>">
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
        <label>
            Utente
            <input type="text" name="username" autocomplete="username" required autofocus>
        </label>
        <label>
            Password
            <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button type="submit">Accedi</button>
    </form>
</div>
</body>
</html>
