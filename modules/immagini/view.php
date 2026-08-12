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

    <!-- Hub Team F1 -->
    <div style="margin: 18px 0 10px 0; padding: 16px; background: #0d1b2a; border: 1px solid #1a3a5c; border-radius: 10px;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 12px;">
            <div>
                <h3 style="margin: 0 0 4px 0; color: #e8f4ff;">🏎️ Hub Fotografici Team F1</h3>
                <p class="muted" style="margin: 0; font-size: 0.82rem;">Scarica direttamente le immagini dagli hub ufficiali dei team. Seleziona i team e avvia il download.</p>
            </div>
            <button type="button" id="b-download-team-hubs-btn" style="background: linear-gradient(135deg, #e10600, #a00000); color: white; padding: 10px 20px; font-weight: bold; border-radius: 8px; border: none; cursor: pointer; white-space: nowrap; font-size: 0.95rem;">
                🏁 Scarica da Hub Team
            </button>
        </div>

        <!-- ScrapingBee API Key -->
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px; padding:10px 12px; background:#071220; border:1px solid #1e3a5a; border-radius:8px; flex-wrap:wrap;">
            <span style="font-size:0.8rem; color:#7ab3d4; white-space:nowrap;">🐝 ScrapingBee API Key:</span>
            <input type="password" id="b-scrapingbee-key" placeholder="Incolla qui la tua API key..."
                style="flex:1; min-width:200px; background:#0a1a2a; border:1px solid #2a4a6a; border-radius:6px; color:#e0e0e0; padding:6px 10px; font-size:0.82rem; font-family:monospace;">
            <button type="button" id="b-scrapingbee-save" style="background:#1e4a7a; color:#fff; border:none; border-radius:6px; padding:6px 14px; font-size:0.8rem; cursor:pointer; white-space:nowrap;">
                💾 Salva
            </button>
            <span id="b-scrapingbee-status" style="font-size:0.75rem; color:#888;"></span>
            <a href="https://app.scrapingbee.com/account/register" target="_blank"
               style="font-size:0.75rem; color:#4a9eff; text-decoration:none; white-space:nowrap; margin-left:auto;">
                ➕ Ottieni gratis 1000 crediti
            </a>
        </div>

        <div id="b-team-hub-checkboxes" style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px;">
            <?php
            $teams = [
                ['id'=>'williams',    'label'=>'Williams',        'color'=>'#005AFF'],
                ['id'=>'haas',        'label'=>'Haas',            'color'=>'#B6BABD'],
                ['id'=>'mercedes',    'label'=>'Mercedes',        'color'=>'#00D2BE'],
                ['id'=>'red_bull',    'label'=>'Red Bull',        'color'=>'#3671C6'],
                ['id'=>'aston_martin','label'=>'Aston Martin',    'color'=>'#358C75'],
                ['id'=>'alpine',      'label'=>'Alpine',          'color'=>'#FF87BC'],
                ['id'=>'visa_rb',     'label'=>'Visa Cash App RB','color'=>'#6692FF'],
                ['id'=>'sauber',      'label'=>'Sauber',          'color'=>'#52E252'],
                ['id'=>'pirelli',     'label'=>'Pirelli',         'color'=>'#FFD700'],
                ['id'=>'mclaren',     'label'=>'McLaren',         'color'=>'#FF8000'],
            ];
            foreach ($teams as $t): ?>
            <label style="display:inline-flex; align-items:center; gap:6px; background:#1a2a3a; border:2px solid <?= $t['color'] ?>33; border-radius:20px; padding:5px 12px; cursor:pointer; user-select:none; font-size:0.82rem; color:#ddd; transition: border-color 0.2s;">
                <input type="checkbox" name="team_hub" value="<?= $t['id'] ?>" checked style="accent-color: <?= $t['color'] ?>;">
                <span style="width:10px;height:10px;border-radius:50%;background:<?= $t['color'] ?>;display:inline-block;"></span>
                <?= $t['label'] ?>
            </label>
            <?php endforeach; ?>
        </div>

        <div id="b-team-hub-status" style="font-size: 0.82rem; color: #aaa; min-height: 18px;"></div>
        <div id="b-team-hub-progress" style="display:none; margin-top: 8px;">
            <div style="background:#0a1a2a; border-radius:6px; height:8px; overflow:hidden;">
                <div id="b-team-hub-bar" style="height:100%; background: linear-gradient(90deg, #e10600, #ff6b6b); width:0%; transition: width 0.3s;"></div>
            </div>
            <div id="b-team-hub-log" style="margin-top: 8px; font-size: 0.78rem; color: #888; max-height: 80px; overflow-y: auto; font-family: monospace;"></div>
        </div>
    </div>

    <div class="module-b-section-head">
        <div>
            <h3>Selezione immagini per i titoli H2</h3>
            <p class="muted">Per ogni H2 scegli direttamente un'immagine dalla libreria. Quando apri la selezione, scegli prima la cartella e poi l'immagine.</p>
        </div>
    </div>

    <div id="b-h2-cards"></div>

    <div class="module-b-section-head" style="margin-top: 30px;">
        <div>
            <h3>📁 Galleria Libreria Immagini</h3>
            <p class="muted">Elenco delle immagini presenti nella libreria del modulo.</p>
        </div>
    </div>
    <div id="b-library-gallery" style="min-height: 150px; background: #111; border: 1px solid #222; border-radius: 8px; padding: 12px; display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px;">
        <div class="muted" style="grid-column: 1/-1; text-align: center; padding: 20px;">Fai clic su 'Eredita e Carica' o 'Scarica da Photo Wall' per caricare la libreria.</div>
    </div>
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
