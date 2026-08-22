<section id="mod-b" class="tab-content">
    <h2>Modulo B: Immagini</h2>

    <div style="margin-bottom: 15px; padding: 12px; background: #1a1a2e; border: 1px solid #3a3a5e; border-radius: 8px; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <span style="font-weight: 600; color: #fff;">Sincronizzazione Modulo A -> Modulo B</span>
            <div style="font-size: 0.82rem; color: #aaa;" id="mod-b-sync-status">Stato: Immagini non caricate. Fai clic su 'Eredita e Carica' per iniziare.</div>
        </div>
        <button type="button" id="b-load-inherit-btn" style="background-color: #27ae60; color: white; padding: 8px 16px; font-weight: bold; border-radius: 6px; border: none; cursor: pointer; transition: background 0.2s;">🔄 Eredita dati e Carica Immagini</button>
    </div>

    <div class="module-b-simple-grid" style="grid-template-columns: 1fr 1fr; gap: 12px;">
        <div class="module-b-panel">
            <h3>Caricamento da PC</h3>
            <p class="muted">Carica immagini da computer o telefono nella cartella attiva della libreria.</p>
            <button type="button" id="b-upload-images-btn">📤 Carica immagini da PC/telefono</button>
            <input type="file" id="b-upload-images-input" accept="image/*" multiple hidden>
        </div>

        <div class="module-b-panel">
            <h3>Photo Wall in galleria</h3>
            <p class="muted">Scarica immagini dal Photo Wall e caricale direttamente nella galleria del modulo.</p>
            <button type="button" id="b-download-photo-wall-btn">⬇️ Scarica da Photo Wall</button>
            <div id="b-photo-wall-status" class="notice notice-ok hidden" style="margin-top: 10px; font-size: 0.85rem;"></div>
        </div>
    </div>

    <div style="margin: 18px 0 10px; padding: 16px; background: #101010; border: 1px solid #333; border-radius: 10px;">
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
            <div>
                <h3 style="margin:0 0 4px;">𝕏 Ultima foto dai team</h3>
                <p class="muted" style="margin:0; font-size:0.82rem;">Scegli un team e aggiungi la sua ultima foto pubblicata su X alle immagini disponibili per i titoli H2.</p>
            </div>
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <select id="b-x-team-select" aria-label="Team Formula 1">
                    <option value="">Seleziona team...</option>
                    <option value="mclaren">McLaren</option>
                    <option value="ferrari">Ferrari</option>
                    <option value="mercedes">Mercedes</option>
                    <option value="red-bull">Red Bull Racing</option>
                    <option value="aston-martin">Aston Martin</option>
                    <option value="alpine">Alpine</option>
                    <option value="williams">Williams</option>
                    <option value="haas">Haas</option>
                    <option value="racing-bulls">Racing Bulls</option>
                    <option value="audi">Audi Revolut F1 Team</option>
                    <option value="cadillac">Cadillac Formula 1 Team</option>
                </select>
                <button type="button" id="b-load-x-latest-btn">𝕏 Carica ultima foto</button>
            </div>
        </div>
        <div id="b-x-latest-status" class="muted" style="margin-top:10px; font-size:0.82rem;"></div>
        <div id="b-x-latest-preview" class="hidden" style="margin-top:12px; max-width:420px;">
            <img id="b-x-latest-image" alt="Ultima foto pubblicata dal team su X" style="display:block; width:100%; height:auto; border-radius:8px; border:1px solid #333;">
            <a id="b-x-latest-link" href="#" target="_blank" rel="noopener noreferrer" style="display:inline-block; margin-top:7px;">Apri il post su X</a>
        </div>
    </div>

    <div class="module-b-section-head">
        <div>
            <h3>Selezione immagini per i titoli H2</h3>
            <p class="muted">Per ogni H2 scegli direttamente un'immagine dalla libreria. Quando apri la selezione, scegli prima la cartella e poi l'immagine.</p>
        </div>
    </div>

    <div id="b-h2-cards"></div>

</section>

<div id="img-picker-modal" class="modal hidden" aria-hidden="true">
    <div class="modal-panel modal-panel-wide">
        <h3>🖼️ Scegli immagine — <span id="img-picker-h2-label"></span></h3>
        <div class="img-picker-body">
            <div class="img-picker-folders" id="img-picker-folders"></div>
            <div class="img-picker-gallery" id="img-picker-gallery">
                <div class="muted">Seleziona una cartella per vedere le immagini.</div>
            </div>
        </div>
        <div class="modal-actions">
            <button type="button" id="img-picker-close">Chiudi</button>
        </div>
    </div>
</div>

<div id="upload-folder-modal" class="modal hidden" aria-hidden="true">
    <div class="modal-panel modal-panel-wide">
        <h3>📁 Scegli cartella di destinazione per l'upload</h3>
        <div class="upload-folder-body">
            <div class="upload-folder-path" id="upload-folder-current">Cartella corrente: uploads</div>

            <div class="upload-folder-new-section">
                <div class="upload-folder-new-row">
                    <input type="text" id="upload-new-folder-name" placeholder="Nome nuova cartella..." maxlength="100">
                    <button type="button" id="upload-new-folder-btn">➕ Crea cartella</button>
                </div>
                <div id="upload-new-folder-result" class="muted" style="margin-top: 8px; display: none;"></div>
            </div>

            <div class="upload-folder-list" id="upload-folder-list">
                <div class="muted">Caricamento cartelle...</div>
            </div>
        </div>
        <div class="modal-actions">
            <button type="button" id="upload-folder-cancel">Annulla</button>
            <button type="button" id="upload-folder-confirm">✓ Seleziona questa cartella</button>
        </div>
    </div>
</div>
