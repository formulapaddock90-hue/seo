<?php
/**
 * commento.php
 * Interfaccia Live Commentary con Toolbar Eventi Avanzata.
 */

define('FP_DISABLE_DISPATCH', true);

$rootConfigPath = __DIR__ . '/config.php';
$rootFunctionPath = __DIR__ . '/function.php';
$socialFunctionPath = __DIR__ . '/social/function.php';
$commonFunctionsPath = __DIR__ . '/nuovo/common_functions.php';
$authPath = __DIR__ . '/auth.php';

if (file_exists($rootConfigPath)) {
    require_once $rootConfigPath;
}

if (file_exists($rootFunctionPath)) {
    require_once $rootFunctionPath;
} elseif (file_exists($socialFunctionPath)) {
    require_once $socialFunctionPath;
}

if (file_exists($commonFunctionsPath)) {
    require_once $commonFunctionsPath;
}

if (!function_exists('checkAuth') && file_exists($authPath)) {
    require_once $authPath;
}

// Verifica autenticazione
checkAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'save-commentary') {
    header('Content-Type: application/json; charset=utf-8');

    $rawBody = file_get_contents('php://input');
    $debugLogPath = __DIR__ . DIRECTORY_SEPARATOR . 'commentary_debug.log';

    $payload = null;

    if ($rawBody !== '') {
        $payload = json_decode($rawBody, true);
        if ($payload === null && json_last_error() !== JSON_ERROR_NONE) {
            $parsedBody = [];
            parse_str($rawBody, $parsedBody);
            if (!empty($parsedBody)) {
                $payload = $parsedBody;
            }
        }
    }

    if ($payload === null && !empty($_POST)) {
        $payload = $_POST;
    }

    $commentary = [];
    if (is_array($payload)) {
        if (array_key_exists('commentary', $payload)) {
            $commentary = $payload['commentary'];
            if (is_string($commentary)) {
                $decodedCommentary = json_decode($commentary, true);
                if (is_array($decodedCommentary)) {
                    $commentary = $decodedCommentary;
                }
            }
        } else {
            $commentary = $payload;
        }
    }

    if (!is_array($commentary)) {
        $commentary = [];
    }

    $debugContext = [
        'time' => date('c'),
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
        'content_length' => $_SERVER['CONTENT_LENGTH'] ?? '',
        'query' => $_GET,
        'post_keys' => array_keys($_POST),
        'raw_body_length' => strlen($rawBody),
        'raw_body_preview' => substr($rawBody, 0, 500),
        'json_error' => json_last_error_msg(),
        'commentary_count' => is_array($commentary) ? count($commentary) : 0
    ];
    @file_put_contents($debugLogPath, json_encode($debugContext) . PHP_EOL, FILE_APPEND);

    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Impossibile creare la cartella uploads.']);
        exit;
    }

    $filePath = $uploadDir . DIRECTORY_SEPARATOR . 'commentary.json';
    $encoded = json_encode($commentary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($encoded === false || file_put_contents($filePath, $encoded) === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Errore nel salvataggio del file.']);
        exit;
    }

    echo json_encode(['success' => true, 'file' => 'uploads/commentary.json']);
    exit;
}

