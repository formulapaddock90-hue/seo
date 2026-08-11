<section id="mod-postgara" class="tab-content">
    <h2>Modulo E: Post gara</h2>
    <p class="muted">Flusso guidato per analisi team, commenti e generazione articolo SEO in pochi passaggi.</p>

    <div class="postgara-guide-grid">
        <div class="postgara-guide-card">
            <h3>Come funziona</h3>
            <ol class="postgara-steps-list">
                <li>Esegui <code>f1_undercutf1_charts.ps1</code> per caricare i dati dal tuo PC sul server.</li>
                <li>Clicca "1. Carica da undercutf1" per popolare i box team e i grafici.</li>
                <li>Aggiungi commenti e immagini, poi genera l'articolo SEO.</li>
            </ol>
        </div>
        <div class="postgara-guide-card postgara-summary-card">
            <h3>Stato avanzamento</h3>
            <div class="postgara-summary-grid">
                <div class="postgara-summary-item">
                    <strong id="postgara-count-loaded">0/0</strong>
                    <span>team caricati</span>
                </div>
                <div class="postgara-summary-item">
                    <strong id="postgara-count-comments">0</strong>
                    <span>commenti</span>
                </div>
                <div class="postgara-summary-item">
                    <strong id="postgara-count-images">0</strong>
                    <span>immagini</span>
                </div>
                <div class="postgara-summary-item accent">
                    <strong id="postgara-count-ready">0</strong>
                    <span>team completi</span>
                </div>
            </div>
            <div id="postgara-next-step" class="muted">Aggiungi commenti o immagini ai team, poi genera l'articolo SEO.</div>
        </div>
    </div>

    <div class="postgara-toolbar">
        <button type="button" id="postgara-load-undercutf1-btn" title="Carica dati da undercutf1 (eseguire prima f1_undercutf1_charts.ps1)">1. Carica da undercutf1</button>
        <button type="button" id="postgara-load-final-standings-btn" title="Carica classifica finale dal database (polling manuale o automatico)">🏁 Classifica Finale</button>
        <button type="button" id="postgara-toggle-incomplete-btn">Mostra solo da completare</button>
        <button type="button" id="postgara-generate-seo-btn">2. Genera articolo SEO</button>
        <button type="button" id="postgara-insert-charts-btn" title="Carica i grafici su WordPress e aggiungili all'articolo">3. Inserisci grafici nel post</button>
        <button type="button" id="postgara-clear-comments-btn">Svuota commenti team</button>
    </div>

    <div id="postgara-standings-status" class="muted postgara-inline-status" style="display: none;"></div>
    <div id="postgara-final-standings-display"></div>

    <div class="postgara-featured-row">
        <span class="postgara-featured-label">🖼️ Immagine in evidenza:</span>
        <div class="postgara-featured-preview" id="postgara-featured-preview">
            <span class="muted">—</span>
        </div>
        <label class="postgara-featured-pick-btn" for="postgara-featured-file-input" title="Carica file immagine">📁 Scegli file</label>
        <input type="file" id="postgara-featured-file-input" accept="image/*" style="display:none">
        <input type="url" id="postgara-featured-url-input" placeholder="oppure incolla URL immagine..." class="postgara-featured-url-field">
        <button type="button" id="postgara-featured-clear-btn" class="postgara-featured-clear" title="Rimuovi">✕</button>
    </div>

    <div id="postgara-autosave-status" class="muted postgara-inline-status"></div>

    <div id="postgara-boxes-container" class="postgara-boxes-container">
        <!-- Box team generate dinamicamente -->
    </div>

    <section class="postgara-group-section" id="postgara-full-charts-section">
        <div class="postgara-group-header">
            <div>
                <h3>Grafici analisi gara</h3>
                <p class="muted">Dati undercutf1: strategia gomme, tempi sul giro, speed trap, settori</p>
            </div>
        </div>

        <div class="postgara-group-chart-card">
            <div class="postgara-group-chart-title">Strategia gomme (Tyre Strategy)</div>
            <div class="postgara-group-chart-wrap">
                <canvas id="postgara-tyre-strategy-chart"></canvas>
            </div>
            <div class="postgara-group-chart-note muted" id="postgara-tyre-strategy-note"></div>
        </div>

        <div class="postgara-group-chart-card">
            <div class="postgara-group-chart-title">Tempi sul giro – Top 5 (P1-5)</div>
            <div class="postgara-group-chart-wrap">
                <canvas id="postgara-lap-times-g1-chart"></canvas>
            </div>
            <div class="postgara-group-chart-note muted" id="postgara-lap-times-g1-note"></div>
        </div>

        <div class="postgara-group-chart-card">
            <div class="postgara-group-chart-title">Tempi sul giro – Midfield (P6-10)</div>
            <div class="postgara-group-chart-wrap">
                <canvas id="postgara-lap-times-g2-chart"></canvas>
            </div>
            <div class="postgara-group-chart-note muted" id="postgara-lap-times-g2-note"></div>
        </div>

        <div class="postgara-group-chart-card">
            <div class="postgara-group-chart-title">Tempi sul giro – Back (P11-15)</div>
            <div class="postgara-group-chart-wrap">
                <canvas id="postgara-lap-times-g3-chart"></canvas>
            </div>
            <div class="postgara-group-chart-note muted" id="postgara-lap-times-g3-note"></div>
        </div>

        <div class="postgara-group-chart-card">
            <div class="postgara-group-chart-title">Tempi sul giro – Tail (P16-20)</div>
            <div class="postgara-group-chart-wrap">
                <canvas id="postgara-lap-times-g4-chart"></canvas>
            </div>
            <div class="postgara-group-chart-note muted" id="postgara-lap-times-g4-note"></div>
        </div>

        <div class="postgara-group-chart-card">
            <div class="postgara-group-chart-title">Speed Trap (velocità massima)</div>
            <div class="postgara-group-chart-wrap">
                <canvas id="postgara-speed-trap-chart"></canvas>
            </div>
            <div class="postgara-group-chart-note muted" id="postgara-speed-trap-note"></div>
        </div>

        <div class="postgara-group-chart-card">
            <div class="postgara-group-chart-title">Miglior Settore 1</div>
            <div class="postgara-group-chart-wrap">
                <canvas id="postgara-best-s1-chart"></canvas>
            </div>
            <div class="postgara-group-chart-note muted" id="postgara-best-s1-note"></div>
        </div>

        <div class="postgara-group-chart-card">
            <div class="postgara-group-chart-title">Miglior Settore 2</div>
            <div class="postgara-group-chart-wrap">
                <canvas id="postgara-best-s2-chart"></canvas>
            </div>
            <div class="postgara-group-chart-note muted" id="postgara-best-s2-note"></div>
        </div>

        <div class="postgara-group-chart-card">
            <div class="postgara-group-chart-title">Miglior Settore 3</div>
            <div class="postgara-group-chart-wrap">
                <canvas id="postgara-best-s3-chart"></canvas>
            </div>
            <div class="postgara-group-chart-note muted" id="postgara-best-s3-note"></div>
        </div>

        <div class="postgara-group-chart-card">
            <div class="postgara-group-chart-title">Miglior giro – SOFT</div>
            <div id="postgara-best-lap-soft-table"></div>
            <div class="postgara-group-chart-note muted" id="postgara-best-lap-soft-note"></div>
        </div>

        <div class="postgara-group-chart-card">
            <div class="postgara-group-chart-title">Miglior giro – MEDIUM</div>
            <div id="postgara-best-lap-medium-table"></div>
            <div class="postgara-group-chart-note muted" id="postgara-best-lap-medium-note"></div>
        </div>

        <div class="postgara-group-chart-card">
            <div class="postgara-group-chart-title">Miglior giro – HARD</div>
            <div id="postgara-best-lap-hard-table"></div>
            <div class="postgara-group-chart-note muted" id="postgara-best-lap-hard-note"></div>
        </div>

        <div class="postgara-group-chart-card">
            <div class="postgara-group-chart-title">Miglior giro – INTERMEDIATE</div>
            <div id="postgara-best-lap-intermediate-table"></div>
            <div class="postgara-group-chart-note muted" id="postgara-best-lap-intermediate-note"></div>
        </div>
    </section>

    <section class="postgara-group-section" id="postgara-compound-charts-section">
        <div class="postgara-group-header">
            <div>
                <h3>Grafici compound gara</h3>
                <p class="muted">Conteggi compound (richiede login F1 tramite undercutf1 --with-api)</p>
            </div>
        </div>

        <div class="postgara-group-chart-card">
            <div class="postgara-group-chart-title">Conteggio giri per compound</div>
            <div class="postgara-group-chart-wrap">
                <canvas id="postgara-compound-lap-count-chart"></canvas>
            </div>
            <div class="postgara-group-chart-note muted" id="postgara-compound-lap-count-note"></div>
        </div>

        <div class="postgara-group-chart-card">
            <div class="postgara-group-chart-title">Conteggio giri per pilota per compound</div>
            <div class="postgara-group-chart-wrap">
                <canvas id="postgara-driver-compound-lap-count-chart"></canvas>
            </div>
            <div class="postgara-group-chart-note muted" id="postgara-driver-compound-lap-count-note"></div>
        </div>

        <div class="postgara-group-chart-card">
            <div class="postgara-group-chart-title">Conteggio per compound usati</div>
            <div class="postgara-group-chart-wrap">
                <canvas id="postgara-compound-usage-chart"></canvas>
            </div>
            <div class="postgara-group-chart-note muted" id="postgara-compound-usage-note"></div>
        </div>

        <div class="postgara-group-chart-card">
            <div class="postgara-group-chart-title">Conteggio per compound usati per pilota</div>
            <div class="postgara-group-chart-wrap">
                <canvas id="postgara-driver-compound-usage-chart"></canvas>
            </div>
            <div class="postgara-group-chart-note muted" id="postgara-driver-compound-usage-note"></div>
        </div>
    </section>

    <div id="postgara-loading" class="muted" style="text-align: center; display: none;">
        Caricamento dati gara... ⏳
    </div>

    <div id="postgara-error" class="notice notice-warn" style="display: none;"></div>
</section>
