<section id="mod-chart" class="tab-content">
    <h2>Modulo F: Grafici</h2>
    <p class="muted">Classifica finale da MySQL + Grafici Wikipedia + editor personalizzato per grafici aggiuntivi da meteo e Pirelli.</p>

    <!-- Wikipedia Pilot Search Section -->
    <section class="postgara-group-section" id="wikipedia-search-section" style="margin-bottom: 30px; padding: 20px; background-color: #f9f9f9; border-radius: 6px;">
        <div class="postgara-group-header">
            <div>
                <h3>🔍 Ricerca Pilota Wikipedia</h3>
                <p class="muted">Carica i dati storici del pilota da Wikipedia F1</p>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr auto auto; gap: 10px; align-items: end;">
            <label style="margin: 0;">
                <span style="display: block; margin-bottom: 5px; font-weight: 500;">Nome pilota</span>
                <input type="text" id="wikipedia-pilot-search" placeholder="es. Charles Leclerc, Lewis Hamilton..." style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </label>
            <button type="button" id="wikipedia-load-btn" style="padding: 8px 16px; background-color: #0066cc; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">Carica Wikipedia</button>
            <button type="button" id="wikipedia-clear-btn" style="padding: 8px 16px; background-color: #ccc; color: #333; border: none; border-radius: 4px; cursor: pointer;">Svuota</button>
        </div>
        <div id="wikipedia-search-status" class="muted" style="margin-top: 10px;"></div>
    </section>

    <div class="chart-builder-meta">
        <div class="chart-meta-pill">Sorgenti disponibili: <strong id="chart-source-count">0</strong></div>
        <div class="chart-meta-pill">Paragrafi disponibili: <strong id="chart-paragraph-count">0</strong></div>
    </div>

    <!-- Wikipedia Historical Data Section -->
    <section class="postgara-group-section" id="wikipedia-charts-section" style="margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #e5e5e5;">
        <div style="margin-bottom: 20px;">
            <h3>📊 Dati Storici Pilota (Wikipedia)</h3>
            <p class="muted">Statistiche storiche del pilota F1</p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px;">
            <div style="padding: 12px; border: 1px solid #ddd; border-radius: 4px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <strong>Vittorie per anno</strong>
                    <button type="button" onclick="wikipediaSaveChart('postgara-wikipedia-wins-by-year', 'Vittorie per anno')" style="padding: 4px 8px; font-size: 12px; background-color: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer;">💾 PNG</button>
                </div>
                <div id="postgara-wikipedia-wins-by-year" style="min-height: 200px;"></div>
                <div id="postgara-wikipedia-wins-by-year-status" class="muted" style="font-size: 12px; margin-top: 5px;"></div>
            </div>

            <div style="padding: 12px; border: 1px solid #ddd; border-radius: 4px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <strong>Vittorie per team</strong>
                    <button type="button" onclick="wikipediaSaveChart('postgara-wikipedia-wins-by-team', 'Vittorie per team')" style="padding: 4px 8px; font-size: 12px; background-color: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer;">💾 PNG</button>
                </div>
                <div id="postgara-wikipedia-wins-by-team" style="min-height: 200px;"></div>
                <div id="postgara-wikipedia-wins-by-team-status" class="muted" style="font-size: 12px; margin-top: 5px;"></div>
            </div>

            <div style="padding: 12px; border: 1px solid #ddd; border-radius: 4px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <strong>Podi per anno</strong>
                    <button type="button" onclick="wikipediaSaveChart('postgara-wikipedia-podiums-by-year', 'Podi per anno')" style="padding: 4px 8px; font-size: 12px; background-color: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer;">💾 PNG</button>
                </div>
                <div id="postgara-wikipedia-podiums-by-year" style="min-height: 200px;"></div>
                <div id="postgara-wikipedia-podiums-by-year-status" class="muted" style="font-size: 12px; margin-top: 5px;"></div>
            </div>

            <div style="padding: 12px; border: 1px solid #ddd; border-radius: 4px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <strong>Podi per team</strong>
                    <button type="button" onclick="wikipediaSaveChart('postgara-wikipedia-podiums-by-team', 'Podi per team')" style="padding: 4px 8px; font-size: 12px; background-color: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer;">💾 PNG</button>
                </div>
                <div id="postgara-wikipedia-podiums-by-team" style="min-height: 200px;"></div>
                <div id="postgara-wikipedia-podiums-by-team-status" class="muted" style="font-size: 12px; margin-top: 5px;"></div>
            </div>

            <div style="padding: 12px; border: 1px solid #ddd; border-radius: 4px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <strong>Pole position per anno</strong>
                    <button type="button" onclick="wikipediaSaveChart('postgara-wikipedia-poles-by-year', 'Pole position per anno')" style="padding: 4px 8px; font-size: 12px; background-color: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer;">💾 PNG</button>
                </div>
                <div id="postgara-wikipedia-poles-by-year" style="min-height: 200px;"></div>
                <div id="postgara-wikipedia-poles-by-year-status" class="muted" style="font-size: 12px; margin-top: 5px;"></div>
            </div>

            <div style="padding: 12px; border: 1px solid #ddd; border-radius: 4px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <strong>Pole position per team</strong>
                    <button type="button" onclick="wikipediaSaveChart('postgara-wikipedia-poles-by-team', 'Pole position per team')" style="padding: 4px 8px; font-size: 12px; background-color: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer;">💾 PNG</button>
                </div>
                <div id="postgara-wikipedia-poles-by-team" style="min-height: 200px;"></div>
                <div id="postgara-wikipedia-poles-by-team-status" class="muted" style="font-size: 12px; margin-top: 5px;"></div>
            </div>
        </div>
    </section>

    <div class="chart-builder-grid">
        <div class="module-b-panel">
            <h3>Nuovo grafico</h3>
            <input type="hidden" id="chart-editor-id">
            <label>Nome interno grafico
                <input type="text" id="chart-name" placeholder="es. Meteo gara Monaco">
            </label>
            <label>Sorgente dati
                <select id="chart-source"></select>
            </label>
            <label>Tipo grafico
                <select id="chart-type">
                    <option value="bar">Barre</option>
                    <option value="line">Linee</option>
                    <option value="pie">Torta</option>
                    <option value="doughnut">Ciambella</option>
                    <option value="polarArea">Area polare</option>
                </select>
            </label>
            <div id="chart-type-hint" class="muted chart-type-hint"></div>
            <label>Titolo grafico
                <input type="text" id="chart-title" placeholder="Titolo visibile nel grafico">
            </label>
            <label>Etichetta dataset
                <input type="text" id="chart-dataset-label" placeholder="es. Temperatura media">
            </label>
            <div class="chart-form-grid">
                <label>Asse X / Etichette
                    <select id="chart-x-field"></select>
                </label>
                <label>Asse Y / Valori
                    <select id="chart-y-field"></select>
                </label>
            </div>
            <div class="chart-form-grid">
                <label>Titolo asse X
                    <input type="text" id="chart-x-axis-label" placeholder="es. Sessione">
                </label>
                <label>Titolo asse Y
                    <input type="text" id="chart-y-axis-label" placeholder="es. Temperatura °C">
                </label>
            </div>
            <label>Colori
                <input type="text" id="chart-colors" placeholder="#cf0821, #0088ff, #ffaa00">
            </label>
            <label>Paragrafo destinazione
                <select id="chart-target-paragraph"></select>
            </label>
            <div class="form-actions-row">
                <button type="button" id="save-custom-chart">Salva grafico</button>
                <button type="button" id="reset-custom-chart">Nuovo</button>
            </div>
            <div id="chart-builder-status" class="muted"></div>
        </div>

        <div class="module-b-panel">
            <div class="module-b-section-head">
                <div>
                    <h3>Anteprima grafico</h3>
                    <span class="muted">L'anteprima usa i dati e i campi scelti nel form corrente.</span>
                </div>
            </div>
            <div class="custom-chart-preview-wrap custom-chart-preview-wrap-editor">
                <canvas id="chart-editor-preview" height="260"></canvas>
            </div>
        </div>

        <div class="module-b-panel chart-builder-saved-panel">
            <div class="module-b-section-head">
                <div>
                    <h3>Grafici salvati</h3>
                    <span class="muted">Ogni grafico può essere assegnato a un paragrafo del testo.</span>
                </div>
            </div>
            <div id="custom-chart-list" class="custom-chart-list"></div>
        </div>
    </div>

    <!-- Wikipedia Charts Image Export Script -->
    <script>
    (function() {
        const wikipediaChartIds = [
            'postgara-wikipedia-wins-by-year',
            'postgara-wikipedia-wins-by-team',
            'postgara-wikipedia-podiums-by-year',
            'postgara-wikipedia-podiums-by-team',
            'postgara-wikipedia-poles-by-year',
            'postgara-wikipedia-poles-by-team'
        ];

        const chartTitles = {
            'postgara-wikipedia-wins-by-year': 'Vittorie per anno',
            'postgara-wikipedia-wins-by-team': 'Vittorie per team',
            'postgara-wikipedia-podiums-by-year': 'Podi per anno',
            'postgara-wikipedia-podiums-by-team': 'Podi per team',
            'postgara-wikipedia-poles-by-year': 'Pole position per anno',
            'postgara-wikipedia-poles-by-team': 'Pole position per team'
        };

        // Salva il canvas come PNG via AJAX
        async function saveChartAsImage(chartId, title) {
            const container = document.getElementById(chartId);
            if (!container) {
                alert('Grafico non trovato');
                return;
            }

            const canvas = container.querySelector('canvas');
            if (!canvas) {
                alert('Canvas non trovato nel grafico');
                return;
            }

            // Converte canvas a base64 PNG
            const base64Image = canvas.toDataURL('image/png');
            const filename = chartId + '-' + Date.now() + '.png';

            // Chiamata AJAX al server
            try {
                const response = await fetch(ajaxurl || '/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'wikipedia_save_chart_image',
                        nonce: document.querySelector('input[name="postgara_nonce"]')?.value || postgara_nonce,
                        base64_image: base64Image,
                        title: title,
                        filename: filename
                    })
                });

                const data = await response.json();
                if (data.success) {
                    // Sostituisce il canvas con l'immagine salvata
                    const img = document.createElement('img');
                    img.src = data.url;
                    img.alt = title;
                    img.style.maxWidth = '100%';
                    img.style.height = 'auto';
                    img.style.borderRadius = '6px';
                    img.style.boxShadow = '0 2px 8px rgba(0,0,0,0.1)';

                    container.innerHTML = '';
                    container.appendChild(img);

                    const caption = document.createElement('figcaption');
                    caption.textContent = title;
                    caption.style.fontSize = '14px';
                    caption.style.color = '#666';
                    caption.style.marginTop = '10px';
                    container.parentElement.appendChild(caption);

                    const status = document.getElementById(chartId + '-status');
                    if (status) {
                        status.textContent = '✅ Salvato come PNG';
                        status.style.color = 'green';
                    }
                } else {
                    alert('Errore: ' + data.error);
                }
            } catch (error) {
                alert('Errore nella comunicazione con il server: ' + error.message);
            }
        }

        // Espone le funzioni globalmente
        window.wikipediaSaveChart = saveChartAsImage;

        // Auto-save all Wikipedia charts on page load (opzionale)
        document.addEventListener('DOMContentLoaded', function() {
            // Puoi aggiungere qui la logica per salvare automaticamente
            // o lasciare all'utente il bottone manuale
        });
    })();
    </script>
</section>
