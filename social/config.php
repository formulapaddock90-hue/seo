<?php
/**
 * Configurazione globale del generatore di contenuti social FormulaPaddock.
 * I segreti devono arrivare da config.php / config/private.php o da variabili d'ambiente.
 */

$seoConfig = [];
$seoConfigFile = __DIR__ . '/../config.php';
if (file_exists($seoConfigFile)) {
    ob_start();
    try {
        $loadedSeoConfig = require $seoConfigFile;
    } finally {
        ob_end_clean();
    }
    if (is_array($loadedSeoConfig)) {
        $seoConfig = $loadedSeoConfig;
    }
}

return [
    // ===================== REEL =====================
    // Il server hosting non consente exec/proc_open/shell_exec: il Reel viene
    // renderizzato nel browser e poi caricato a chunk in output/reels.
    'reel_render_mode' => 'browser',
    'reel_music_dir' => __DIR__ . '/music',

    // ===================== PROVIDER AI =====================
    'ai_provider' => 'gemini',

    // ----- GOOGLE GEMINI -----
    'gemini_api_key' => $seoConfig['gemini_api_key'] ?? (getenv('GEMINI_API_KEY') ?: ''),
    'gemini_model'   => 'gemini-3.6-flash',
    'fallback_models' => [
        'gemini-3.6-flash',
        'gemini-3.5-flash',
        'gemini-2.0-flash',
        'gemini-1.5-flash',
        'gemini-2.0-flash-lite',
        'gemini-1.5-pro'
    ],
    'gemini_api_url' => 'https://generativelanguage.googleapis.com/v1beta/models',

    // ----- ANTHROPIC (Claude) -----
    'anthropic_api_key' => $seoConfig['anthropic_api_key'] ?? (getenv('ANTHROPIC_API_KEY') ?: ''),
    'anthropic_model'   => 'claude-sonnet-4-6',
    'anthropic_api_url' => 'https://api.anthropic.com/v1/messages',

    // ===================== GOOGLE =====================
    'google_service_account_json' => __DIR__ . '/credentials/service-account.json',
    'google_oauth_client_json' => __DIR__ . '/credentials/oauth-client.json',
    'google_oauth_token_json'  => __DIR__ . '/credentials/oauth-token.json',
    'google_oauth_redirect_uri' => 'https://www.formulapaddock.it/seo/social/oauth_setup.php',
    'google_sheet_id' => '1YSrwn0wcmxIQzucRoe2SmSWFWHIUAr0u624aMCY-wzE',
    'google_sheet_tab' => '',
    'google_drive_folder_id' => '1zDqtrdpLBxC7q_2kB42tZ9f9_eyABz5K',
    'drive_reel_folder_id'   => '1zDqtrdpLBxC7q_2kB42tZ9f9_eyABz5K',

    // ===================== TIKTOK NATIVO =====================
    'tiktok_client_key'       => $seoConfig['tiktok_client_key'] ?? (getenv('TIKTOK_CLIENT_KEY') ?: ''),
    'tiktok_client_secret'    => $seoConfig['tiktok_client_secret'] ?? (getenv('TIKTOK_CLIENT_SECRET') ?: ''),
    'tiktok_creator_token'    => getenv('TIKTOK_CREATOR_TOKEN') ?: '',
    'tiktok_oauth_token_json' => __DIR__ . '/credentials/tiktok-token.json',
    'tiktok_redirect_uri'     => 'https://www.formulapaddock.it/seo/social/oauth_setup.php?action=tiktok',

    // ===================== FACEBOOK REELS NATIVO =====================
    'facebook_page_id'           => getenv('FACEBOOK_PAGE_ID') ?: '',
    'facebook_page_access_token' => getenv('FACEBOOK_PAGE_ACCESS_TOKEN') ?: '',

    // ===================== BUFFER =====================
    'buffer_access_token'    => $seoConfig['buffer_access_token'] ?? (getenv('BUFFER_ACCESS_TOKEN') ?: ''),
    'buffer_organization_id' => getenv('BUFFER_ORGANIZATION_ID') ?: '',
    'buffer_share_mode'      => 'shareNow',

    // ===================== LINKEDIN NATIVO =====================
    'linkedin_client_id'        => $seoConfig['linkedin_client_id'] ?? (getenv('LINKEDIN_CLIENT_ID') ?: ''),
    'linkedin_client_secret'    => $seoConfig['linkedin_client_secret'] ?? (getenv('LINKEDIN_CLIENT_SECRET') ?: ''),
    'linkedin_oauth_token_json' => __DIR__ . '/credentials/linkedin-token.json',
    'linkedin_author_urn'       => getenv('LINKEDIN_AUTHOR_URN') ?: '',

    // ===================== THREADS NATIVO =====================
    'threads_client_id'        => $seoConfig['threads_client_id'] ?? (getenv('THREADS_CLIENT_ID') ?: ''),
    'threads_client_secret'    => $seoConfig['threads_client_secret'] ?? (getenv('THREADS_CLIENT_SECRET') ?: ''),
    'threads_oauth_token_json' => __DIR__ . '/credentials/threads-token.json',

    // Conservato solo per eventuali utility locali/test fuori dall'hosting.
    'ffmpeg_path' => (PHP_OS_FAMILY === 'Windows')
        ? __DIR__ . '/bin/ffmpeg-n7.1-latest-win64-gpl-7.1/bin/ffmpeg.exe'
        : 'ffmpeg',

    // ===================== BRAND & OUTPUT =====================
    'brand_name' => 'FORMULAPADDOCK.IT',
    'brand_color' => '#e10600',
    'brand_logo_url' => 'https://www.formulapaddock.it/wp-content/uploads/2026/05/preview.webp',
    'brand_cta' => 'SEGUI FORMULAPADDOCK.IT SU INSTAGRAM E TIKTOK',
    'output_images_dir' => __DIR__ . '/output/images',
    'output_reels_dir'  => __DIR__ . '/output/reels',
    'font_bold'    => __DIR__ . '/fonts/Montserrat-Bold.ttf',
    'font_regular' => __DIR__ . '/fonts/Montserrat-Regular.ttf',
];
