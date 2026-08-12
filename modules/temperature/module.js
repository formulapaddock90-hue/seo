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

    if (typeof window.refreshCustomChartsModule === 'function') {
        window.refreshCustomChartsModule();
    }
    updatePreview();
}

