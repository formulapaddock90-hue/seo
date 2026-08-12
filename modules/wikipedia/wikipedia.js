const entityData = JSON.parse(document.getElementById('entity-data').textContent);
const form = document.getElementById('career-form');
const searchInput = document.getElementById('driver-input');
const entityTypeInput = document.getElementById('entity-type');
const entityValueInput = document.getElementById('entity-value');
const entityWikiTitleInput = document.getElementById('entity-wiki-title');
const entityLabelInput = document.getElementById('entity-label');
const suggestionsBox = document.getElementById('driver-suggestions');
const statusBox = document.getElementById('status-box');
const resultCard = document.getElementById('result-card');
const resultEyebrow = document.getElementById('result-eyebrow');
const resultTitle = document.getElementById('result-title');
const bioSection = document.getElementById('bio-section');
const bioImage = document.getElementById('bio-image');
const bioExtract = document.getElementById('bio-extract');
const timelineSection = document.getElementById('timeline-section');
const chartsSection = document.getElementById('charts-section');
const timelineList = document.getElementById('timeline-list');

const chartDefinitions = {
    winsByYear: { color: 'rgba(239, 68, 68, 0.85)', type: 'bar', label: 'Vittorie' },
    winsByTeam: { color: 'rgba(249, 115, 22, 0.85)', type: 'bar', label: 'Vittorie' },
    podiumsByTeam: { color: 'rgba(59, 130, 246, 0.85)', type: 'bar', label: 'Podi' },
    podiumsByYear: { color: 'rgba(14, 165, 233, 0.85)', type: 'bar', label: 'Podi' },
    polesByTeam: { color: 'rgba(168, 85, 247, 0.85)', type: 'bar', label: 'Pole position' },
    polesByYear: { color: 'rgba(34, 197, 94, 0.85)', type: 'bar', label: 'Pole position' },
};

const chartInstances = {};
let filteredSuggestions = [];
let activeSuggestionIndex = -1;

function setStatus(message, isError = false) {
    statusBox.textContent = message;
    statusBox.style.color = isError ? '#fecaca' : '#94a3b8';
}

