<?php

// Copia questo file come config/private.php SOLO sul server.
// config/private.php e' ignorato da Git e non deve essere pubblicato.
return [
    'auth_user' => 'admin',
    // Preferito: risultato di password_hash('la-tua-password', PASSWORD_DEFAULT).
    'auth_password_hash' => '',
    // Solo compatibilità con installazioni esistenti; migrare appena possibile.
    'auth_password' => '',

    'gemini_api_key' => '',
    'buffer_access_token' => '',
    'tiktok_client_key' => '',
    'tiktok_client_secret' => '',
    'threads_client_id' => '',
    'threads_client_secret' => '',
    'linkedin_client_id' => '',
    'linkedin_client_secret' => '',

    'db_username' => '',
    'db_name' => '',
    'db_hostname' => '',
    'db_password' => '',

    'ftp_host' => '',
    'ftp_user' => '',
    'ftp_passw' => '',

    'wp_formulapaddock_username' => 'admin',
    'wp_formulapaddock_application_password' => '',
    'wp_wec_username' => 'admin',
    'wp_wec_application_password' => '',
    'wp_formula2_username' => 'admin',
    'wp_formula2_application_password' => '',
];
