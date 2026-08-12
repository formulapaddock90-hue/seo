<?php
/**
 * Configurazione globale del generatore di contenuti social FormulaPaddock per https://www.formulapaddock.it/seo/social/
 */

$seoConfig = file_exists('F:/seo/config.php') ? require 'F:/seo/config.php' : [];

return [

    // ===================== REEL CLOUD ENGINE URL =====================
    'reel_cloud_url' => 'https://reel-engine-dcnr.onrender.com',

    // ===================== PROVIDER AI =====================
    'ai_provider' => 'gemini',

    // ----- GOOGLE GEMINI -----
    'gemini_api_key' => $seoConfig['gemini_api_key'] ?? getenv('GEMINI_API_KEY') ?: '',
    'gemini_model'   => 'gemini-2.5-flash',
    'gemini_fallback_models' => [
        'gemini-2.5-flash-lite',
        'gemini-2.0-flash',
        'gemini-2.0-flash-lite',
    ],
    'gemini_api_url' => 'https://generativelanguage.googleapis.com/v1beta/models',

    // ----- ANTHROPIC (Claude) -----
    'anthropic_api_key' => $seoConfig['anthropic_api_key'] ?? getenv('ANTHROPIC_API_KEY') ?: '',
    'anthropic_model'   => 'claude-sonnet-4-6',
    'anthropic_api_url' => 'https://api.anthropic.com/v1/messages',

    // ===================== GOOGLE =====================
    'google_service_account_json' => __DIR__ . '/credentials/service-account.json',
    'google_oauth_client_json' => __DIR__ . '/credentials/oauth-client.json',
    'google_oauth_token_json'  => __DIR__ . '/credentials/oauth-token.json',
    'google_oauth_redirect_uri' => 'https://www.formulapaddock.it/seo/social/oauth_setup.php',

    // ID del Google Sheet
    'google_sheet_id' => '1YSrwn0wcmxIQzucRoe2SmSWFWHIUAr0u624aMCY-wzE',
    'google_sheet_tab' => '',

    // ID della cartella di Google Drive per salvataggio automatico Reel & Social Media (cartella creatività)
    'google_drive_folder_id' => '1zDqtrdpLBxC7q_2kB42tZ9f9_eyABz5K',
    'drive_reel_folder_id'   => '1zDqtrdpLBxC7q_2kB42tZ9f9_eyABz5K',

    // ===================== TIKTOK NATIVO (NO BUFFER) =====================
    'tiktok_client_key'         => 'awv11yg8p6dya9rv',
    'tiktok_client_secret'      => 'TFhSbpzihzCAtNCsJWZUVnzAGvwKlFwG',
    'tiktok_creator_token'      => '',
    'tiktok_oauth_token_json'   => __DIR__ . '/credentials/tiktok-token.json',
    'tiktok_redirect_uri'       => 'https://www.formulapaddock.it/seo/social/oauth_setup.php?action=tiktok',

    // ===================== FACEBOOK REELS NATIVO =====================
    'facebook_page_id'           => '',
    'facebook_page_access_token' => '',

    // ===================== BUFFER (SOLO FB POST & TWITTER) =====================
    'buffer_access_token'    => $seoConfig['buffer_access_token'] ?? 'GFqEZiylc4lAEJvkXWa3Q1pcyFqzJL20yATjNTJsD4w',
    'buffer_organization_id' => '689a66e96a24d3b69387c582',
    'buffer_share_mode'     => 'shareNow',

    // ===================== LINKEDIN NATIVO =====================
    'linkedin_client_id'        => '',
    'linkedin_client_secret'    => '',
    'linkedin_oauth_token_json' => __DIR__ . '/credentials/linkedin-token.json',
    'linkedin_author_urn'       => '',

    // ===================== THREADS NATIVO =====================
    'threads_client_id'         => '921011200339541',
    'threads_client_secret'     => '0d68d183941cd3e435af7b7cb29242c4',
    'threads_oauth_token_json'  => __DIR__ . '/credentials/threads-token.json',

    'ffmpeg_path' => (PHP_OS_FAMILY === 'Windows')
        ? __DIR__ . '/bin/ffmpeg-n7.1-latest-win64-gpl-7.1/bin/ffmpeg.exe'
        : 'ffmpeg',

    // ===================== REEL LOGIC & BRAND =====================
    'brand_name' => 'FORMULAPADDOCK.IT',
    'brand_color' => '#e10600',
    'brand_logo_url' => 'https://www.formulapaddock.it/wp-content/uploads/2026/05/preview.webp',
    'brand_cta' => 'SEGUI FORMULAPADDOCK.IT SU INSTAGRAM E TIKTOK',

    // Cartelle locali temporanee
    'output_images_dir' => __DIR__ . '/output/images',
    'output_reels_dir'  => __DIR__ . '/output/reels',

    // Font per le infografiche
    'font_bold'    => __DIR__ . '/fonts/Montserrat-Bold.ttf',
    'font_regular' => __DIR__ . '/fonts/Montserrat-Regular.ttf',
];