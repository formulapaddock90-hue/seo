<?php
/**
 * auth.php
 * Helper condiviso per la gestione dell'autenticazione.
 * Include session, checkAuth, handleLogin, handleLogout.
 */

if (session_status() === PHP_SESSION_NONE) {
    // Estende la durata della sessione a 12 ore (43200 secondi)
    ini_set('session.gc_maxlifetime', 43200);
    ini_set('session.cookie_lifetime', 43200);
    
    // Configura parametri del cookie in modo sicuro e compatibile
    $isSecure = isset($_SERVER['HTTPS']) && (
        strtolower($_SERVER['HTTPS']) === 'on' || 
        $_SERVER['HTTPS'] === '1' || 
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
    );
    
    session_set_cookie_params([
        'lifetime' => 43200,
        'path' => '/',
        'domain' => '',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    session_start();
}

$_authConfig = require __DIR__ . '/config.php';
define('AUTH_USER', (string) ($_authConfig['auth_user'] ?? ''));
define('AUTH_PASSWORD_HASH', (string) ($_authConfig['auth_password_hash'] ?? ''));
unset($_authConfig);

if (!defined('BASE_PATH')) {
    $_bp_docroot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])), '/');
    $_bp_dir     = rtrim(str_replace('\\', '/', realpath(__DIR__)), '/');
    define('BASE_PATH', substr($_bp_dir, strlen($_bp_docroot)) . '/');
    unset($_bp_docroot, $_bp_dir);
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function safeRedirect(string $redirect): string
{
    $default = BASE_PATH . 'index.php';
    if ($redirect === '' || $redirect[0] !== '/' || str_starts_with($redirect, '//') || preg_match('/[\r\n]/', $redirect)) {
        return $default;
    }
    return $redirect;
}

/**
 * Verifica che l'utente sia autenticato.
 * Per richieste AJAX/API restituisce JSON 401, altrimenti reindirizza al login.
 */
function checkAuth(): void
{
    if (!isAuthenticated()) {
        $isAjax = (
            (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || $_SERVER['REQUEST_METHOD'] === 'POST'
            || isset($_GET['action'])
        );

        if ($isAjax) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Sessione non autorizzata.']);
            exit;
        }

        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header('Location: ' . BASE_PATH . 'login.php' . ($redirect ? '?redirect=' . $redirect : ''));
        exit;
    }
}

/**
 * Gestisce il form di login (POST).
 * Restituisce un array con 'error' in caso di credenziali errate, null altrimenti.
 */
function handleLogin(): ?array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return null;
    }

    if (!verifyCsrf((string) ($_POST['csrf_token'] ?? ''))) {
        return ['error' => 'Richiesta non valida.'];
    }

    $user = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (AUTH_USER !== '' && AUTH_PASSWORD_HASH !== '' && hash_equals(AUTH_USER, $user) && password_verify($password, AUTH_PASSWORD_HASH)) {
        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
        $_SESSION['login_time']    = time();

        header('Location: ' . safeRedirect((string) ($_POST['redirect'] ?? '')));
        exit;
    }

    return ['error' => 'Credenziali errate. Riprova.'];
}

/**
 * Distrugge la sessione e reindirizza al login.
 */
function handleLogout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
    header('Location: ' . BASE_PATH . 'login.php');
    exit;
}

/**
 * Restituisce true se l'utente è autenticato.
 */
function isAuthenticated(): bool
{
    return isset($_SESSION['authenticated'], $_SESSION['login_time'])
        && $_SESSION['authenticated'] === true
        && (time() - (int) $_SESSION['login_time']) < 43200;
}