function normalizeValue(value) {
    return value.toLocaleLowerCase('it-IT').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

function escapeHtml(value) {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function getEntityBadgeText(entityType) {
    if (entityType === 'driver') {
        return 'Pilota';
    }
    if (entityType === 'team') {
        return 'Team';
    }
    if (entityType === 'teamPrincipal') {
        return 'Team principal';
    }
    if (entityType === 'circuit') {
        return 'Circuito';
    }
    return 'Voce F1';
}

function findEntityByValue(entityType, entityValue) {
    return entityData.find((entity) => entity.entityType === entityType && entity.entityValue === entityValue) || null;
}

function findEntityByLabel(label) {
    const normalizedLabel = normalizeValue(label.trim());
    return entityData.find((entity) => normalizeValue(entity.label) === normalizedLabel) || null;
}

function clearSelection() {
    entityTypeInput.value = '';
    entityValueInput.value = '';
    entityWikiTitleInput.value = '';
    entityLabelInput.value = '';
}

function hideSuggestions() {
    suggestionsBox.classList.add('hidden');
    suggestionsBox.innerHTML = '';
    filteredSuggestions = [];
    activeSuggestionIndex = -1;
}

function selectEntity(entity) {
    searchInput.value = entity.label;
    entityTypeInput.value = entity.entityType;
    entityValueInput.value = entity.entityValue;
    entityWikiTitleInput.value = entity.wikiTitle || entity.label;
    entityLabelInput.value = entity.label;
    hideSuggestions();
}

function buildSuggestionMarkup(label, query) {
    if (!query) {
        return escapeHtml(label);
    }

    const normalizedLabel = normalizeValue(label);
    const normalizedQuery = normalizeValue(query);
    const index = normalizedLabel.indexOf(normalizedQuery);

    if (index < 0) {
        return escapeHtml(label);
    }

    const start = label.slice(0, index);
    const match = label.slice(index, index + query.length);
    const end = label.slice(index + query.length);

    return `${escapeHtml(start)}<mark>${escapeHtml(match)}</mark>${escapeHtml(end)}`;
}

function renderSuggestions(matches, query) {
    if (!matches.length) {
        suggestionsBox.innerHTML = '<button type="button" class="suggestion-item suggestion-empty" disabled>Nessuna voce F1 trovata</button>';
        suggestionsBox.classList.remove('hidden');
        return;
    }

    suggestionsBox.innerHTML = matches
        .map((entity, index) => `
            <button
                type="button"
                class="suggestion-item${index === activeSuggestionIndex ? ' active' : ''}"
                data-entity-type="${entity.entityType}"
                data-entity-value="${entity.entityValue}"
            >
                <span class="suggestion-label">${buildSuggestionMarkup(entity.label, query)}</span>
                <small class="suggestion-meta">${escapeHtml(entity.meta || getEntityBadgeText(entity.entityType))}</small>
            </button>
        `)
        .join('');

    suggestionsBox.classList.remove('hidden');
}

function updateSuggestions() {
    const query = searchInput.value.trim();

    if (!query) {
        clearSelection();
        hideSuggestions();
        return;
    }

    const normalizedQuery = normalizeValue(query);
    filteredSuggestions = entityData
        .filter((entity) => {
            const haystack = `${entity.label} ${entity.meta || ''}`;
            return normalizeValue(haystack).includes(normalizedQuery);
        })
        .slice(0, 14);

    activeSuggestionIndex = -1;
    const exactMatch = findEntityByLabel(query);
    if (exactMatch) {
        selectEntity(exactMatch);
        return;
    }

    clearSelection();
    renderSuggestions(filteredSuggestions, query);
}

function moveActiveSuggestion(direction) {
    if (!filteredSuggestions.length) {
        return;
    }

    activeSuggestionIndex += direction;

    if (activeSuggestionIndex < 0) {
        activeSuggestionIndex = filteredSuggestions.length - 1;
    }

    if (activeSuggestionIndex >= filteredSuggestions.length) {
        activeSuggestionIndex = 0;
    }

    renderSuggestions(filteredSuggestions, searchInput.value.trim());
}

function renderTimeline(timeline) {
    timelineList.innerHTML = '';

    if (!timeline.length) {
        timelineList.innerHTML = '<article class="timeline-item timeline-empty"><div class="timeline-year">--</div><div class="timeline-rail"><div class="timeline-dot"></div></div><div class="timeline-card"><strong>Nessun dato disponibile</strong><span>La timeline non contiene elementi validi.</span></div></article>';
        return;
    }

    timeline.forEach((entry, index) => {
        const item = document.createElement('article');
        item.className = 'timeline-item';

        const year = document.createElement('div');
        year.className = 'timeline-year';
        year.textContent = entry.year;

        const rail = document.createElement('div');
        rail.className = 'timeline-rail';

        const dot = document.createElement('div');
        dot.className = 'timeline-dot';

        const card = document.createElement('div');
        card.className = 'timeline-card';

        const team = document.createElement('strong');
        team.textContent = entry.team || 'Team non disponibile';

        const badge = document.createElement('small');
        badge.textContent = entry.badge || `Stagione ${index + 1}`;

        card.appendChild(badge);
        card.appendChild(team);

        if (entry.result) {
            const result = document.createElement('span');
            result.textContent = entry.result;
            card.appendChild(result);
        }

        rail.appendChild(dot);
        item.appendChild(year);
        item.appendChild(rail);
        item.appendChild(card);
        timelineList.appendChild(item);
    });
}

function createOrUpdateChart(chartKey, series) {
    const config = chartDefinitions[chartKey];
    const canvas = document.getElementById(`${chartKey}-chart`);
    const emptyStateId = `${chartKey}-empty`;
    let emptyState = document.getElementById(emptyStateId);

    if (!emptyState) {
        emptyState = document.createElement('p');
        emptyState.id = emptyStateId;
        emptyState.className = 'chart-empty hidden';
        emptyState.textContent = 'Nessun dato disponibile per questo grafico.';
        canvas.parentElement.appendChild(emptyState);
    }

    if (chartInstances[chartKey]) {
        chartInstances[chartKey].destroy();
    }

    const labels = series?.labels || [];
    const data = series?.data || [];

    if (!labels.length || !data.length) {
        canvas.classList.add('hidden');
        emptyState.classList.remove('hidden');
        return;
    }

    canvas.classList.remove('hidden');
    emptyState.classList.add('hidden');

    chartInstances[chartKey] = new Chart(canvas, {
        type: config.type,
        data: {
            labels,
            datasets: [
                {
                    label: config.label,
                    data,
                    backgroundColor: config.color,
                    borderColor: 'rgba(248, 250, 252, 0.18)',
                    borderWidth: 1,
                    borderRadius: 10,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false,
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#cbd5e1',
                        precision: 0,
                    },
                    grid: {
                        color: 'rgba(148, 163, 184, 0.12)',
                    },
                },
                x: {
                    ticks: {
                        color: '#cbd5e1',
                    },
                    grid: {
                        display: false,
                    },
                },
            },
        },
    });
}

function renderCharts(charts) {
    Object.keys(chartDefinitions).forEach((chartKey) => {
        createOrUpdateChart(chartKey, charts[chartKey] || { labels: [], data: [] });
    });
}

function renderBio(result) {
    bioSection.classList.remove('hidden');
    bioExtract.textContent = result.extract || 'Nessun riepilogo disponibile.';

    if (result.image) {
        bioImage.src = result.image;
        bioImage.alt = result.title;
        bioImage.classList.remove('hidden');
    } else {
        bioImage.classList.add('hidden');
        bioImage.removeAttribute('src');
    }
}

function notifyParentCharts(result) {
    try {
        if (!window.parent || window.parent === window) return;

        const charts = Object.entries(result?.charts || {}).reduce((carry, [chartKey, series]) => {
            if (!Array.isArray(series?.labels) || !Array.isArray(series?.data)) {
                return carry;
            }

            carry[chartKey] = {
                title: document.querySelector(`#${chartKey}-chart`)?.closest('.chart-card')?.querySelector('h4')?.textContent?.trim() || chartKey,
                labels: series.labels,
                data: series.data,
                datasetLabel: chartDefinitions[chartKey]?.label || 'Valore'
            };
            return carry;
        }, {});

        window.parent.postMessage({ type: 'wiki-chart-series', payload: { charts } }, window.location.origin);
    } catch {
    }
}

function renderResult(result) {
    resultEyebrow.textContent = getEntityBadgeText(result.entityType);
    resultTitle.textContent = result.title;
    bioSection.classList.add('hidden');

    if (result.mode === 'driver') {
        timelineSection.classList.remove('hidden');
        chartsSection.classList.remove('hidden');
        renderTimeline(result.timeline || []);
        renderCharts(result.charts || {});
    } else {
        timelineSection.classList.remove('hidden');
        chartsSection.classList.add('hidden');
        renderTimeline(result.timeline || []);
    }

    notifyParentCharts(result);
    resultCard.classList.remove('hidden');
}

async function handleSubmit(event) {
    event.preventDefault();

    const selectedEntity = entityTypeInput.value && entityValueInput.value
        ? findEntityByValue(entityTypeInput.value, entityValueInput.value)
        : findEntityByLabel(searchInput.value);

    if (!selectedEntity) {
        setStatus('Seleziona una voce F1 valida dai suggerimenti.', true);
        resultCard.classList.add('hidden');
        notifyParentCharts({ charts: {} });
        return;
    }

    selectEntity(selectedEntity);

    const submitButton = form.querySelector('button[type="submit"]');
    const formData = new FormData();
    formData.set('action', 'searchEntity');
    formData.set('entityType', selectedEntity.entityType);
    formData.set('entityValue', selectedEntity.entityValue);
    formData.set('wikiTitle', selectedEntity.wikiTitle || selectedEntity.label);
    formData.set('label', selectedEntity.label);

    if (Array.isArray(selectedEntity.constructorTimeline)) {
        selectedEntity.constructorTimeline.forEach((period, index) => {
            formData.append(`constructorTimeline[${index}][constructorId]`, period.constructorId || '');
            formData.append(`constructorTimeline[${index}][from]`, String(period.from || ''));
            formData.append(`constructorTimeline[${index}][to]`, String(period.to || ''));
        });
    }

    setStatus('Caricamento scheda F1 in corso...');
    submitButton.disabled = true;
    hideSuggestions();

    try {
        const response = await fetch('wikipedia.php', {
            method: 'POST',
            body: formData,
        });

        const payload = await response.json();

        if (!response.ok || !payload.success) {
            throw new Error(payload.message || 'Errore durante il caricamento della scheda.');
        }

        renderResult(payload.result);
        setStatus('Scheda caricata con successo.');
    } catch (error) {
        resultCard.classList.add('hidden');
        notifyParentCharts({ charts: {} });
        setStatus(error.message || 'Errore imprevisto.', true);
    } finally {
        submitButton.disabled = false;
    }
}

searchInput.addEventListener('input', updateSuggestions);
searchInput.addEventListener('focus', updateSuggestions);
searchInput.addEventListener('keydown', (event) => {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        moveActiveSuggestion(1);
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        moveActiveSuggestion(-1);
    }

    if (event.key === 'Enter' && activeSuggestionIndex >= 0 && filteredSuggestions[activeSuggestionIndex]) {
        event.preventDefault();
        selectEntity(filteredSuggestions[activeSuggestionIndex]);
    }

    if (event.key === 'Escape') {
        hideSuggestions();
    }
});

document.addEventListener('click', (event) => {
    const suggestionButton = event.target.closest('.suggestion-item[data-entity-type][data-entity-value]');
    if (suggestionButton) {
        const selectedEntity = findEntityByValue(suggestionButton.dataset.entityType, suggestionButton.dataset.entityValue);
        if (selectedEntity) {
            selectEntity(selectedEntity);
        }
        return;
    }

    if (!event.target.closest('.autocomplete')) {
        hideSuggestions();
    }
});

form.addEventListener('submit', handleSubmit);
setStatus('Scrivi il nome di un pilota, team, team principal o circuito F1 e scegli un suggerimento.');
