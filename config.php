<?php

// Public configuration only. Never commit passwords, API tokens or private keys here.
$privateFile = __DIR__ . '/config/private.php';
$private = is_file($privateFile) ? (require $privateFile) : [];
if (!is_array($private)) $private = [];

$secret = static function (string $key, string $env, string $default = '') use ($private): string {
    if (isset($private[$key]) && $private[$key] !== '') return (string) $private[$key];
    $value = getenv($env);
    return ($value !== false && $value !== '') ? (string) $value : $default;
};

$settingsFile = __DIR__ . '/storage/settings.json';
$savedSettings = is_file($settingsFile) ? (json_decode(file_get_contents($settingsFile), true) ?? []) : [];
$geminiApiKey = !empty($savedSettings['gemini_api_key']) ? (string) $savedSettings['gemini_api_key'] : $secret('gemini_api_key', 'GEMINI_API_KEY');
$geminiModelUrl = !empty($savedSettings['gemini_model_url']) ? (string) $savedSettings['gemini_model_url'] : 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent';
$authPasswordHash = $secret('auth_password_hash', 'SEO_AUTH_PASSWORD_HASH');

// Compatibilità temporanea con i server configurati prima del passaggio agli hash.
// La password in chiaro resta nel file privato e non viene mai restituita.
if ($authPasswordHash === '') {
    $legacyAuthPassword = $secret('auth_password', 'SEO_AUTH_PASSWORD');
    if ($legacyAuthPassword !== '') {
        $authPasswordHash = password_hash($legacyAuthPassword, PASSWORD_DEFAULT);
    }
    unset($legacyAuthPassword);
}

return [
    // Authentication must be explicitly configured; never fall back to a known username/password.
    'auth_user' => $secret('auth_user', 'SEO_AUTH_USER'),
    'auth_password_hash' => $authPasswordHash,
    'gemini_api_key' => $geminiApiKey,
    'gemini_model_url' => $geminiModelUrl,
    'gemini_models' => [
        'gemini-3.6-flash' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent',
        'gemini-2.0-flash' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent',
        'gemini-1.5-flash' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent',
        'gemini-1.5-pro' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent',
        'gemini-2.0-flash-lite' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-lite:generateContent',
    ],
    'google_service_account_email' => $secret('google_service_account_email', 'GOOGLE_SERVICE_ACCOUNT_EMAIL'),
    'google_service_account_private_key' => $secret('google_service_account_private_key', 'GOOGLE_SERVICE_ACCOUNT_PRIVATE_KEY'),
    'google_service_account_key_file' => 'config/google-service-account.json',
    'buffer_access_token' => $secret('buffer_access_token', 'BUFFER_ACCESS_TOKEN'),
    'tiktok_client_key' => $secret('tiktok_client_key', 'TIKTOK_CLIENT_KEY'),
    'tiktok_client_secret' => $secret('tiktok_client_secret', 'TIKTOK_CLIENT_SECRET'),
    'threads_client_id' => $secret('threads_client_id', 'THREADS_CLIENT_ID'),
    'threads_client_secret' => $secret('threads_client_secret', 'THREADS_CLIENT_SECRET'),
    'x_username' => $secret('x_username', 'X_USERNAME', 'paddock_formula'),
    'x_bearer_token' => $secret('x_bearer_token', 'X_BEARER_TOKEN'),
    'linkedin_client_id' => $secret('linkedin_client_id', 'LINKEDIN_CLIENT_ID'),
    'linkedin_client_secret' => $secret('linkedin_client_secret', 'LINKEDIN_CLIENT_SECRET'),
    'timezone' => 'Europe/Rome',
    'openf1_base_url' => 'https://api.openf1.org/v1',
    'drive_output_dir' => '/home/urkv6v6p/domains/seo.formulapaddock.it/public_html/storage/social',
    'drive_infografiche_folder_id' => '1L-fU8_IxVedWIUAuHSGmWUHuwnOB68YA',
    'media_dirs' => [
        '/home/urkv6v6p/domains/seo.formulapaddock.it/public_html/immagini',
        '/home/urkv6v6p/domains/seo.formulapaddock.it/public_html/uploads',
    ],
    'sitemaps' => [
        'https://www.formulapaddock.it/post-sitemap.xml',
        'https://www.formulapaddock.it/page-sitemap.xml',
        'https://www.formulapaddock.it/gran_premi-sitemap.xml',
        'https://www.formulapaddock.it/pirelli-sitemap.xml',
        'https://www.formulapaddock.it/evergreen-sitemap.xml',
        'https://www.formulapaddock.it/category-sitemap.xml',
    ],
    'db_username' => $secret('db_username', 'DB_USERNAME'),
    'db_name' => $secret('db_name', 'DB_NAME'),
    'db_hostname' => $secret('db_hostname', 'DB_HOSTNAME'),
    'db_password' => $secret('db_password', 'DB_PASSWORD'),
    'ftp_host' => $secret('ftp_host', 'FTP_HOST'),
    'ftp_user' => $secret('ftp_user', 'FTP_USER'),
    'ftp_passw' => $secret('ftp_passw', 'FTP_PASSWORD'),
    'sites' => [
        'formulapaddock' => ['label'=>'Formula Paddock','url'=>'https://www.formulapaddock.it','username'=>$secret('wp_formulapaddock_username','WP_FORMULAPADDOCK_USERNAME'),'application_password'=>$secret('wp_formulapaddock_application_password','WP_FORMULAPADDOCK_APPLICATION_PASSWORD'),'default_category'=>'','default_parent_page'=>''],
        'wec' => ['label'=>'Formula Paddock WEC','url'=>'https://www.formulapaddock.it/wec','username'=>$secret('wp_wec_username','WP_WEC_USERNAME'),'application_password'=>$secret('wp_wec_application_password','WP_WEC_APPLICATION_PASSWORD'),'default_category'=>'','default_parent_page'=>''],
        'formula2' => ['label'=>'Formula Paddock Formula 2','url'=>'https://www.formulapaddock.it/f2','username'=>$secret('wp_formula2_username','WP_FORMULA2_USERNAME'),'application_password'=>$secret('wp_formula2_application_password','WP_FORMULA2_APPLICATION_PASSWORD'),'default_category'=>'','default_parent_page'=>''],
    ],
];
