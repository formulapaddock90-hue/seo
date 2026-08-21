<?php
/** Shared authentication helper. */
if (session_status() === PHP_SESSION_NONE) {
    $secure = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    ini_set('session.gc_maxlifetime', '3600');
    session_set_cookie_params(['lifetime'=>3600,'path'=>'/','domain'=>'','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
    session_start();
}

$_authConfig = require __DIR__ . '/config.php';
define('AUTH_USER', (string) ($_authConfig['auth_user'] ?? ''));
define('AUTH_PASSWORD_HASH', (string) ($_authConfig['auth_password_hash'] ?? ''));
unset($_authConfig);

if (!defined('BASE_PATH')) {
    $docroot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: ''), '/');
    $dir = rtrim(str_replace('\\', '/', realpath(__DIR__) ?: __DIR__), '/');
    define('BASE_PATH', ($docroot !== '' && str_starts_with($dir, $docroot)) ? substr($dir, strlen($docroot)) . '/' : '/');
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function verifyCsrf(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
function safeRedirect(string $redirect): string {
    $default = BASE_PATH . 'index.php';
    if ($redirect === '' || $redirect[0] !== '/' || str_starts_with($redirect, '//') || preg_match('/[\r\n]/', $redirect)) return $default;
    return $redirect;
}
function checkAuth(): void {
    if (!isAuthenticated()) {
        $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') || ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' || isset($_GET['action']);
        if ($isAjax) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success'=>false,'message'=>'Sessione non autorizzata.']);
            exit;
        }
        header('Location: ' . BASE_PATH . 'login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? BASE_PATH . 'index.php'));
        exit;
    }
}
function handleLogin(): ?array {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return null;
    if (!verifyCsrf((string) ($_POST['csrf_token'] ?? ''))) return ['error'=>'Richiesta non valida.'];
    $user = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $valid = AUTH_USER !== '' && AUTH_PASSWORD_HASH !== '' && hash_equals(AUTH_USER, $user) && password_verify($password, AUTH_PASSWORD_HASH);
    if ($valid) {
        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
        $_SESSION['login_time'] = time();
        header('Location: ' . safeRedirect((string) ($_POST['redirect'] ?? '')));
        exit;
    }
    return ['error'=>'Credenziali errate. Riprova.'];
}
function handleLogout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], (bool)$p['secure'], (bool)$p['httponly']);
    }
    session_destroy();
    header('Location: ' . BASE_PATH . 'login.php');
    exit;
}
function isAuthenticated(): bool {
    return isset($_SESSION['authenticated'], $_SESSION['login_time']) && $_SESSION['authenticated'] === true && (time() - (int) $_SESSION['login_time']) < 3600;
}
