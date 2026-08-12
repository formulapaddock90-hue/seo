<!-- Modal Impostazioni -->
<div id="settings-modal" class="modal hidden" aria-hidden="true">
    <div class="modal-panel modal-panel-settings">
        <h3>⚙️ Impostazioni</h3>
        <label>
            Chiave API Gemini
            <div class="settings-key-row">
                <input type="password" id="settings-gemini-key" placeholder="AIzaSy..." autocomplete="off">
                <button type="button" id="settings-key-toggle" class="btn-icon" title="Mostra/Nascondi">👁</button>
            </div>
            <small>Ottienila su <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener">Google AI Studio</a></small>
        </label>
        <label>
            URL modello Gemini
            <input type="text" id="settings-gemini-url" placeholder="https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent" autocomplete="off">
            <small>Cambia modello es. <code>gemini-3.6-flash</code>, <code>gemini-3.5-flash</code>, <code>gemini-2.0-flash</code></small>
        </label>

        <div style="display:flex;justify-content:flex-end;margin:6px 0 10px 0;">
            <button type="button" id="settings-test-gemini" class="btn-sm">Test API Gemini</button>
        </div>

        <div class="settings-sites-head">
            <h4>Siti esterni WordPress</h4>
            <button type="button" id="settings-add-site" class="btn-sm">+ Aggiungi sito</button>
        </div>
        <div id="settings-sites-list" class="settings-sites-list"></div>

        <div id="settings-result"></div>
        <div class="modal-actions">
            <button type="button" id="settings-save">Salva</button>
            <button type="button" id="settings-close">Annulla</button>
        </div>
    </div>
</div>
