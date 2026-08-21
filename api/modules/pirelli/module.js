// ========== CIRCUIT TEMPERATURE FUNCTIONS ==========
function getSelectedMeetingYear() {
    const meetingKey = String(document.getElementById('circuit-select')?.value || '');
    const meeting = state.meetings.find(m => String(m.meeting_key || '') === meetingKey);
    return Number(meeting?.year || state.meetings[0]?.year || (new Date().getFullYear() - 1));
}

function buildCircuitPreviewHtml() {
    if (!state.circuitRows.length) return '';

    const selected = document.getElementById('circuit-select');
    const title = selected?.selectedOptions?.[0]?.textContent || 'Circuito';
    const year = getSelectedMeetingYear();

    const rows = state.circuitRows.map(r => {
        return `<tr><td>${escapeHtml(r.session_name || '')}</td><td>${r.min_track_temperature ?? '-'}</td><td>${r.avg_track_temperature ?? '-'}</td><td>${r.max_track_temperature ?? '-'}</td></tr>`;
    }).join('');

    return [
        '<section class="preview-appendix">',
        `<h2>Andamento temperature circuito ${year}: ${escapeHtml(title)}</h2>`,
        '<table>',
        '<thead><tr><th>Sessione</th><th>Min track (°C)</th><th>Avg track (°C)</th><th>Max track (°C)</th></tr></thead>',
        `<tbody>${rows}</tbody>`,
        '</table>',
        '</section>'
    ].join('');
}

async function loadCircuits() {
    const meetings = await apiGet('api/openf1.php?action=meetings_2026');
    state.meetings = Array.isArray(meetings) ? meetings : [];

    const circuitSelect = document.getElementById('circuit-select');
    if (circuitSelect) {
        circuitSelect.innerHTML = '<option value="">Seleziona circuito</option>';
        state.meetings.forEach(m => {
            const opt = document.createElement('option');
            opt.value = String(m.meeting_key || '');
            opt.textContent = `${m.meeting_name || 'Meeting'} (${m.country_name || 'N/A'})`;
            circuitSelect.appendChild(opt);
        });
    }

    const circuitSelectA = document.getElementById('circuito-select-a');
    if (circuitSelectA) {
        circuitSelectA.innerHTML = '<option value="">Seleziona circuito...</option>';
        state.meetings.forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.meeting_name || '';
            opt.textContent = `${m.meeting_name || 'Meeting'} (${m.country_name || 'N/A'})`;
            opt.dataset.key = String(m.meeting_key || '');
            circuitSelectA.appendChild(opt);
        });
    }

    const countrySet = Array.from(new Set(state.meetings.map(m => m.country_name).filter(Boolean))).sort((a, b) => a.localeCompare(b));
    const pirelli = document.getElementById('pirelli-country');
    if (pirelli) {
        pirelli.innerHTML = '<option value="">Seleziona nazione</option>';
        countrySet.forEach(country => {
            const opt = document.createElement('option');
            opt.value = country;
            opt.textContent = country;
            pirelli.appendChild(opt);
        });
    }
}

