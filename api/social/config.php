<?php
/**
 * Configurazione globale del generatore di contenuti social.
 * Compila tutti i valori prima di usare l'applicazione.
 */

return [

    // ===================== PROVIDER AI =====================
    // Provider per la generazione dei testi: 'gemini' oppure 'anthropic'
    'ai_provider' => 'gemini',

    // ----- GOOGLE GEMINI -----
    // Chiave API da https://aistudio.google.com/apikey
    'gemini_api_key' => getenv('GEMINI_API_KEY') ?: 'AIzaSy_MASKED_GEMINI_KEY',
    'gemini_model'   => 'gemini-3.5-flash',
    // Modelli di riserva, provati in ordine se il precedente fallisce
    // (es. quota esaurita / 429 sul piano free)
    'gemini_fallback_models' => [
        'gemini-3.1-flash-lite',
        'gemini-2.5-flash',
        'gemini-2.5-flash-lite',
        'gemini-2.0-flash',
        'gemini-2.0-flash-lite',
    ],
    'gemini_api_url' => 'https://generativelanguage.googleapis.com/v1beta/models',

    // ----- ANTHROPIC (Claude) -----
    // Chiave API da https://console.anthropic.com/ (usata solo se ai_provider = 'anthropic')
    'anthropic_api_key' => getenv('ANTHROPIC_API_KEY') ?: 'sk-ant-MASKED_KEY',
    'anthropic_model'   => 'claude-sonnet-4-6',
    'anthropic_api_url' => 'https://api.anthropic.com/v1/messages',

    // ===================== GOOGLE =====================
    // Percorso del file JSON dell'account di servizio Google
    // (creato su Google Cloud Console -> IAM -> Service Account -> Keys)
    'google_service_account_json' => __DIR__ . '/credentials/service-account.json',

    // ----- OAuth utente (NECESSARIO per l'upload su Drive) -----
    // I service account non hanno quota di archiviazione su Drive, quindi i file
    // vengono caricati a nome del TUO account Google tramite OAuth.
    // 1. Google Cloud Console -> API e servizi -> Credenziali -> Crea credenziali
    //    -> ID client OAuth -> tipo "Applicazione web"
    //    -> URI di reindirizzamento autorizzato: http://localhost/social/oauth_setup.php
    // 2. Scarica il JSON e salvalo come credentials/oauth-client.json
    // 3. Apri http://localhost/social/oauth_setup.php e autorizza l'account
    //    proprietario della cartella Drive (il token viene salvato automaticamente)
    'google_oauth_client_json' => __DIR__ . '/credentials/oauth-client.json',
    'google_oauth_token_json'  => __DIR__ . '/credentials/oauth-token.json',
    // Auto-rilevato dall'host corrente (funziona sia su localhost sia in produzione);
    // l'URI deve comunque essere tra quelli autorizzati nel client OAuth su Google Cloud.
    'google_oauth_redirect_uri' => (function () {
        if (!empty($_SERVER['HTTP_HOST'])) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
            return "{$scheme}://{$_SERVER['HTTP_HOST']}{$dir}/oauth_setup.php";
        }
        return 'http://localhost/social/oauth_setup.php';
    })(),

    // ID del Google Sheet (preso dall'URL fornito)
    'google_sheet_id' => '1YSrwn0wcmxIQzucRoe2SmSWFWHIUAr0u624aMCY-wzE',

    // Nome del foglio (tab) dove scrivere, corrispondente a gid=0
    // Se non conosci il nome esatto, lascialo vuoto: lo script usera' il primo foglio
    'google_sheet_tab' => '',

    // ID della cartella di Google Drive dove salvare immagini e reel
    // (Crea una cartella, condividila con l'email del service account come "Editor"
    //  e copia l'ID dall'URL della cartella)
    'google_drive_folder_id' => '1Lic9tS7CYIgAXcF-23KQ0yYxLcXms9vS',

    // ===================== FFMPEG (per i Reel) =====================
    // Percorso del binario ffmpeg sul server (lascia "ffmpeg" se e' nel PATH)
    'ffmpeg_path' => __DIR__ . '/bin/ffmpeg-8.1.1-essentials_build/bin/ffmpeg.exe',

    // ===================== ALTRO =====================
    // Cartelle locali temporanee (poi caricate su Drive e cancellate)
    'output_images_dir' => __DIR__ . '/output/images',
    'output_reels_dir'  => __DIR__ . '/output/reels',

    // Font usato per le infografiche (TTF) - inserisci un font nella cartella /fonts
    'font_bold'    => __DIR__ . '/fonts/Montserrat-Bold.ttf',
    'font_regular' => __DIR__ . '/fonts/Montserrat-Regular.ttf',
];