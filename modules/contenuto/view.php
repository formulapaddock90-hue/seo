<section id="mod-a" class="tab-content active">
    <h2>Modulo A: Generazione testo</h2>
    <?php
    $appCfg = require __DIR__ . '/../../config.php';
    if (trim((string)($appCfg['gemini_api_key'] ?? '')) === ''):
    ?>
    <div id="gemini-key-notice" class="notice notice-warn">
        ⚠️ <strong>Chiave API Gemini non configurata.</strong>
        Clicca su ⚙️ in alto a destra per inserirla, oppure aprila su <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener">Google AI Studio</a>.
    </div>
    <?php endif; ?>
    <form id="content-form">
        <label>Testo grezzo<textarea name="raw_text" id="raw-text" rows="10" required></textarea></label>
        <div class="form-actions-row">
            <button type="button" id="open-json-import">Importa testo da JSON (upload)</button>
            <button type="button" id="open-article-modal">Carica articolo salvato</button>
            <button type="button" id="analyze-keywords-ia" style="background-color: #9b59b6; color: white;">🔍 IA: Estrai Keyword con Trends</button>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;">
            <label>Campionato
                <select name="campionato" id="campionato-select">
                    <option value="f1">Formula 1 (F1)</option>
                    <option value="f2">Formula 2 (F2)</option>
                    <option value="wec">WEC</option>
                </select>
            </label>
            <label>Circuito
                <select name="circuito" id="circuito-select-a">
                    <option value="">Seleziona circuito...</option>
                </select>
            </label>
        </div>

        <div style="margin-top: 10px;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" name="is_live_session" id="live-session-check" value="1">
                <span>Sessione Live?</span>
            </label>
        </div>

        <div id="live-session-container" class="hidden" style="margin-top: 10px;">
            <label>Nome Sessione
                <select name="live_session_name" id="live-session-name">
                    <option value="FP1">FP1</option>
                    <option value="FP2">FP2</option>
                    <option value="FP3">FP3</option>
                    <option value="Qualifiche">Qualifiche</option>
                    <option value="Sprint Qualifiche">Sprint Qualifiche</option>
                    <option value="Sprint Race">Sprint Race</option>
                    <option value="Gara">Gara</option>
                </select>
            </label>
        </div>

        <label>Keyword principale da Google Trends (opzionale)
            <input type="text" name="trends_main_keyword" id="trends-main-keyword" placeholder="es. GP Monaco F1">
        </label>
        <label>Keyword correlate da Google Trends (una per riga, opzionale)
            <textarea name="trends_keywords" id="trends-keywords" rows="4" placeholder="es.&#10;meteo monaco f1&#10;strategia gomme monaco"></textarea>
        </label>

        <label>Categoria selezionata da sitemap
            <select id="selected-category" name="category_name">
                <option value="">Seleziona categoria da sitemap</option>
            </select>
        </label>

        <button type="button" id="generate-seo">Genera bozza SEO con Gemini</button>
    </form>
    <div id="gemini-result"></div>
</section>

<div id="json-import-modal" class="modal hidden" aria-hidden="true">
    <div class="modal-panel">
        <h3>Importa testo da JSON in upload</h3>
        <label>File JSON
            <select id="json-file-select">
                <option value="">Seleziona file .json</option>
            </select>
        </label>
        <div class="modal-actions">
            <button type="button" id="json-import-confirm">Importa nel testo grezzo</button>
            <button type="button" id="json-import-close">Chiudi</button>
        </div>
    </div>
</div>

<div id="article-modal" class="modal hidden" aria-hidden="true">
    <div class="modal-panel modal-panel-wide">
        <h3>Articoli salvati</h3>
        <input type="search" id="article-search" placeholder="Cerca articolo per titolo o categoria..." autocomplete="off" style="width:100%;box-sizing:border-box;margin-bottom:10px;">
        <div id="articles-list" class="articles-list"></div>
        <div class="modal-actions" style="margin-top:12px;display:flex;justify-content:flex-end;gap:10px;">
            <button type="button" id="article-modal-close">Chiudi</button>
        </div>
    </div>
</div>
