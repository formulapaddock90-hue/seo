(() => {
    const DEFAULT_COLORS = ['#cf0821', '#0088ff', '#ffaa00', '#10b981', '#8b5cf6', '#f97316'];
    const chartStore = window.charts || (window.charts = {});

    window.upsertChart = function upsertChart(id, config) {
        const canvas = document.getElementById(id);
        if (!canvas) return null;
        if (chartStore[id]) {
            chartStore[id].destroy();
        }
        chartStore[id] = new Chart(canvas, config);
        return chartStore[id];
    };

    window.destroyChart = function destroyChart(id) {
        if (!chartStore[id]) return;
        chartStore[id].destroy();
        delete chartStore[id];
    };

    function getChartState() {
        const currentState = window.state;
        if (!currentState) {
            throw new Error('Stato applicativo non inizializzato.');
        }
        if (!Array.isArray(currentState.customCharts)) currentState.customCharts = [];
        if (!currentState.paragraphChartMap || typeof currentState.paragraphChartMap !== 'object') currentState.paragraphChartMap = {};
        if (!currentState.wikipediaChartSeries || typeof currentState.wikipediaChartSeries !== 'object') currentState.wikipediaChartSeries = {};
        return currentState;
    }

    function setBuilderStatus(message) {
        const status = document.getElementById('chart-builder-status');
        if (status) status.textContent = message || '';
    }

    function normalizeNumber(value) {
        if (typeof value === 'number') return Number.isFinite(value) ? value : null;
        const normalized = String(value ?? '').trim().replace(/,/g, '.');
        if (normalized === '') return null;
        const parsed = Number(normalized);
        return Number.isFinite(parsed) ? parsed : null;
    }

    function parseColors(value) {
        const colors = String(value || '')
            .split(',')
            .map(color => color.trim())
            .filter(Boolean);
        return colors.length ? colors : DEFAULT_COLORS;
    }

    function getChartSources() {
        const currentState = getChartState();
        const sources = [];

        if (Array.isArray(currentState.sessionResultRows) && currentState.sessionResultRows.length) {
            sources.push({
                key: 'openf1_session_results',
                label: 'OpenF1 · Classifica sessione (deprecated)',
                defaultTitle: 'Classifica sessione OpenF1',
                defaultX: 'driver_name',
                defaultY: 'points',
                xFields: [
                    { key: 'position', label: 'Posizione' },
                    { key: 'driver_name', label: 'Pilota' },
                    { key: 'team_name', label: 'Team' }
                ],
                yFields: [
                    { key: 'points', label: 'Punti' },
                    { key: 'position', label: 'Posizione' }
                ],
                rows: currentState.sessionResultRows
            });
        }

        if (Array.isArray(currentState.circuitRows) && currentState.circuitRows.length) {
            sources.push({
                key: 'meteo_openf1',
                label: 'Meteo · Temperature circuito OpenF1',
                defaultTitle: 'Andamento meteo circuito',
                defaultX: 'session_name',
                defaultY: 'avg_track_temperature',
                xFields: [
                    { key: 'session_name', label: 'Sessione' },
                    { key: 'session_key', label: 'Chiave sessione' }
                ],
                yFields: [
                    { key: 'min_track_temperature', label: 'Min track (°C)' },
                    { key: 'avg_track_temperature', label: 'Avg track (°C)' },
                    { key: 'max_track_temperature', label: 'Max track (°C)' }
                ],
                rows: currentState.circuitRows
            });
        }

        if (Array.isArray(currentState.pirelliRows) && currentState.pirelliRows.length) {
            sources.push({
                key: 'pirelli_compounds',
                label: 'Pirelli · Compound gara',
                defaultTitle: 'Compound Pirelli',
                defaultX: 'compound',
                defaultY: 'max_stint_laps',
                xFields: [
                    { key: 'compound', label: 'Compound' }
                ],
                yFields: [
                    { key: 'best_lap', label: 'Miglior giro' },
                    { key: 'max_stint_laps', label: 'Durata max stint' }
                ],
                rows: currentState.pirelliRows
            });
        }

        Object.entries(currentState.wikipediaChartSeries || {}).forEach(([chartKey, chart]) => {
            const rows = Array.isArray(chart?.labels)
                ? chart.labels.map((label, index) => ({
                    label,
                    value: Array.isArray(chart.data) ? chart.data[index] : null
                }))
                : [];

            if (!rows.length) return;

            sources.push({
                key: `wikipedia_${chartKey}`,
                label: `Wikipedia · ${chart.title || chartKey}`,
                defaultTitle: chart.title || 'Grafico Wikipedia',
                defaultX: 'label',
                defaultY: 'value',
                xFields: [{ key: 'label', label: 'Etichetta' }],
                yFields: [{ key: 'value', label: chart.datasetLabel || 'Valore' }],
                rows
            });
        });

        return sources;
    }

    function getSourceByKey(sourceKey) {
        return getChartSources().find(source => source.key === sourceKey) || null;
    }

    function getParagraphOptions() {
        const articleHtml = toArticleHtml(document.getElementById('review-content')?.value || '');
        if (!articleHtml) return [];

        const parser = new DOMParser();
        const doc = parser.parseFromString(`<div id="chart-root">${articleHtml}</div>`, 'text/html');
        return Array.from(doc.querySelectorAll('#chart-root p')).map((paragraph, index) => {
            const text = (paragraph.textContent || '').replace(/\s+/g, ' ').trim();
            return {
                key: String(index),
                label: `Paragrafo ${index + 1}${text ? ` · ${text.slice(0, 90)}${text.length > 90 ? '…' : ''}` : ''}`
            };
        });
    }

    function updateCounters() {
        const sourceCount = document.getElementById('chart-source-count');
        const paragraphCount = document.getElementById('chart-paragraph-count');
        if (sourceCount) sourceCount.textContent = String(getChartSources().length);
        if (paragraphCount) paragraphCount.textContent = String(getParagraphOptions().length);
    }

    function updateChartTypeUi() {
        const chartType = document.getElementById('chart-type')?.value || 'bar';
        const xAxis = document.getElementById('chart-x-axis-label');
        const yAxis = document.getElementById('chart-y-axis-label');
        const hint = document.getElementById('chart-type-hint');
        const usesCartesianAxes = chartType === 'bar' || chartType === 'line';

        if (xAxis) xAxis.disabled = !usesCartesianAxes;
        if (yAxis) yAxis.disabled = !usesCartesianAxes;
        if (hint) {
            hint.textContent = usesCartesianAxes
                ? 'Per barre e linee puoi personalizzare asse X e asse Y.'
                : 'Per torta, ciambella e area polare vengono usate etichette e valori senza assi cartesiani.';
        }
    }

    function renderSourceOptions() {
        const sourceSelect = document.getElementById('chart-source');
        if (!sourceSelect) return;

        const previous = sourceSelect.value;
        const sources = getChartSources();
        sourceSelect.innerHTML = '';

        if (!sources.length) {
            sourceSelect.innerHTML = '<option value="">Carica dati dai moduli prima di creare un grafico</option>';
            renderFieldOptions();
            updateCounters();
            renderEditorPreview();
            return;
        }

        sources.forEach(source => {
            const option = document.createElement('option');
            option.value = source.key;
            option.textContent = source.label;
            sourceSelect.appendChild(option);
        });

        sourceSelect.value = sources.some(source => source.key === previous) ? previous : sources[0].key;
        renderFieldOptions();
        updateCounters();
    }

    function renderFieldOptions() {
        const source = getSourceByKey(document.getElementById('chart-source')?.value || '');
        const xSelect = document.getElementById('chart-x-field');
        const ySelect = document.getElementById('chart-y-field');
        if (!xSelect || !ySelect) return;

        xSelect.innerHTML = '';
        ySelect.innerHTML = '';

        if (!source) {
            xSelect.innerHTML = '<option value="">Nessun campo disponibile</option>';
            ySelect.innerHTML = '<option value="">Nessun campo disponibile</option>';
            renderEditorPreview();
            return;
        }

        source.xFields.forEach(field => {
            const option = document.createElement('option');
            option.value = field.key;
            option.textContent = field.label;
            xSelect.appendChild(option);
        });

        source.yFields.forEach(field => {
            const option = document.createElement('option');
            option.value = field.key;
            option.textContent = field.label;
            ySelect.appendChild(option);
        });

        xSelect.value = source.defaultX || source.xFields[0]?.key || '';
        ySelect.value = source.defaultY || source.yFields[0]?.key || '';

        const title = document.getElementById('chart-title');
        if (title && !title.value.trim()) title.value = source.defaultTitle || '';
        const datasetLabel = document.getElementById('chart-dataset-label');
        if (datasetLabel && !datasetLabel.value.trim()) datasetLabel.value = source.yFields.find(field => field.key === ySelect.value)?.label || '';
        const xAxis = document.getElementById('chart-x-axis-label');
        if (xAxis && !xAxis.value.trim()) xAxis.value = source.xFields.find(field => field.key === xSelect.value)?.label || '';
        const yAxis = document.getElementById('chart-y-axis-label');
        if (yAxis && !yAxis.value.trim()) yAxis.value = source.yFields.find(field => field.key === ySelect.value)?.label || '';

        updateChartTypeUi();
        renderEditorPreview();
    }

    function renderParagraphOptions() {
        const paragraphSelect = document.getElementById('chart-target-paragraph');
        if (!paragraphSelect) return;

        const previous = paragraphSelect.value;
        const paragraphs = getParagraphOptions();
        paragraphSelect.innerHTML = '<option value="">Nessun paragrafo selezionato</option>';

        paragraphs.forEach(paragraph => {
            const option = document.createElement('option');
            option.value = paragraph.key;
            option.textContent = paragraph.label;
            paragraphSelect.appendChild(option);
        });

        if (paragraphs.some(paragraph => paragraph.key === previous)) {
            paragraphSelect.value = previous;
        }

        updateCounters();
    }

    function buildChartPayloadFromForm() {
        const source = getSourceByKey(document.getElementById('chart-source')?.value || '');
        if (!source) {
            throw new Error('Nessuna sorgente dati disponibile per il grafico.');
        }

        const chartType = document.getElementById('chart-type')?.value || 'bar';
        const xField = document.getElementById('chart-x-field')?.value || source.defaultX;
        const yField = document.getElementById('chart-y-field')?.value || source.defaultY;
        if (!xField || !yField) {
            throw new Error('Seleziona i campi X e Y del grafico.');
        }

        return {
            id: document.getElementById('chart-editor-id')?.value || '',
            name: (document.getElementById('chart-name')?.value || '').trim(),
            sourceKey: source.key,
            chartType,
            xField,
            yField,
            title: (document.getElementById('chart-title')?.value || '').trim() || source.defaultTitle,
            datasetLabel: (document.getElementById('chart-dataset-label')?.value || '').trim() || yField,
            xAxisLabel: (document.getElementById('chart-x-axis-label')?.value || '').trim(),
            yAxisLabel: (document.getElementById('chart-y-axis-label')?.value || '').trim(),
            colors: parseColors(document.getElementById('chart-colors')?.value || ''),
            targetParagraph: document.getElementById('chart-target-paragraph')?.value || ''
        };
    }

    function buildChartModel(input) {
        const source = getSourceByKey(input.sourceKey);
        if (!source) {
            throw new Error('La sorgente dati del grafico non è disponibile.');
        }

        const rows = source.rows
            .map(row => {
                const label = row[input.xField];
                const numericValue = normalizeNumber(row[input.yField]);
                return { label, numericValue };
            })
            .filter(row => row.label !== undefined && row.label !== null && String(row.label).trim() !== '' && row.numericValue !== null);

        if (!rows.length) {
            throw new Error('La sorgente selezionata non contiene dati validi per i campi scelti.');
        }

        return {
            labels: rows.map(row => String(row.label)),
            values: rows.map(row => row.numericValue),
            source
        };
    }

    function buildChartConfig(chartDef, options = {}) {
        const { forExport = false } = options;
        const model = buildChartModel(chartDef);
        const colors = Array.isArray(chartDef.colors) && chartDef.colors.length ? chartDef.colors : DEFAULT_COLORS;
        const baseColor = colors[0] || DEFAULT_COLORS[0];
        const repeatedColors = model.labels.map((_, index) => colors[index % colors.length]);
        const type = chartDef.chartType || 'bar';
        const commonOptions = {
            responsive: !forExport,
            maintainAspectRatio: false,
            animation: false,
            plugins: {
                legend: { display: !['bar', 'line'].includes(type) },
                title: {
                    display: Boolean(chartDef.title),
                    text: chartDef.title || ''
                }
            }
        };

        const dataset = {
            label: chartDef.datasetLabel || model.source.defaultTitle || 'Dataset',
            data: model.values
        };

        if (type === 'line') {
            Object.assign(dataset, {
                borderColor: baseColor,
                backgroundColor: `${baseColor}33`,
                tension: 0.25,
                fill: false
            });
        } else if (['pie', 'doughnut', 'polarArea'].includes(type)) {
            Object.assign(dataset, {
                backgroundColor: repeatedColors,
                borderColor: repeatedColors,
                borderWidth: 1
            });
        } else {
            Object.assign(dataset, {
                backgroundColor: repeatedColors,
                borderColor: repeatedColors,
                borderWidth: 1,
                borderRadius: 6
            });
        }

        const config = {
            type,
            data: {
                labels: model.labels,
                datasets: [dataset]
            },
            options: commonOptions
        };

        if (type === 'bar' || type === 'line') {
            config.options.scales = {
                x: {
                    title: {
                        display: Boolean(chartDef.xAxisLabel),
                        text: chartDef.xAxisLabel || ''
                    },
                    ticks: {
                        autoSkip: false,
                        maxRotation: 55,
                        minRotation: 20
                    }
                },
                y: {
                    beginAtZero: true,
                    title: {
                        display: Boolean(chartDef.yAxisLabel),
                        text: chartDef.yAxisLabel || ''
                    }
                }
            };
        }

        return config;
    }

    function renderEditorPreview() {
        const previewId = 'chart-editor-preview';
        try {
            const payload = buildChartPayloadFromForm();
            upsertChart(previewId, buildChartConfig(payload));
        } catch (error) {
            destroyChart(previewId);
            setBuilderStatus(error.message || 'Anteprima grafico non disponibile.');
        }
    }

    function exportChartAsImage(chartDef) {
        const canvas = document.createElement('canvas');
        canvas.width = 960;
        canvas.height = 420;
        canvas.style.position = 'fixed';
        canvas.style.left = '-9999px';
        canvas.style.top = '-9999px';
        document.body.appendChild(canvas);

        let image = '';
        let instance = null;
        try {
            instance = new Chart(canvas, buildChartConfig(chartDef, { forExport: true }));
            image = instance.toBase64Image();
        } catch {
            image = '';
        } finally {
            if (instance) instance.destroy();
            canvas.remove();
        }

        return image;
    }

    function getAssignedParagraphForChart(chartId) {
        const entry = Object.entries(getChartState().paragraphChartMap).find(([, value]) => value === chartId);
        return entry ? entry[0] : '';
    }

    function getParagraphLabel(paragraphKey) {
        return getParagraphOptions().find(paragraph => paragraph.key === paragraphKey)?.label || 'Paragrafo non disponibile';
    }

    function renderSavedCharts() {
        const wrap = document.getElementById('custom-chart-list');
        if (!wrap) return;
        wrap.innerHTML = '';

        const charts = getChartState().customCharts;
        if (!charts.length) {
            wrap.innerHTML = '<div class="folder-item muted">Nessun grafico salvato.</div>';
            return;
        }

        charts.forEach(chart => {
            const card = document.createElement('div');
            card.className = 'custom-chart-card';
            card.innerHTML = `
                <div class="custom-chart-card-head">
                    <div>
                        <strong>${escapeHtml(chart.name || chart.title || 'Grafico senza nome')}</strong>
                        <div class="muted">${escapeHtml(getSourceByKey(chart.sourceKey)?.label || chart.sourceKey)} · ${escapeHtml(chart.chartType)}</div>
                        <div class="muted">${escapeHtml(getParagraphLabel(getAssignedParagraphForChart(chart.id)))}</div>
                    </div>
                    <div class="custom-chart-actions">
                        <button type="button" class="btn-sm" data-action="edit">Modifica</button>
                        <button type="button" class="btn-sm btn-sm-danger" data-action="delete">Elimina</button>
                    </div>
                </div>
                <div class="custom-chart-preview-wrap">
                    <canvas id="custom-chart-canvas-${chart.id}" height="220"></canvas>
                </div>
            `;

            card.querySelector('[data-action="edit"]')?.addEventListener('click', () => loadChartIntoForm(chart.id));
            card.querySelector('[data-action="delete"]')?.addEventListener('click', () => deleteCustomChart(chart.id));
            wrap.appendChild(card);

            try {
                upsertChart(`custom-chart-canvas-${chart.id}`, buildChartConfig(chart));
            } catch (error) {
                setBuilderStatus(`Errore rendering grafico ${chart.name || chart.title}: ${error.message}`);
            }
        });
    }

    function resetChartForm() {
        const sourceSelect = document.getElementById('chart-source');
        document.getElementById('chart-editor-id').value = '';
        document.getElementById('chart-name').value = '';
        document.getElementById('chart-title').value = '';
        document.getElementById('chart-dataset-label').value = '';
        document.getElementById('chart-x-axis-label').value = '';
        document.getElementById('chart-y-axis-label').value = '';
        document.getElementById('chart-colors').value = DEFAULT_COLORS.join(', ');
        document.getElementById('chart-type').value = 'bar';
        renderSourceOptions();
        renderParagraphOptions();
        if (sourceSelect && sourceSelect.value) renderFieldOptions();
        updateChartTypeUi();
        renderEditorPreview();
        setBuilderStatus('Compila i campi e salva il grafico.');
    }

    function loadChartIntoForm(chartId) {
        const chart = getChartState().customCharts.find(entry => entry.id === chartId);
        if (!chart) return;

        renderSourceOptions();
        renderParagraphOptions();
        document.getElementById('chart-editor-id').value = chart.id;
        document.getElementById('chart-name').value = chart.name || '';
        document.getElementById('chart-source').value = chart.sourceKey;
        document.getElementById('chart-type').value = chart.chartType || 'bar';
        renderFieldOptions();
        document.getElementById('chart-title').value = chart.title || '';
        document.getElementById('chart-dataset-label').value = chart.datasetLabel || '';
        document.getElementById('chart-x-field').value = chart.xField || '';
        document.getElementById('chart-y-field').value = chart.yField || '';
        document.getElementById('chart-x-axis-label').value = chart.xAxisLabel || '';
        document.getElementById('chart-y-axis-label').value = chart.yAxisLabel || '';
        document.getElementById('chart-colors').value = (chart.colors || DEFAULT_COLORS).join(', ');
        document.getElementById('chart-target-paragraph').value = getAssignedParagraphForChart(chart.id);
        updateChartTypeUi();
        renderEditorPreview();
        setBuilderStatus(`Modifica grafico: ${chart.name || chart.title}`);
    }

    function saveCustomChart() {
        const currentState = getChartState();
        const payload = buildChartPayloadFromForm();
        buildChartModel(payload);

        const normalized = {
            id: payload.id || `chart_${Date.now()}_${Math.random().toString(16).slice(2, 8)}`,
            name: payload.name,
            sourceKey: payload.sourceKey,
            chartType: payload.chartType,
            xField: payload.xField,
            yField: payload.yField,
            title: payload.title,
            datasetLabel: payload.datasetLabel,
            xAxisLabel: payload.xAxisLabel,
            yAxisLabel: payload.yAxisLabel,
            colors: payload.colors
        };

        const chartIndex = currentState.customCharts.findIndex(chart => chart.id === normalized.id);
        if (chartIndex >= 0) {
            currentState.customCharts[chartIndex] = normalized;
        } else {
            currentState.customCharts.push(normalized);
        }

        Object.keys(currentState.paragraphChartMap).forEach(paragraphKey => {
            if (currentState.paragraphChartMap[paragraphKey] === normalized.id || paragraphKey === payload.targetParagraph) {
                delete currentState.paragraphChartMap[paragraphKey];
            }
        });

        if (payload.targetParagraph !== '') {
            currentState.paragraphChartMap[payload.targetParagraph] = normalized.id;
        }

        renderSavedCharts();
        renderParagraphOptions();
        renderEditorPreview();
        updatePreview();
        queueAutoSave();
        setBuilderStatus(`Grafico salvato: ${normalized.name || normalized.title}`);
        document.getElementById('chart-editor-id').value = normalized.id;
    }

    function deleteCustomChart(chartId) {
        const currentState = getChartState();
        currentState.customCharts = currentState.customCharts.filter(chart => chart.id !== chartId);
        Object.keys(currentState.paragraphChartMap).forEach(paragraphKey => {
            if (currentState.paragraphChartMap[paragraphKey] === chartId) {
                delete currentState.paragraphChartMap[paragraphKey];
            }
        });
        destroyChart(`custom-chart-canvas-${chartId}`);
        renderSavedCharts();
        renderParagraphOptions();
        renderEditorPreview();
        updatePreview();
        queueAutoSave();
        setBuilderStatus('Grafico eliminato.');
        if (document.getElementById('chart-editor-id')?.value === chartId) {
            resetChartForm();
        }
    }

    window.applyCustomChartsToArticleRoot = function applyCustomChartsToArticleRoot(root, doc) {
        const currentState = getChartState();
        if (!root || !doc || !Array.isArray(currentState.customCharts) || !currentState.customCharts.length) return;

        const paragraphs = Array.from(root.querySelectorAll('p'));
        Object.entries(currentState.paragraphChartMap || {}).forEach(([paragraphKey, chartId]) => {
            const paragraph = paragraphs[Number(paragraphKey)];
            const chart = currentState.customCharts.find(entry => entry.id === chartId);
            if (!paragraph || !chart) return;

            const image = exportChartAsImage(chart);
            if (!image) return;

            const figure = doc.createElement('figure');
            figure.className = 'auto-chart-figure inline-chart-figure';
            figure.setAttribute('data-inline-chart', '1');
            figure.setAttribute('data-chart-id', chart.id);

            const caption = doc.createElement('figcaption');
            caption.textContent = chart.title || chart.name || 'Grafico';
            figure.appendChild(caption);

            const img = doc.createElement('img');
            img.src = image;
            img.alt = chart.title || chart.name || 'Grafico';
            figure.appendChild(img);

            paragraph.insertAdjacentElement('afterend', figure);
        });
    };

    window.refreshCustomChartsModule = function refreshCustomChartsModule() {
        renderSourceOptions();
        renderParagraphOptions();
        renderSavedCharts();
        updateChartTypeUi();
        renderEditorPreview();
    };

    window.addEventListener('message', (event) => {
        if (event.origin !== window.location.origin) return;
        if (!event.data || event.data.type !== 'wiki-chart-series') return;
        getChartState().wikipediaChartSeries = event.data.payload?.charts || {};
        if (typeof window.refreshCustomChartsModule === 'function') {
            window.refreshCustomChartsModule();
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        if (!document.getElementById('mod-chart')) return;

        document.getElementById('chart-source')?.addEventListener('change', () => {
            renderFieldOptions();
            renderEditorPreview();
        });
        document.getElementById('chart-type')?.addEventListener('change', () => {
            updateChartTypeUi();
            renderEditorPreview();
        });
        document.getElementById('chart-y-field')?.addEventListener('change', () => {
            const source = getSourceByKey(document.getElementById('chart-source')?.value || '');
            const field = source?.yFields.find(entry => entry.key === document.getElementById('chart-y-field')?.value);
            const datasetLabel = document.getElementById('chart-dataset-label');
            const yAxis = document.getElementById('chart-y-axis-label');
            if (field && datasetLabel && !datasetLabel.value.trim()) datasetLabel.value = field.label;
            if (field && yAxis && !yAxis.value.trim()) yAxis.value = field.label;
            renderEditorPreview();
        });
        document.getElementById('chart-x-field')?.addEventListener('change', () => {
            const source = getSourceByKey(document.getElementById('chart-source')?.value || '');
            const field = source?.xFields.find(entry => entry.key === document.getElementById('chart-x-field')?.value);
            const xAxis = document.getElementById('chart-x-axis-label');
            if (field && xAxis && !xAxis.value.trim()) xAxis.value = field.label;
            renderEditorPreview();
        });
        ['chart-name', 'chart-title', 'chart-dataset-label', 'chart-x-axis-label', 'chart-y-axis-label', 'chart-colors', 'chart-target-paragraph'].forEach(id => {
            document.getElementById(id)?.addEventListener('input', renderEditorPreview);
            document.getElementById(id)?.addEventListener('change', renderEditorPreview);
        });
        document.getElementById('save-custom-chart')?.addEventListener('click', () => {
            try {
                saveCustomChart();
            } catch (error) {
                setBuilderStatus(error.message || 'Errore salvataggio grafico.');
            }
        });
        document.getElementById('reset-custom-chart')?.addEventListener('click', resetChartForm);
        document.getElementById('review-content')?.addEventListener('input', renderParagraphOptions);
        document.getElementById('review-content')?.addEventListener('change', renderParagraphOptions);

        resetChartForm();
        renderSavedCharts();
    });
})();