if (($_GET['action'] ?? '') === 'save-commentary' && ($_GET['debug'] ?? '') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'message' => 'Debug endpoint attivo',
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
        'content_length' => $_SERVER['CONTENT_LENGTH'] ?? '',
        'query' => $_GET,
        'post_keys' => array_keys($_POST)
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <title>Live Commentary - Formula Paddock</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap');

        body { 
            font-family: 'Inter', sans-serif; 
        }

        /* Custom scrollbar per feed commenti */
        #commentary-feed::-webkit-scrollbar { 
            width: 6px; 
        }
        #commentary-feed::-webkit-scrollbar-track { 
            background: rgba(15, 23, 42, 0.5); 
            border-radius: 3px;
        }
        #commentary-feed::-webkit-scrollbar-thumb { 
            background: #e11d48; 
            border-radius: 3px; 
        }
        #commentary-feed::-webkit-scrollbar-thumb:hover { 
            background: #be185d; 
        }

        /* Smooth scrolling per mobile */
        #commentary-feed {
            -webkit-overflow-scrolling: touch;
        }

        /* Stili per pulsanti eventi - versione compatta */
        .event-btn { 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            justify-content: center; 
            gap: 4px; 
            border: 2px solid rgba(255,255,255,0.05);
            position: relative;
            overflow: hidden;
            padding: 12px 8px; /* Ridotto da 16px 12px */
        }

        .event-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.6s;
        }

        .event-btn:hover::before {
            left: 100%;
        }

        .event-btn:hover { 
            transform: translateY(-3px); 
            background: rgba(255,255,255,0.08); 
            border-color: rgba(255,255,255,0.2);
            box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.3);
        }

        .event-btn:active { 
            transform: translateY(-1px); 
        }

        .event-btn i { 
            font-size: 1rem; 
            margin-bottom: 2px;
        }

        .event-btn span { 
            font-size: 8px; 
            font-weight: 900; 
            text-transform: uppercase; 
            letter-spacing: 0.1em; 
            text-align: center;
            line-height: 1;
        }

        /* Animazioni per modal */
        .modal-enter {
            animation: modalEnter 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .modal-exit {
            animation: modalExit 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes modalEnter {
            from { 
                opacity: 0; 
                transform: scale(0.95) translateY(10px); 
            }
            to { 
                opacity: 1; 
                transform: scale(1) translateY(0); 
            }
        }

        @keyframes modalExit {
            from { 
                opacity: 1; 
                transform: scale(1) translateY(0); 
            }
            to { 
                opacity: 0; 
                transform: scale(0.95) translateY(10px); 
            }
        }

        /* Stili timer */
        .timer-display {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border: 2px solid rgba(255,255,255,0.1);
            transition: all 0.2s;
        }

        .timer-display:hover {
            border-color: #e11d48;
            box-shadow: 0 0 20px rgba(225, 29, 72, 0.3);
        }

        /* Indicatore status live */
        .live-indicator {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { 
                background-color: #dc2626; 
                box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.7); 
            }
            50% { 
                background-color: #ef4444; 
                box-shadow: 0 0 0 8px rgba(220, 38, 38, 0); 
            }
        }

        /* Custom scrollbar per sezione eventi */
        .events-section-mobile::-webkit-scrollbar {
            height: 4px;
        }

        .events-section-mobile::-webkit-scrollbar-track {
            background: rgba(15, 23, 42, 0.3);
            border-radius: 2px;
        }

        .events-section-mobile::-webkit-scrollbar-thumb {
            background: #e11d48;
            border-radius: 2px;
        }

        .events-section-mobile::-webkit-scrollbar-thumb:hover {
            background: #be185d;
        }

        /* Container flex per eventi con scroll orizzontale */
        .events-container-scroll {
            display: flex;
            flex-wrap: nowrap;
            gap: 0.5rem;
            padding: 0.5rem;
            width: 100%;
        }

        .events-container-scroll > button {
            flex-shrink: 0;
            min-width: fit-content;
        }

        /* Responsive adjustments per mobile - pulsanti piccoli */
        @media (max-width: 768px) {
            .event-btn {
                padding: 12px 8px;
                gap: 3px;
            }

            .event-btn i {
                font-size: 1rem;
            }

            .event-btn span {
                font-size: 7px;
                font-weight: 900;
            }

            /* Header mobile minimale */
            .commentary-header {
                padding: 12px 16px;
            }

            .commentary-header h2 {
                font-size: 1rem;
            }

            .commentary-header i {
                font-size: 1rem;
            }

            /* Modal mobile - altura adjustada */
            .modal-mobile {
                height: 100vh;
                height: 100dvh; /* Dynamic viewport height */
                border-radius: 0;
                margin: 0;
                padding: 0;
            }

            /* Body mobile - scroll fix con header minimale */
            .body-mobile {
                height: calc(100vh - 80px); /* Header minimale height */
                height: calc(100dvh - 80px);
                overflow: hidden;
                display: flex;
                flex-direction: column;
            }

            .events-section-mobile {
                flex-shrink: 0;
                max-height: 140px;
                overflow-x: auto;
                overflow-y: hidden;
                -webkit-overflow-scrolling: touch;
                padding: 0;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            }

            .events-section-mobile h3 {
                display: none;
            }

            .events-container-scroll {
                padding: 12px 8px;
                gap: 0.5rem;
            }

            .feed-section-mobile {
                flex: 1;
                min-height: 0;
                display: flex;
                flex-direction: column;
                overflow: hidden;
            }

            .feed-content-mobile {
                flex: 1;
                overflow-y: auto;
                overflow-x: hidden;
                -webkit-overflow-scrolling: touch;
                padding: 12px;
            }

            .timer-controls {
                gap: 12px;
            }

            .timer-display {
                padding: 8px;
            }

            .timer-display .timer-btn {
                width: 32px;
                height: 32px;
            }

            .timer-display #comm-timer-display {
                font-size: 1rem;
                padding: 0 8px;
            }

            .close-btn {
                width: 32px;
                height: 32px;
            }

            /* Footer mobile minimale */
            .footer-controls {
                padding: 12px 16px;
            }

            .footer-controls button {
                padding: 8px 12px;
                font-size: 11px;
            }

            .import-btn {
                font-size: 11px;
                padding: 8px 12px;
            }
        }

        @media (max-width: 480px) {
            .event-btn {
                padding: 10px 6px;
            }

            .event-btn i {
                font-size: 0.9rem;
            }

            .event-btn span {
                font-size: 6px;
            }

            .events-section-mobile {
                max-height: 130px;
            }

            .events-container-scroll {
                padding: 10px 6px;
                gap: 0.375rem;
            }

            .commentary-header {
                padding: 8px 12px;
            }

            .commentary-header h2 {
                font-size: 0.9rem;
            }

            .timer-display {
                padding: 4px;
            }

            .timer-display #comm-timer-display {
                font-size: 0.9rem;
                padding: 0 4px;
            }

            .timer-display .timer-btn {
                width: 28px;
                height: 28px;
            }

            .close-btn {
                width: 28px;
                height: 28px;
            }

            .footer-controls {
                padding: 8px 12px;
            }

            .footer-controls button {
                padding: 6px 10px;
                font-size: 10px;
            }

            .import-btn {
                font-size: 10px;
                padding: 6px 10px;
            }
        }

        /* Focus states per accessibilità */
        .event-btn:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
        }

        /* Loading states */
        .loading {
            position: relative;
            overflow: hidden;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% { left: -100%; }
            100% { left: 100%; }
        }
    </style>
