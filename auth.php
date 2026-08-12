<?php
/**
 * auth.php
 * Helper condiviso per la gestione dell'autenticazione.
 * Include session, checkAuth, handleLogin, handleLogout.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_authConfig = require __DIR__ . '/config.php';
define('AUTH_USER',     $_authConfig['auth_user']     ?? 'admin');
define('AUTH_PASSWORD', $_authConfig['auth_password'] ?? 'admin');
unset($_authConfig);

if (!defined('BASE_PATH')) {
    $_bp_docroot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])), '/');
    $_bp_dir     = rtrim(str_replace('\\', '/', realpath(__DIR__)), '/');
    define('BASE_PATH', substr($_bp_dir, strlen($_bp_docroot)) . '/');
    unset($_bp_docroot, $_bp_dir);
}

/**
 * Verifica che l'utente sia autenticato.
 * Per richieste AJAX/API restituisce JSON 401, altrimenti reindirizza al login.
 */
function checkAuth(): void
{
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
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

    $user     = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($user === AUTH_USER && $password === AUTH_PASSWORD) {
        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
        $_SESSION['login_time']    = time();

        $redirect = $_POST['redirect'] ?? BASE_PATH . 'index.php';
        header('Location: ' . $redirect);
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
    session_destroy();
    header('Location: ' . BASE_PATH . 'login.php');
    exit;
}

/**
 * Restituisce true se l'utente è autenticato.
 */
function isAuthenticated(): bool
{
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}