async function loadCircuitTemperature() {
    const meetingKey = Number(document.getElementById('circuit-select')?.value || 0);
    if (meetingKey <= 0) return;

    const rows = await apiGet(`api/openf1.php?action=weather_by_meeting&meeting_key=${meetingKey}`);
    state.circuitRows = Array.isArray(rows) ? rows : [];

    upsertChart('circuitTempChart', {
        type: 'line',
        data: {
            labels: state.circuitRows.map(r => r.session_name || `Sessione ${r.session_key}`),
            datasets: [
                {
                    label: 'Min track (°C)',
                    data: state.circuitRows.map(r => r.min_track_temperature),
                    borderColor: '#0088ff',
                    backgroundColor: 'rgba(0,136,255,0.2)',
                    tension: 0.25
                },
                {
                    label: 'Avg track (°C)',
                    data: state.circuitRows.map(r => r.avg_track_temperature),
                    borderColor: '#ffaa00',
                    backgroundColor: 'rgba(255,170,0,0.2)',
                    tension: 0.25
                },
                {
                    label: 'Max track (°C)',
                    data: state.circuitRows.map(r => r.max_track_temperature),
                    borderColor: '#cf0821',
                    backgroundColor: 'rgba(207,8,33,0.2)',
                    tension: 0.25
                }
            ]
        },
        options: {
            scales: {
                x: { ticks: { autoSkip: false, maxRotation: 65, minRotation: 35 } },
                y: { title: { display: true, text: 'Temperatura (°C)' } }
            }
        }
    });

    const tbody = document.querySelector('#circuit-temp-table tbody');
    if (tbody) {
        tbody.innerHTML = '';
        state.circuitRows.forEach(row => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${row.session_name || ''}</td><td>${row.min_track_temperature ?? '-'}</td><td>${row.avg_track_temperature ?? '-'}</td><td>${row.max_track_temperature ?? '-'}</td>`;
            tbody.appendChild(tr);
        });
    }

    // Connection to Modulo D (Grafici): update the inputs
    const chartSource = document.getElementById('chart-source');
    if (chartSource) {
        const meetingSelect = document.getElementById('circuit-select');
        const meetingName = meetingSelect?.selectedOptions?.[0]?.textContent || 'Circuito';
        
        // Update the form fields in Modulo D
        const chartNameInput = document.getElementById('chart-name');
        if (chartNameInput) chartNameInput.value = `Andamento meteo ${meetingName}`;
        
        const chartTitleInput = document.getElementById('chart-title');
        if (chartTitleInput) chartTitleInput.value = `Andamento temperature circuito: ${meetingName}`;
        
        const chartDatasetInput = document.getElementById('chart-dataset-label');
        if (chartDatasetInput) chartDatasetInput.value = 'Avg track (°C)';
        
        const chartXAxisInput = document.getElementById('chart-x-axis-label');
        if (chartXAxisInput) chartXAxisInput.value = 'Sessione';
        
        const chartYAxisInput = document.getElementById('chart-y-axis-label');
        if (chartYAxisInput) chartYAxisInput.value = 'Temperatura (°C)';
        
        // We select the meteo_openf1 source
        chartSource.value = 'meteo_openf1';
    }

    if (typeof window.refreshCustomChartsModule === 'function') {
        window.refreshCustomChartsModule();
        // Trigger field options load and preview update inside the chart module
        const chartXField = document.getElementById('chart-x-field');
        if (chartXField) chartXField.value = 'session_name';
        const chartYField = document.getElementById('chart-y-field');
        if (chartYField) chartYField.value = 'avg_track_temperature';
        window.refreshCustomChartsModule();
    }
    updatePreview();
}

// ========== PIRELLI FUNCTIONS ==========
function getPirelliReferenceYear() {
    return Number(state.meetings[0]?.year || (new Date().getFullYear() - 1));
}

function buildPirelliPreviewHtml() {
    if (!state.pirelliRows.length && !state.sessionResultRows.length) return '';

    const country = document.getElementById('pirelli-country')?.value || 'Pirelli';
    const year = getPirelliReferenceYear();
    const rows = state.pirelliRows.map(r => {
        return `<tr><td>${escapeHtml(r.compound || '')}</td><td>${r.best_lap ?? '-'}</td><td>${r.max_stint_laps ?? '-'}</td></tr>`;
    }).join('');

    const sessionRows = state.sessionResultRows.map(r => {
        return `<tr><td>${r.position || '-'}</td><td>${escapeHtml(r.driver_name || '')}</td><td>${escapeHtml(r.team_name || '')}</td><td>${escapeHtml(r.time || '') || '-'}</td><td>${r.points ?? '-'}</td></tr>`;
    }).join('');

    const sections = [];

    if (state.pirelliRows.length) {
        sections.push([
            '<section class="preview-appendix">',
            `<h2>Compound Pirelli ${year}: ${escapeHtml(country)}</h2>`,
            '<table>',
            '<thead><tr><th>Compound</th><th>Miglior giro</th><th>Durata max giri</th></tr></thead>',
            `<tbody>${rows}</tbody>`,
            '</table>',
            '</section>'
        ].join(''));
    }

    if (state.sessionResultRows.length) {
        const meta = state.sessionResultMeta || {};
        const title = [meta.session_name || 'Ultima sessione', meta.race_name || ''].filter(Boolean).join(' - ');
        sections.push([
            '<section class="preview-appendix">',
            `<h2>Classifica ${escapeHtml(title)}</h2>`,
            '<table>',
            '<thead><tr><th>Pos</th><th>Pilota</th><th>Team</th><th>Tempo</th><th>Punti</th></tr></thead>',
            `<tbody>${sessionRows}</tbody>`,
            '</table>',
            '</section>'
        ].join(''));
    }

    return sections.join('');
}

async function loadPirelli() {
    const country = document.getElementById('pirelli-country')?.value || '';
    if (!country) return;

    const compoundRows = await apiGet(`api/openf1.php?action=pirelli_infographic&country=${encodeURIComponent(country)}`);
    const rows = Array.isArray(compoundRows) ? compoundRows : [];
    state.pirelliRows = rows;

    const tbody = document.querySelector('#pirelli-table tbody');
    if (tbody) {
        tbody.innerHTML = '';
        rows.forEach(row => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${row.compound}</td><td>${row.best_lap ?? '-'}</td><td>${row.max_stint_laps ?? '-'}</td>`;
            tbody.appendChild(tr);
        });
    }

    upsertChart('pirelliCompoundChart', {
        type: 'bar',
        data: {
            labels: rows.map(r => r.compound),
            datasets: [
                {
                    label: 'Miglior giro (s)',
                    data: rows.map(r => r.best_lap),
                    backgroundColor: '#0088ff'
                },
                {
                    label: 'Durata max stint (giri)',
                    data: rows.map(r => r.max_stint_laps),
                    backgroundColor: '#ffaa00'
                }
            ]
        },
        options: {
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    if (typeof window.refreshCustomChartsModule === 'function') {
        window.refreshCustomChartsModule();
    }
    updatePreview();
}

async function loadSessionResultLatest() {
    try {
        const data = await apiGet('api/session-results-gdrive.php');
        if (data.success) {
            state.sessionResultRows = Array.isArray(data.rows) ? data.rows : [];
            state.sessionResultMeta = {
                session_name: data.session_name || 'Classifica',
                race_name: data.race_name || 'Da Google Drive',
                date: data.date || new Date().toLocaleDateString('it-IT'),
                source: data.source || 'Google Drive'
            };
        } else {
            throw new Error(data.error || 'Errore sconosciuto');
        }
    } catch (error) {
        console.error('Errore caricamento da Google Drive:', error);
        state.sessionResultRows = [];
        state.sessionResultMeta = { session_name: 'Errore', race_name: '', date: '', source: '' };
    }

    const tbody = document.querySelector('#session-result-table tbody');
    if (tbody) {
        tbody.innerHTML = '';
        state.sessionResultRows.forEach(row => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${row.position || '-'}</td><td>${row.number || '-'}</td><td>${escapeHtml(row.driver_name || '')}</td><td>${escapeHtml(row.team_name || '')}</td><td>${escapeHtml(row.time || '') || '-'}</td>`;
            tbody.appendChild(tr);
        });
    }

    if (typeof window.refreshCustomChartsModule === 'function') {
        window.refreshCustomChartsModule();
    }
    updatePreview();
}