</head>
<body class="bg-slate-900 min-h-screen">

    <!-- MODAL PRINCIPALE (sempre visibile) -->
    <div id="commentary-modal" class="fixed inset-0 bg-black/95 backdrop-blur-xl z-[100] flex items-center justify-center p-4">
        <div class="w-full max-w-7xl bg-slate-900 border border-white/20 rounded-[2rem] shadow-2xl overflow-hidden flex flex-col h-[90vh] modal-enter modal-mobile">

            <!-- Header Minimale -->
            <header class="bg-rose-600 p-4 flex justify-between items-center shrink-0 commentary-header">
                <div class="flex items-center gap-3">
                    <i class="fa-solid fa-microphone-lines text-white text-lg"></i>
                    <h2 class="font-bold text-white text-lg">Live Commentary</h2>
                </div>

                <div class="flex items-center gap-4 timer-controls">
                    <!-- Timer Compatto -->
                    <div class="flex items-center gap-2 timer-display p-2 rounded-lg">
                        <button onclick="window.toggleCommTimer()" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-white transition-all flex items-center justify-center timer-btn">
                            <i id="timer-play-icon" class="fa-solid fa-play text-sm"></i>
                        </button>
                        <button onclick="window.resetCommTimer()" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-white transition-all flex items-center justify-center timer-btn">
                            <i class="fa-solid fa-rotate-right text-sm"></i>
                        </button>
                        <div id="comm-timer-display" onclick="window.openEditTimer()" class="text-lg font-mono font-bold text-white cursor-pointer tabular-nums hover:text-rose-200 transition-colors px-2">
                            00:00:00
                        </div>
                    </div>

                    <!-- Pulsante Chiudi -->
                    <a href="index.php" class="w-8 h-8 bg-black/30 text-white rounded-lg flex items-center justify-center hover:bg-black/50 transition-all close-btn">
                        <i class="fa-solid fa-xmark text-sm"></i>
                    </a>
                </div>
            </header>

            <!-- Body -->
            <div class="flex-1 flex flex-col overflow-hidden bg-slate-950 body-mobile">

                <!-- Sezione Pulsanti Eventi (sopra) -->
                <div class="bg-slate-900/70 border-b border-white/10 p-6 events-section-mobile">
                    <h3 class="text-[11px] font-black text-slate-400 uppercase mb-6 tracking-[0.15em] hidden md:block">Eventi Gara</h3>
                    <div class="events-container-scroll">
                        <button onclick="window.openEventModal('bandiera-green')" class="event-btn p-3 rounded-lg text-emerald-400 bg-emerald-500/15 hover:bg-emerald-500/25">
                            <i class="fa-solid fa-flag"></i>
                            <span>Verde</span>
                        </button>
                        <button onclick="window.openEventModal('bandiera-rossa')" class="event-btn p-3 rounded-lg text-red-400 bg-red-500/15 hover:bg-red-500/25">
                            <i class="fa-solid fa-flag"></i>
                            <span>Rossa</span>
                        </button>
                        <button onclick="window.openEventModal('bandiera-gialla')" class="event-btn p-3 rounded-lg text-yellow-400 bg-yellow-500/15 hover:bg-yellow-500/25">
                            <i class="fa-solid fa-flag"></i>
                            <span>Gialla</span>
                        </button>
                        <button onclick="window.openEventModal('safety-car')" class="event-btn p-3 rounded-lg text-orange-400 bg-orange-500/15 hover:bg-orange-500/25">
                            <i class="fa-solid fa-car"></i>
                            <span>Safety Car</span>
                        </button>
                        <button onclick="window.openEventModal('vsc')" class="event-btn p-3 rounded-lg text-orange-300 bg-orange-500/10 hover:bg-orange-500/20">
                            <i class="fa-solid fa-stopwatch"></i>
                            <span>VSC</span>
                        </button>
                        <button onclick="window.openEventModal('giro-veloce-assoluto')" class="event-btn p-3 rounded-lg text-purple-400 bg-purple-500/15 hover:bg-purple-500/25">
                            <i class="fa-solid fa-gauge-high"></i>
                            <span>Veloce</span>
                        </button>
                        <button onclick="window.openEventModal('giro-veloce-personale')" class="event-btn p-3 rounded-lg text-emerald-300 bg-emerald-500/10 hover:bg-emerald-500/20">
                            <i class="fa-solid fa-gauge"></i>
                            <span>Record</span>
                        </button>
                        <button onclick="window.openEventModal('giro-normale')" class="event-btn p-3 rounded-lg text-slate-400 bg-slate-500/10 hover:bg-slate-500/20">
                            <i class="fa-solid fa-circle-notch"></i>
                            <span>Giro</span>
                        </button>
                        <button onclick="window.openEventModal('pit-stop')" class="event-btn p-3 rounded-lg text-blue-400 bg-blue-500/15 hover:bg-blue-500/25">
                            <i class="fa-solid fa-wrench"></i>
                            <span>Pit Stop</span>
                        </button>
                        <button onclick="window.openEventModal('sorpasso')" class="event-btn p-3 rounded-lg text-indigo-400 bg-indigo-500/15 hover:bg-indigo-500/25">
                            <i class="fa-solid fa-right-left"></i>
                            <span>Sorpasso</span>
                        </button>
                        <button onclick="window.openEventModal('note')" class="event-btn p-3 rounded-lg text-slate-300 bg-slate-500/15 hover:bg-slate-500/25">
                            <i class="fa-solid fa-note-sticky"></i>
                            <span>Nota</span>
                        </button>
                    </div>
                </div>

                <!-- Sezione Feed Commenti (sotto) - Minimale -->
                <div class="flex-1 flex flex-col overflow-hidden feed-section-mobile">
                    <div id="commentary-feed" class="flex-1 overflow-y-auto p-4 space-y-4 feed-content-mobile">
                        <!-- Feed dei commenti generato via JS -->
                        <div class="text-center text-slate-500 mt-8">
                            <i class="fa-solid fa-microphone-lines text-2xl mb-2 opacity-50"></i>
                            <p class="text-sm font-bold">Pronto per il Commentary</p>
                            <p class="text-xs mt-1">Clicca su un evento per iniziare</p>
                        </div>
                    </div>

                    <!-- Footer Minimale -->
                    <div class="p-4 bg-slate-900/80 border-t border-white/10 flex justify-between items-center shrink-0 footer-controls">
                        <button onclick="window.confirmReset()" class="text-xs font-bold text-slate-400 hover:text-rose-400 transition-all flex items-center gap-2">
                            <i class="fa-solid fa-trash text-xs"></i>
                            Reset
                        </button>
                        <button onclick="window.importCommentary()" class="bg-white text-slate-900 px-4 py-2 rounded-lg font-bold text-xs hover:bg-rose-50 transition-all flex items-center gap-2 import-btn">
                            Importa
                            <i class="fa-solid fa-arrow-right text-xs"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL INPUT EVENTO -->
    <div id="event-input-modal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-[120] hidden flex items-center justify-center p-4">
        <div class="bg-slate-800 border border-white/20 rounded-[2rem] w-full max-w-lg shadow-2xl overflow-hidden modal-enter">
            <header class="bg-slate-700/80 p-6 flex justify-between items-center">
                <h3 id="event-modal-title" class="text-white font-black uppercase italic text-sm tracking-widest">Dettaglio Evento</h3>
                <button onclick="document.getElementById('event-input-modal').classList.add('hidden')" class="text-slate-400 hover:text-white transition-colors w-10 h-10 flex items-center justify-center rounded-xl hover:bg-slate-600">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </header>
            <div class="p-6">
                <div id="event-form-fields" class="space-y-4">
                    <!-- Campi generati dinamicamente via JS -->
                </div>
                <div class="mt-8 flex gap-4">
                    <button onclick="document.getElementById('event-input-modal').classList.add('hidden')" class="flex-1 py-4 rounded-xl bg-slate-700 hover:bg-slate-600 text-white font-bold text-sm uppercase transition-all">
                        Annulla
                    </button>
                    <button onclick="window.saveEvent()" class="flex-1 py-4 rounded-xl bg-gradient-to-r from-rose-600 to-rose-700 hover:from-rose-700 hover:to-rose-800 text-white font-black text-sm uppercase shadow-lg transition-all">
                        Salva Evento
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation footer per tornare alla home -->
    <div class="fixed bottom-4 left-4 z-50">
        <a href="index.php" class="bg-slate-800 hover:bg-slate-700 text-white p-3 rounded-full shadow-lg transition-all flex items-center gap-2">
            <i class="fa-solid fa-home"></i>
            <span class="text-xs font-bold hidden sm:inline">Home</span>
        </a>
    </div>

    <script>
        // Sistema di gestione Commentary con salvataggio JSON
        let commentaryData = [];
        let currentEventType = null;
        let driversData = [];

        // Carica i dati dei piloti all'avvio
        async function loadDrivers() {
            try {
                const response = await fetch('drivers.json');
                if (response.ok) {
                    driversData = await response.json();
                    console.log('Piloti caricati:', driversData.length);
                } else {
                    console.error('Errore nel caricamento dei piloti');
                }
            } catch (error) {
                console.error('Errore nel fetch dei piloti:', error);
            }
        }

        function generateDriverSelect(elementId, label) {
            const uniqueDrivers = Array.from(new Map(driversData.map(d => [d.driver_number, d])).values());
            const options = uniqueDrivers.map(driver => 
                `<option value="${driver.broadcast_name}">${driver.broadcast_name}</option>`
            ).join('');

            return `
                <div class="mb-4">
                    <label class="block text-white text-sm font-bold mb-2">${label}</label>
                    <select id="${elementId}" class="w-full p-3 rounded-xl bg-slate-700 text-white border border-slate-600 focus:border-rose-500 outline-none">
                        <option value="">Seleziona pilota</option>
                        ${options}
                    </select>
                </div>
            `;
        }

        // ===== TIMER SYSTEM (BACKGROUND PERSISTENT) =====
        let timerInterval = null;
        let timerRunning = false;
        let timerMs = 0; // Millisecondi trascorsi
        let timerStartTime = null; // Timestamp di inizio per calcolo real-time
        const TIMER_STORAGE_KEY = 'commentaryTimer_state';
        const TIMER_DATA_KEY = 'commentaryTimer_data';

        // Carica lo stato del timer da localStorage
        function loadTimerState() {
            const state = localStorage.getItem(TIMER_STORAGE_KEY);
            const data = localStorage.getItem(TIMER_DATA_KEY);
            
            if (state && data) {
                const parsedState = JSON.parse(state);
                const parsedData = JSON.parse(data);
                
                timerRunning = parsedState.running;
                timerMs = parsedData.ms;
                
                // Se il timer era in esecuzione, riprendilo dal tempo reale
                if (timerRunning) {
                    timerStartTime = parsedData.startTime;
                    // Calcola il tempo trascorso durante l'assenza
                    const currentTime = Date.now();
                    const elapsedSinceLastUpdate = currentTime - timerStartTime;
                    timerMs += elapsedSinceLastUpdate;
                    
                    // Aggiorna il timestamp di inizio al momento corrente
                    timerStartTime = currentTime;
                    startTimerInterval();
                    
                    console.log('⏱️ Timer ripreso da background. Tempo aggiunto:', elapsedSinceLastUpdate, 'ms');
                } else if (timerInterval) {
                    clearInterval(timerInterval);
                    timerInterval = null;
                }
                
                updateTimerDisplay();
                updateTimerIcon();
            }
        }

        // Salva lo stato del timer in localStorage
        function saveTimerState() {
            const state = {
                running: timerRunning
            };
            
            const data = {
                ms: timerMs,
                startTime: timerStartTime
            };
            
            localStorage.setItem(TIMER_STORAGE_KEY, JSON.stringify(state));
            localStorage.setItem(TIMER_DATA_KEY, JSON.stringify(data));
        }

        // Aggiorna l'icona del play/pause
        function updateTimerIcon() {
            const playIcon = document.getElementById('timer-play-icon');
            if (playIcon) {
                if (timerRunning) {
                    playIcon.classList.remove('fa-play');
                    playIcon.classList.add('fa-pause');
                } else {
                    playIcon.classList.remove('fa-pause');
                    playIcon.classList.add('fa-play');
                }
            }
        }

        function formatTime(ms) {
            const totalSeconds = Math.floor(ms / 1000);
            const hours = Math.floor(totalSeconds / 3600);
            const minutes = Math.floor((totalSeconds % 3600) / 60);
            const seconds = totalSeconds % 60;
            
            return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        }

        function updateTimerDisplay() {
            const display = document.getElementById('comm-timer-display');
            if (display) {
                display.textContent = formatTime(timerMs);
            }
        }

        function startTimerInterval() {
            if (timerInterval) {
                clearInterval(timerInterval);
            }
            timerInterval = setInterval(updateTimerRealTime, 50);
        }

        // Timer che si aggiorna in base al tempo reale
        function updateTimerRealTime() {
            if (timerRunning && timerStartTime) {
                const currentTime = Date.now();
                const elapsed = currentTime - timerStartTime;
                timerMs += elapsed;
                timerStartTime = currentTime;
                updateTimerDisplay();
            }
        }

        window.toggleCommTimer = function() {
            if (timerRunning) {
                // Pausa il timer
                clearInterval(timerInterval);
                timerInterval = null;
                timerRunning = false;
                console.log('⏱️ Timer pausato. Tempo totale:', formatTime(timerMs));
            } else {
                // Avvia il timer
                timerRunning = true;
                timerStartTime = Date.now(); // Registra il tempo di inizio
                console.log('⏱️ Timer avviato in background');
                
                // Aggiorna il timer ogni 50ms per precisione
                startTimerInterval();
            }
            
            updateTimerIcon();
            saveTimerState();
        };

        window.resetCommTimer = function() {
            // Ferma il timer
            if (timerRunning || timerInterval) {
                clearInterval(timerInterval);
                timerInterval = null;
                timerRunning = false;
                updateTimerIcon();
            }
            
            // Resetta il contatore
            timerMs = 0;
            timerStartTime = null;
            updateTimerDisplay();
            saveTimerState();
            console.log('⏱️ Timer resettato');
        };

        window.openEditTimer = function() {
            const currentTime = formatTime(timerMs);
            const newTime = prompt('Modifica il tempo (HH:MM:SS):', currentTime);
            
            if (newTime !== null) {
                // Parsing del formato HH:MM:SS
                const parts = newTime.split(':');
                if (parts.length === 3) {
                    const hours = parseInt(parts[0]) || 0;
                    const minutes = parseInt(parts[1]) || 0;
                    const seconds = parseInt(parts[2]) || 0;
                    
                    timerMs = (hours * 3600 + minutes * 60 + seconds) * 1000;
                    updateTimerDisplay();
                    saveTimerState();
                    console.log('⏱️ Timer modificato a:', formatTime(timerMs));
                }
            }
        };

        // Monitora il cambio di visibilità della finestra
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden && timerRunning) {
                // La finestra è tornata visibile - sincronizza il timer
                console.log('👁️ Finestra torna visibile - sincronizzazione timer');
                loadTimerState();
            }
        });

        // Sincronizza il timer periodicamente anche in background
        setInterval(() => {
            if (timerRunning) {
                saveTimerState();
            }
        }, 1000);

        window.openEventModal = async function(eventType) {
            currentEventType = eventType;

            // I piloti vengono caricati via fetch: attendi il caricamento prima di creare il select.
            if (driversData.length === 0) {
                await loadDrivers();
            }

            const modal = document.getElementById('event-input-modal');
            const title = document.getElementById('event-modal-title');
            const formFields = document.getElementById('event-form-fields');

            // Titoli per ogni evento
            const eventTitles = {
                'bandiera-green': 'Bandiera Verde',
                'bandiera-rossa': 'Bandiera Rossa', 
                'bandiera-gialla': 'Bandiera Gialla',
                'safety-car': 'Safety Car',
                'vsc': 'Virtual Safety Car',
                'giro-veloce-assoluto': 'Giro Più Veloce',
                'giro-veloce-personale': 'Record Personale',
                'giro-normale': 'Giro Standard',
                'pit-stop': 'Pit Stop',
                'sorpasso': 'Sorpasso',
                'note': 'Nota'
            };

            title.textContent = eventTitles[eventType] || 'Evento';

            // Genera campi dinamici
            let fields = '';

            // Campi base per tutti gli eventi (pilota singolo)
            if (eventType !== 'sorpasso') {
                fields += generateDriverSelect('pilota', 'Pilota');
            }

            fields += `
                <div class="mb-4">
                    <label class="block text-white text-sm font-bold mb-2">Minuto/Giro</label>
                    <input type="text" id="tempo" class="w-full p-3 rounded-xl bg-slate-700 text-white border border-slate-600 focus:border-rose-500 outline-none" placeholder="es. 1:23.456 o Giro 15">
                </div>
            `;

            // Campo pneumatico per eventi specifici
            if (['giro-veloce-assoluto', 'giro-veloce-personale', 'giro-normale', 'pit-stop'].includes(eventType)) {
                fields += `
                    <div class="mb-4">
                        <label class="block text-white text-sm font-bold mb-2">Pneumatico</label>
                        <select id="pneumatico" class="w-full p-3 rounded-xl bg-slate-700 text-white border border-slate-600 focus:border-rose-500 outline-none">
                            <option value="">Seleziona mescola</option>
                            <option value="soft">🔴 Soft</option>
                            <option value="medium">🟡 Medium</option>
                            <option value="hard">⚪ Hard</option>
                            <option value="inter">🟢 Intermedie</option>
                            <option value="wet">🔵 Bagnato</option>
                        </select>
                    </div>
                `;
            }

            // Campi specifici per sorpasso (due piloti)
            if (eventType === 'sorpasso') {
                fields += generateDriverSelect('pilota-sorpassa', 'Pilota che Sorpassa');
                fields += generateDriverSelect('pilota-sorpassato', 'Pilota Sorpassato');
            }

            // Campo commento per tutti
            fields += `
                <div class="mb-4">
                    <label class="block text-white text-sm font-bold mb-2">Commento</label>
                    <textarea id="commento" rows="3" class="w-full p-3 rounded-xl bg-slate-700 text-white border border-slate-600 focus:border-rose-500 outline-none resize-none" placeholder="Descrivi l'evento..."></textarea>
                </div>
            `;

            formFields.innerHTML = fields;
            modal.classList.remove('hidden');
        };

        window.saveEvent = function() {
            if (!currentEventType) return;

            const eventData = {
                tipo: currentEventType,
                timestamp: new Date().toISOString(),
                tempo_gara: document.getElementById('comm-timer-display').textContent
            };

            // Raccogli dati base
            if (currentEventType !== 'sorpasso') {
                eventData.pilota = document.getElementById('pilota')?.value || '';
            }

            eventData.minuto_giro = document.getElementById('tempo')?.value || '';
            eventData.commento = document.getElementById('commento')?.value || '';

            // Dati specifici
            if (['giro-veloce-assoluto', 'giro-veloce-personale', 'giro-normale', 'pit-stop'].includes(currentEventType)) {
                eventData.pneumatico = document.getElementById('pneumatico')?.value || '';
            }

            if (currentEventType === 'sorpasso') {
                eventData.pilota_sorpassa = document.getElementById('pilota-sorpassa')?.value || '';
                eventData.pilota_sorpassato = document.getElementById('pilota-sorpassato')?.value || '';
            }

            // Aggiungi ai dati del commentary
            commentaryData.push(eventData);

            // Salva in localStorage per persistenza
            localStorage.setItem('commentaryData', JSON.stringify(commentaryData));
            persistCommentary();

            // Aggiorna il feed visivo
            updateCommentaryFeed();

            // Chiudi modal
            document.getElementById('event-input-modal').classList.add('hidden');
            currentEventType = null;
        };

        function updateCommentaryFeed() {
            const feed = document.getElementById('commentary-feed');
            if (commentaryData.length === 0) {
                feed.innerHTML = `
                    <div class="text-center text-slate-500 mt-20">
                        <i class="fa-solid fa-microphone-lines text-4xl mb-4 opacity-50"></i>
                        <p class="text-lg font-bold">Pronto per il Commentary Live</p>
                        <p class="text-sm mt-2">Clicca su un evento per iniziare</p>
                    </div>
                `;
                return;
            }

            const eventIcons = {
                'bandiera-green': 'fa-flag text-emerald-400',
                'bandiera-rossa': 'fa-flag text-red-400',
                'bandiera-gialla': 'fa-flag text-yellow-400',
                'safety-car': 'fa-car text-orange-400',
                'vsc': 'fa-stopwatch text-orange-300',
                'giro-veloce-assoluto': 'fa-gauge-high text-purple-400',
                'giro-veloce-personale': 'fa-gauge text-emerald-300',
                'giro-normale': 'fa-circle-notch text-slate-400',
                'pit-stop': 'fa-wrench text-blue-400',
                'sorpasso': 'fa-right-left text-indigo-400',
                'note': 'fa-note-sticky text-slate-300'
            };

            const eventTitles = {
                'bandiera-green': 'Bandiera Verde',
                'bandiera-rossa': 'Bandiera Rossa',
                'bandiera-gialla': 'Bandiera Gialla',
                'safety-car': 'Safety Car',
                'vsc': 'Virtual Safety Car',
                'giro-veloce-assoluto': 'Giro Più Veloce',
                'giro-veloce-personale': 'Record Personale',
                'giro-normale': 'Giro Standard',
                'pit-stop': 'Pit Stop',
                'sorpasso': 'Sorpasso',
                'note': 'Nota'
            };

            const html = commentaryData.map((event, index) => {
                const realIndex = commentaryData.length - 1 - index;
                let pilotaInfo = '';
                if (event.tipo === 'sorpasso') {
                    pilotaInfo = `<span class="font-bold">${event.pilota_sorpassa}</span> sorpassa <span class="font-bold">${event.pilota_sorpassato}</span>`;
                } else if (event.pilota) {
                    pilotaInfo = `<span class="font-bold">${event.pilota}</span>`;
                }

                const pneumaticoInfo = event.pneumatico ? `<span class="text-xs bg-slate-700 px-2 py-1 rounded-lg">${event.pneumatico}</span>` : '';

                return `
                    <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4 hover:bg-slate-800/70 transition-all">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-slate-700/50 flex items-center justify-center">
                                    <i class="fa-solid ${eventIcons[event.tipo]} text-sm"></i>
                                </div>
                                <div>
                                    <div class="flex flex-wrap items-center gap-2 mb-1">
                                        <h4 class="font-bold text-white text-sm">${eventTitles[event.tipo]}</h4>
                                        ${pneumaticoInfo}
                                        <span class="text-xs text-slate-500 bg-slate-700/50 px-2 py-1 rounded">${event.tempo_gara}</span>
                                    </div>
                                    <div class="text-xs text-slate-300">
                                        ${pilotaInfo}
                                        ${event.minuto_giro ? `<span class="text-slate-400"> • ${event.minuto_giro}</span>` : ''}
                                    </div>
                                </div>
                            </div>
                            <button onclick="deleteEvent(${realIndex})" class="text-slate-500 hover:text-red-400 transition-colors w-8 h-8 flex items-center justify-center rounded-lg hover:bg-slate-700 flex-shrink-0">
                                <i class="fa-solid fa-trash text-xs"></i>
                            </button>
                        </div>
                        <div class="pl-13">
                            <p class="text-xs text-slate-200 break-words leading-relaxed">${event.commento}</p>
                        </div>
                    </div>
                `;
            }).reverse().join('');

            feed.innerHTML = html;
        }

        window.deleteEvent = function(index) {
            if (confirm('Eliminare questo evento?')) {
                commentaryData.splice(index, 1);
                localStorage.setItem('commentaryData', JSON.stringify(commentaryData));
                persistCommentary();
                updateCommentaryFeed();
            }
        };

        window.confirmReset = function() {
            if (confirm('Sei sicuro di voler resettare la sessione? Tutti i dati verranno persi.')) {
                commentaryData = [];
                localStorage.removeItem('commentaryData');
                persistCommentary();
                updateCommentaryFeed();
            }
        };

        window.importCommentary = async function() {
            if (commentaryData.length === 0) {
                alert('Nessun evento da esportare!');
                return;
            }

            const result = await persistCommentary();
            if (result?.success) {
                alert(`JSON creato: ${result.file}`);
            } else {
                alert('Errore nella creazione del JSON.');
            }
        };

        async function persistCommentary() {
            try {
                const body = new URLSearchParams();
                body.set('commentary', JSON.stringify(commentaryData));

                const response = await fetch('commento.php?action=save-commentary', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: body.toString()
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('Errore nel salvataggio del commentary:', response.statusText, errorText);
                    try {
                        const debugResponse = await fetch('commento.php?action=save-commentary&debug=1', {
                            method: 'GET'
                        });
                        const debugText = await debugResponse.text();
                        console.error('Debug save-commentary:', debugText);
                    } catch (debugError) {
                        console.error('Errore debug save-commentary:', debugError);
                    }
                    return null;
                }

                return await response.json();
            } catch (error) {
                console.error('Errore nel salvataggio del commentary:', error);
                return null;
            }
        }

        // Carica dati salvati all'avvio
        document.addEventListener('DOMContentLoaded', function() {
            // Carica i piloti
            loadDrivers();

            const saved = localStorage.getItem('commentaryData');
            if (saved) {
                commentaryData = JSON.parse(saved);
                updateCommentaryFeed();
            }

            // Carica stato timer
            loadTimerState();
        });
    </script>

</body>
</html>