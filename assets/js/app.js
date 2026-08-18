const charts = {};
window.charts = charts;

const state = {
    sitemapCategories: [],
    sitemapPages: [],
    sitemapPostTypes: [],
    internalLinks: [],
    mediaImages: [],
    mediaCategories: [],
    wpPages: [],
    articles: [],
    selectedArticleId: '',
    meetings: [],
    circuitRows: [],
    pirelliRows: [],
    sessionResultRows: [],
    wikipediaChartSeries: {},
    customCharts: [],
    paragraphChartMap: {},
    browserFolders: [],
    browserFiles: [],
    browserSelectedFolder: '',
    browserCurrentSubfolder: '',
    h2ImageMap: {},
    reviewH2Titles: [],
    uploadJsonFiles: [],
    imgPickerTargetH2: null,
    imgPickerFolderKey: '',
    imgPickerMode: 'h2',
    postgaraPickerTeamIdx: null,
    postgaraUploadsSubfolder: '',
    uploadFolderPending: false,
    uploadFolderSelected: '',
    uploadFolderCurrentSubfolder: '',
    settingsSites: [],
    autoSaveTimer: null,
    lastAutoSaveSignature: '',
    autoPlacementEnabled: false,
    isHydratingArticle: false,
    wpUrlMap: {},
    wpMediaIdMap: {}
};

window.state = state;

const clientConsoleLogState = {
    queue: [],
    flushTimer: 0,
    installed: false
};

function safeStringifyForLog(value) {
    if (value instanceof Error) {
        return value.stack || value.message || String(value);
    }
    if (typeof value === 'string') return value;
    try {
        return JSON.stringify(value);
    } catch {
        return String(value);
    }
}

async function flushClientConsoleLogs() {
    if (!clientConsoleLogState.queue.length) return;
    if (location.hostname.includes('github.io') || location.protocol === 'file:') {
        clientConsoleLogState.queue = [];
        return;
    }

    const entries = clientConsoleLogState.queue.splice(0, clientConsoleLogState.queue.length);
    try {
        await fetch('api/client-console-log.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ entries })
        });
    } catch {
    }
}

function enqueueClientConsoleLog(level, message, stack = '') {
    clientConsoleLogState.queue.push({
        ts: new Date().toISOString(),
        level,
        message,
        stack,
        page: window.location.href
    });

    window.clearTimeout(clientConsoleLogState.flushTimer);
    clientConsoleLogState.flushTimer = window.setTimeout(() => {
        flushClientConsoleLogs();
    }, 500);
}

function installClientConsoleLogCapture() {
    if (clientConsoleLogState.installed) return;
    clientConsoleLogState.installed = true;

    const originalConsoleError = console.error.bind(console);
    console.error = (...args) => {
        enqueueClientConsoleLog('console.error', args.map(safeStringifyForLog).join(' | '));
        originalConsoleError(...args);
    };

    window.addEventListener('error', (event) => {
        const location = `${event.filename || 'unknown'}:${event.lineno || 0}:${event.colno || 0}`;
        enqueueClientConsoleLog('window.error', `${event.message || 'Errore JS'} @ ${location}`, event.error?.stack || '');
    });

    window.addEventListener('unhandledrejection', (event) => {
        const reason = safeStringifyForLog(event.reason);
        enqueueClientConsoleLog('unhandledrejection', reason);
    });

    window.addEventListener('beforeunload', () => {
        if (!clientConsoleLogState.queue.length) return;
        const payload = JSON.stringify({ entries: clientConsoleLogState.queue });
        navigator.sendBeacon?.('api/client-console-log.php', new Blob([payload], { type: 'application/json' }));
    });

    enqueueClientConsoleLog('load', 'Init cattura console completata');
}

installClientConsoleLogCapture();

function setupTabs() {
    document.querySelectorAll('.tabs button').forEach(btn => {
        btn.addEventListener('click', () => activateTab(btn.dataset.tab));
    });
}

function activateTab(tabId) {
    document.querySelectorAll('.tabs button').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(s => s.classList.remove('active'));

    const btn = document.querySelector(`.tabs button[data-tab="${tabId}"]`);
    if (btn) btn.classList.add('active');

    const section = document.getElementById(tabId);
    if (section) section.classList.add('active');

    if (tabId === 'mod-chart' && typeof window.refreshCustomChartsModule === 'function') {
        window.refreshCustomChartsModule();
    }
    if (tabId === 'mod-b' && typeof window.refreshH2SelectionState === 'function') {
        window.refreshH2SelectionState();
    }

    updatePreview();
}

const isStaticEnv = location.hostname.includes('github.io') || location.protocol === 'file:';

async function apiGet(path) {
    if (isStaticEnv && (path.includes('.php') || path.endsWith('.php'))) {
        return { success: true, categories: [], links: [], pages: [], post_types: [], images: [] };
    }
    const res = await fetch(path);
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        if (res.status === 401) {
            alert("La sessione è scaduta o non è più valida. Verrai reindirizzato alla pagina di login.");
            window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
            return new Promise(() => {});
        }
        const parts = [data.error, data.message, data.details]
            .filter(Boolean)
            .map(v => String(v).trim())
            .filter((v, i, arr) => arr.indexOf(v) === i);
        throw new Error(parts.join(' - ') || `Errore richiesta API (${res.status})`);
    }
    return data;
}

async function apiPost(path, payload) {
    if (isStaticEnv && (path.includes('.php') || path.endsWith('.php'))) {
        return { success: true, message: 'Operazione eseguita in modalità statica client-side.' };
    }
    const res = await fetch(path, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        if (res.status === 401) {
            alert("La sessione è scaduta o non è più valida. Verrai reindirizzato alla pagina di login.");
            window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
            return new Promise(() => {});
        }
        const detailStr = data.details
            ? (typeof data.details === 'object'
                ? (data.details.message || JSON.stringify(data.details))
                : String(data.details))
            : '';
        const parts = [data.error, data.message, detailStr]
            .map(v => (v || '').trim())
            .filter((v, i, arr) => v && arr.indexOf(v) === i);
        throw new Error(parts.join(' — ') || `Errore richiesta API (${res.status})`);
    }
    return data;
}

function upsertChart(id, config) {
    const canvas = document.getElementById(id);
    if (!canvas) return;
    if (charts[id]) charts[id].destroy();
    charts[id] = new Chart(canvas, config);
}

function mediaFolderKey(folder, category) {
    return `${folder}/${category}`;
}

function createEmptySite(index = 0) {
    return {
        key: `site${Date.now()}${index}`,
        label: '',
        url: '',
        username: '',
        application_password: '',
        default_category: '',
        default_parent_page: ''
    };
}

function normalizeSiteKey(value = '', index = 0) {
    const normalized = String(value || '')
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '');
    return normalized || `site_${index + 1}`;
}

async function withBtnLock(btn, loadingLabel, fn) {
    if (!btn || btn.disabled) return;
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = loadingLabel;
    try {
        await fn();
    } finally {
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

function queueAutoSave() {
    if (state.isHydratingArticle) return;
    window.clearTimeout(state.autoSaveTimer);
    state.autoSaveTimer = window.setTimeout(async () => {
        try {
            await saveGeneratedArticle({ silent: true, autosave: true });
        } catch {
        }
    }, 900);
}

function renderWpSiteOptions() {
    const select = document.getElementById('wp-site');
    if (!select) return;

    const previous = select.value;
    const sites = state.settingsSites.filter(site => (site.key || '').trim() !== '');
    select.innerHTML = '';

    sites.forEach(site => {
        const opt = document.createElement('option');
        opt.value = site.key;
        opt.textContent = site.label || site.key;
        select.appendChild(opt);
    });

    if (!sites.length) {
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = 'Nessun sito configurato';
        select.appendChild(opt);
    }

    if (previous && sites.some(site => site.key === previous)) {
        select.value = previous;
    }
}

function renderSettingsSites() {
    const wrap = document.getElementById('settings-sites-list');
    if (!wrap) return;
    wrap.innerHTML = '';

    if (!state.settingsSites.length) {
        const empty = document.createElement('div');
        empty.className = 'folder-item muted';
        empty.textContent = 'Nessun sito configurato.';
        wrap.appendChild(empty);
        return;
    }

    state.settingsSites.forEach((site, index) => {
        const card = document.createElement('div');
        card.className = 'settings-site-card';
        card.dataset.index = String(index);
        card.innerHTML = `
            <div class="settings-site-grid">
                <label>Chiave sito<input type="text" data-field="key" value="${escapeHtml(site.key || '')}" placeholder="es. formulapaddock"></label>
                <label>Nome sito<input type="text" data-field="label" value="${escapeHtml(site.label || '')}" placeholder="Formula Paddock"></label>
                <label>URL WordPress<input type="text" data-field="url" value="${escapeHtml(site.url || '')}" placeholder="https://www.esempio.it"></label>
                <label>Username<input type="text" data-field="username" value="${escapeHtml(site.username || '')}" placeholder="utente"></label>
                <label>Password applicazione<input type="password" data-field="application_password" value="${escapeHtml(site.application_password || '')}" placeholder="xxxx xxxx xxxx xxxx"></label>
                <label>Categoria default<input type="text" data-field="default_category" value="${escapeHtml(site.default_category || '')}" placeholder="Categoria"></label>
                <label>Pagina genitore default<input type="text" data-field="default_parent_page" value="${escapeHtml(site.default_parent_page || '')}" placeholder="ID pagina"></label>
            </div>
            <div class="settings-site-actions">
                <button type="button" class="btn-sm settings-site-toggle-password">👁 Password</button>
                <button type="button" class="btn-sm btn-sm-danger settings-site-remove">Rimuovi</button>
            </div>
        `;

        card.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', () => {
                const field = input.dataset.field;
                if (!field) return;
                state.settingsSites[index][field] = input.value;
            });
        });

        card.querySelector('.settings-site-toggle-password')?.addEventListener('click', () => {
            const input = card.querySelector('input[data-field="application_password"]');
            if (input) input.type = input.type === 'password' ? 'text' : 'password';
        });

        card.querySelector('.settings-site-remove')?.addEventListener('click', () => {
            state.settingsSites.splice(index, 1);
            renderSettingsSites();
        });

        wrap.appendChild(card);
    });
}

async function loadSettingsData() {
    const savedKey = localStorage.getItem('gemini_api_key') || '';
    const savedUrl = localStorage.getItem('gemini_model_url') || 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent';
    
    const notice = document.getElementById('gemini-key-notice');
    if (notice) notice.style.display = savedKey ? 'none' : '';

    const input = document.getElementById('settings-gemini-key');
    const urlInput = document.getElementById('settings-gemini-url');
    if (input && savedKey) input.value = savedKey;
    if (urlInput && savedUrl) urlInput.value = savedUrl;

    if (isStaticEnv) {
        return { gemini_api_key: savedKey, gemini_model_url: savedUrl, sites: state.settingsSites || [] };
    }
    
    try {
        const data = await apiGet('api/settings.php');
        state.settingsSites = Array.isArray(data.sites) ? data.sites : [];
        renderSettingsSites();
        renderWpSiteOptions();
        const finalKey = savedKey || data.gemini_api_key || '';
        const finalUrl = savedUrl || data.gemini_model_url || '';
        if (input && finalKey) input.value = finalKey;
        if (urlInput && finalUrl) urlInput.value = finalUrl;
        if (notice) notice.style.display = finalKey ? 'none' : '';
        return {
            ...data,
            gemini_api_key: finalKey,
            gemini_model_url: finalUrl
        };
    } catch {
        return { gemini_api_key: savedKey, gemini_model_url: savedUrl };
    }
}

function escapeHtml(value) {
    return (value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function plainToArticleHtml(text) {
    const lines = (text || '').split('\n');
    const html = [];

    lines.forEach(raw => {
        const line = raw.trim();
        if (!line) return;

        if (line.startsWith('TITLE:')) {
            html.push(`<h1>${escapeHtml(line.replace('TITLE:', '').trim())}</h1>`);
            return;
        }
        if (line.startsWith('H1:')) {
            html.push(`<h1>${escapeHtml(line.replace('H1:', '').trim())}</h1>`);
            return;
        }
        if (line.startsWith('H2:')) {
            html.push(`<h2>${escapeHtml(line.replace('H2:', '').trim())}</h2>`);
            return;
        }
        if (line.startsWith('H3:')) {
            html.push(`<h3>${escapeHtml(line.replace('H3:', '').trim())}</h3>`);
            return;
        }

        html.push(`<p>${escapeHtml(line)}</p>`);
    });

    return html.join('\n');
}

function stripPageTags(html) {
    return html
        .replace(/<meta\b[^>]*>/gi, '')
        .replace(/<title\b[^>]*>[\s\S]*?<\/title>/gi, '')
        .replace(/<\/?(html|head|body)\b[^>]*>/gi, '')
        .trim();
}

function toArticleHtml(source) {
    const text = (source || '').trim();
    if (!text) return '';

    if (/<\s*(h1|h2|h3|p|div|article|section|ul|ol|img|table)\b/i.test(text)) {
        return stripPageTags(text);
    }

    return plainToArticleHtml(text);
}

async function preUploadImagesToWordPress() {
    const site = document.getElementById('wp-site')?.value;
    if (!site) return;

    const images = collectSelectedImages();
    const toUpload = images.filter(img => !(state.wpUrlMap && state.wpUrlMap[img.token]));
    if (!toUpload.length) return;

    const BATCH_SIZE = 2;
    let changed = false;

    for (let i = 0; i < toUpload.length; i += BATCH_SIZE) {
        const batch = toUpload.slice(i, i + BATCH_SIZE);
        try {
            const data = await apiPost('api/wordpress.php?action=upload_media_batch', {
                site,
                paths: batch.map(img => img.url)
            });

            const uploads = data?.uploads || {};
            batch.forEach(img => {
                const entry = uploads[img.url];
                if (entry && entry.url) {
                    state.wpUrlMap[img.token] = entry.url;
                    if (entry.id) state.wpMediaIdMap[img.token] = entry.id;
                    changed = true;
                }
            });
        } catch (e) {
            console.warn('Upload batch non riuscito:', e);
        }
    }

    if (changed) {
        updatePreview();
        queueAutoSave();
    }
}

function findMediaIdBySrc(src) {
    if (!src || !state.wpMediaIdMap) return 0;
    const entry = state.mediaImages.find(m => (state.wpUrlMap && state.wpUrlMap[m.token]) === src);
    return entry ? (state.wpMediaIdMap[entry.token] || 0) : 0;
}

function getFeaturedMediaId() {
    const images = collectSelectedImages();
    for (const img of images) {
        const id = state.wpMediaIdMap && state.wpMediaIdMap[img.token];
        if (id) return id;
    }
    return (window.postgara && window.postgara.firstWpMediaId) || 0;
}

function srcToImageBlock(src, alt) {
    const id = findMediaIdBySrc(src);
    const idPart = id ? `"id":${id},` : '';
    const classPart = id ? ` class="wp-image-${id}"` : '';
    const safeSrc = src.replace(/"/g, '&quot;');
    const safeAlt = (alt || '').replace(/"/g, '&quot;');
    return `<!-- wp:image {${idPart}"sizeSlug":"large","linkDestination":"media"} -->\n<figure class="wp-block-image size-large"><a href="${safeSrc}"><img src="${safeSrc}" alt="${safeAlt}"${classPart}/></a></figure>\n<!-- /wp:image -->`;
}

function nodeToGbBlock(node) {
    if (node.nodeType === Node.TEXT_NODE) {
        const t = node.textContent.trim();
        return t ? `<!-- wp:paragraph --><p>${t}</p><!-- /wp:paragraph -->` : '';
    }
    if (node.nodeType !== Node.ELEMENT_NODE) return '';

    const tag = node.tagName.toLowerCase();

    const hMatch = tag.match(/^h([1-6])$/);
    if (hMatch) {
        const level = hMatch[1];
        return `<!-- wp:heading {"level":${level}} -->\n<${tag} class="wp-block-heading">${node.innerHTML}</${tag}>\n<!-- /wp:heading -->`;
    }

    if (tag === 'p') {
        return `<!-- wp:paragraph -->\n${node.outerHTML}\n<!-- /wp:paragraph -->`;
    }

    if (tag === 'ul' || tag === 'ol') {
        const ordered = tag === 'ol';
        const itemsHtml = Array.from(node.querySelectorAll(':scope > li'))
            .map(li => `<!-- wp:list-item --><li>${li.innerHTML}</li><!-- /wp:list-item -->`)
            .join('\n');
        return `<!-- wp:list${ordered ? ' {"ordered":true}' : ''} -->\n<${tag} class="wp-block-list">\n${itemsHtml}\n</${tag}>\n<!-- /wp:list -->`;
    }

    if (tag === 'img') {
        return srcToImageBlock(node.getAttribute('src') || '', node.getAttribute('alt') || '');
    }

    if (tag === 'figure') {
        if (node.classList.contains('auto-chart-figure') || node.classList.contains('auto-media-figure')) {
            return `<!-- wp:html -->\n${node.outerHTML}\n<!-- /wp:html -->`;
        }
        const img = node.querySelector('img');
        if (img) return srcToImageBlock(img.getAttribute('src') || '', img.getAttribute('alt') || '');
    }

    if (tag === 'table') {
        return `<!-- wp:table -->\n<figure class="wp-block-table">${node.outerHTML}</figure>\n<!-- /wp:table -->`;
    }

    if (tag === 'article') {
        return Array.from(node.childNodes).map(nodeToGbBlock).filter(Boolean).join('\n\n');
    }

    return `<!-- wp:html -->\n${node.outerHTML}\n<!-- /wp:html -->`;
}

function toGutenbergHtml(html) {
    if (!html) return html;
    const parser = new DOMParser();
    const doc = parser.parseFromString(`<div id="gb-root">${html}</div>`, 'text/html');
    const root = doc.getElementById('gb-root');
    if (!root) return html;
    return Array.from(root.childNodes).map(nodeToGbBlock).filter(Boolean).join('\n\n');
}

function extractTitleFromArticle(source = '') {
    const text = source.trim();

    // Testo plain: cerca TITLE:, H1:, H2:
    const titleMatch = text.match(/^(?:TITLE|H1|H2):\s*(.+)/m);
    if (titleMatch) return titleMatch[1].trim();

    // HTML: cerca <h1> o <h2>
    const htmlMatch = text.match(/<h[12][^>]*>\s*(.*?)\s*<\/h[12]>/i);
    if (htmlMatch) return htmlMatch[1].replace(/<[^>]+>/g, '').trim();

    // Fallback: Prima riga non vuota
    const firstLine = text.split('\n').map(l => l.trim()).find(l => l.length > 0);
    return firstLine || '';
}

async function openSettingsModal() {
    const modal = document.getElementById('settings-modal');
    const input = document.getElementById('settings-gemini-key');
    const urlInput = document.getElementById('settings-gemini-url');
    const result = document.getElementById('settings-result');
    if (!modal) return;
    if (result) { result.className = ''; result.textContent = ''; }
    try {
        const data = await loadSettingsData();
        if (input) input.value = data.gemini_api_key || '';
        if (urlInput) urlInput.value = data.gemini_model_url || '';
    } catch {
        if (input) input.value = '';
        if (urlInput) urlInput.value = '';
    }
    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
    if (input) input.focus();
}

function closeSettingsModal() {
    const modal = document.getElementById('settings-modal');
    if (modal) { modal.classList.add('hidden'); modal.setAttribute('aria-hidden', 'true'); }
}

async function saveSettings() {
    const input = document.getElementById('settings-gemini-key');
    const urlInput = document.getElementById('settings-gemini-url');
    const result = document.getElementById('settings-result');
    const key = (input?.value || '').trim();
    const modelUrl = (urlInput?.value || '').trim();

    if (key) localStorage.setItem('gemini_api_key', key);
    if (modelUrl) localStorage.setItem('gemini_model_url', modelUrl);

    const payloadSites = state.settingsSites.map((site, index) => ({
        key: normalizeSiteKey(site.key, index),
        label: (site.label || '').trim(),
        url: (site.url || '').trim(),
        username: (site.username || '').trim(),
        application_password: (site.application_password || '').trim(),
        default_category: (site.default_category || '').trim(),
        default_parent_page: (site.default_parent_page || '').trim()
    })).filter(site => site.key && site.label && site.url);

    if (result) { result.className = ''; result.textContent = '⏳ Salvataggio...'; }

    if (isStaticEnv) {
        if (result) { result.className = 'notice notice-ok'; result.textContent = '✅ Impostazioni salvate nel browser.'; }
        const notice = document.getElementById('gemini-key-notice');
        if (notice) notice.style.display = key ? 'none' : '';
        setTimeout(closeSettingsModal, 1000);
        return;
    }

    try {
        const data = await apiPost('api/settings.php', {
            gemini_api_key: key,
            gemini_model_url: modelUrl,
            sites: payloadSites
        });
        if (data.ok || data.success) {
            state.settingsSites = Array.isArray(data.sites) ? data.sites : payloadSites;
            renderSettingsSites();
            renderWpSiteOptions();
            if (result) { result.className = 'notice notice-ok'; result.textContent = '✅ Impostazioni salvate.'; }
            const notice = document.getElementById('gemini-key-notice');
            if (notice) notice.style.display = key ? 'none' : '';
            try {
                await loadWordPressMeta();
            } catch {}
            setTimeout(closeSettingsModal, 1000);
        } else {
            if (result) { result.className = 'notice notice-warn'; result.textContent = data.error || 'Errore salvataggio.'; }
        }
    } catch (err) {
        if (result) { result.className = 'notice notice-warn'; result.textContent = `Errore: ${err.message}`; }
    }
}

async function testGeminiSettings() {
    const result = document.getElementById('settings-result');
    const input = document.getElementById('settings-gemini-key');
    const key = (input?.value || localStorage.getItem('gemini_api_key') || '').trim();

    if (result) {
        result.className = '';
        result.textContent = '⏳ Test connessione Gemini...';
    }

    if (isStaticEnv) {
        if (!key) {
            if (result) { result.className = 'notice notice-warn'; result.textContent = '❌ Inserisci una chiave API Gemini prima di eseguire il test.'; }
            return;
        }
        try {
            const testEndpoints = [
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent',
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent',
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-lite:generateContent'
            ];
            let success = false;
            let lastErr = '';

            for (const endpoint of testEndpoints) {
                try {
                    const testRes = await fetch(`${endpoint}?key=${key}`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ contents: [{ parts: [{ text: 'Ping test' }] }] })
                    });
                    if (!testRes.ok) continue;
                    const testData = await testRes.json();
                    if (testData?.candidates?.[0]) {
                        success = true;
                        break;
                    } else if (testData?.error?.message) {
                        lastErr = testData.error.message;
                    }
                } catch (e) {
                    lastErr = e.message;
                }
            }

            if (success) {
                if (result) { result.className = 'notice notice-ok'; result.textContent = '✅ Connessione Gemini valida!'; }
            } else {
                throw new Error(lastErr || 'Chiave non valida o quota superata.');
            }
        } catch (err) {
            if (result) { result.className = 'notice notice-warn'; result.textContent = `❌ Test Gemini fallito: ${err.message}`; }
        }
        return;
    }

    try {
        const data = await apiGet('api/settings.php?action=test_gemini');
        if (result) {
            result.className = 'notice notice-ok';
            result.textContent = `✅ ${data.message || 'Connessione Gemini valida'} (${data.model_url || ''})`;
        }
    } catch (err) {
        if (result) {
            result.className = 'notice notice-warn';
            result.textContent = `❌ Test Gemini fallito: ${err.message}`;
        }
    }
}

/* ── Sitemap browser (Modulo G - tab Aggiorna) ──────────────────────────── */
function openSitemapModal() {
    const modal = document.getElementById('sitemap-modal');
    if (!modal) return;
    const searchEl = document.getElementById('sitemap-search');
    if (searchEl) searchEl.value = '';
    renderSitemapList();
    modal.style.display = '';
    if (searchEl) searchEl.focus();
}

function renderSitemapList() {
    const listEl = document.getElementById('sitemap-list');
    if (!listEl) return;
    const query = (document.getElementById('sitemap-search')?.value || '').trim().toLowerCase();
    const pages = (state.wpPages || []);

    if (!pages.length) {
        listEl.innerHTML = '<div style="padding:16px;color:#666;text-align:center;">Nessun articolo. Seleziona un sito WordPress per caricarli.</div>';
        return;
    }

    const filtered = pages.filter(p => {
        if (!query) return true;
        return (p.title || '').toLowerCase().includes(query) || (p.link || '').toLowerCase().includes(query);
    });

    if (!filtered.length) {
        listEl.innerHTML = '<div style="padding:16px;color:#666;text-align:center;">Nessun risultato per "<em>' + escapeHtml(query) + '</em>".</div>';
        return;
    }

    listEl.innerHTML = filtered.slice(0, 300).map(p => {
        const title          = p.title || '(senza titolo)';
        const link           = p.link  || '';
        const type           = p.post_type || '';
        const postId         = p.id ? String(p.id) : '';
        const featuredMedia  = p.featured_media ? String(p.featured_media) : '';
        const date           = p.date || '';
        const badge  = (type && type !== 'post')
            ? `<span style="font-size:.7rem;color:#888;background:#222;padding:1px 5px;border-radius:3px;margin-left:5px;vertical-align:middle;">${escapeHtml(type)}</span>`
            : '';
        const imgBadge = featuredMedia
            ? `<span style="font-size:.7rem;color:#5a8;background:#122;padding:1px 5px;border-radius:3px;margin-left:5px;vertical-align:middle;" title="Ha immagine in evidenza (ID ${featuredMedia})">🖼️</span>`
            : '';
        return `<div class="sitemap-item" data-url="${escapeHtml(link).replace(/"/g,'&quot;')}" data-post-id="${escapeHtml(postId)}" data-post-type="${escapeHtml(type)}" data-featured-media="${escapeHtml(featuredMedia)}" data-date="${escapeHtml(date)}"
            style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #222;transition:background .1s;">
            <div style="color:#fff;font-weight:500;">${escapeHtml(title)}${badge}${imgBadge}</div>
            ${link ? `<div style="font-size:.73rem;color:#555;margin-top:2px;word-break:break-all;">${escapeHtml(link)}</div>` : ''}
        </div>`;
    }).join('');

    listEl.querySelectorAll('.sitemap-item').forEach(item => {
        item.addEventListener('mouseenter', () => { item.style.background = '#252525'; });
        item.addEventListener('mouseleave', () => { item.style.background = ''; });
        item.addEventListener('click', () => {
            const url           = item.dataset.url;
            const postId        = item.dataset.postId        || '';
            const featuredMedia = item.dataset.featuredMedia || '';
            const postType      = item.dataset.postType      || '';
            const date          = item.dataset.date          || '';
            const urlInput = document.getElementById('existing-article-url');
            if (urlInput) {
                urlInput.value = url;
                urlInput.dataset.postId        = postId;
                urlInput.dataset.featuredMedia = featuredMedia;
                urlInput.dataset.postType      = postType;   // tipo reale dell'articolo
            }
            // Pre-popola data pubblicazione (formato datetime-local: "YYYY-MM-DDTHH:MM")
            const dateInput = document.getElementById('wp-update-date');
            if (dateInput && date) {
                dateInput.value = date.substring(0, 16);
            }
            const modal = document.getElementById('sitemap-modal');
            if (modal) modal.style.display = 'none';
        });
    });
}

function attachListeners() {
    document.getElementById('live-session-check')?.addEventListener('change', (e) => {
        const container = document.getElementById('live-session-container');
        if (container) {
            if (e.target.checked) {
                container.classList.remove('hidden');
            } else {
                container.classList.add('hidden');
            }
        }
    });

    document.getElementById('analyze-keywords-ia')?.addEventListener('click', function() {
        const rawText = document.getElementById('raw-text')?.value || '';
        const out = document.getElementById('gemini-result');
        if (!rawText.trim()) {
            if (out) {
                out.className = 'notice notice-warn';
                out.textContent = 'Inserisci il testo grezzo nel Modulo A prima di analizzare le keyword.';
            }
            return;
        }
        withBtnLock(this, '⏳ Estrazione...', async () => {
            try {
                if (out) { out.className = ''; out.textContent = '⏳ Analisi testo e ricerca Trends in corso con IA...'; }
                const res = await apiPost('api/analyze-keywords.php', { raw_text: rawText });
                if (res.success) {
                    if (document.getElementById('trends-main-keyword')) {
                        document.getElementById('trends-main-keyword').value = res.main_keyword || '';
                    }
                    if (document.getElementById('trends-keywords')) {
                        document.getElementById('trends-keywords').value = (res.related_keywords || []).join('\n');
                    }
                    if (out) { out.className = 'notice notice-ok'; out.textContent = '✅ Keyword inserite automaticamente da IA!'; }
                } else {
                    throw new Error(res.message || 'Errore durante la generazione delle keyword.');
                }
            } catch (err) {
                if (out) { out.className = 'notice notice-warn'; out.textContent = `Errore IA: ${err.message}`; }
            }
        });
    });

    document.getElementById('b-load-inherit-btn')?.addEventListener('click', function() {
        const campionato = document.getElementById('campionato-select')?.value || 'f1';
        const circuito = document.getElementById('circuito-select-a')?.value || '';
        const statusEl = document.getElementById('mod-b-sync-status');
        
        withBtnLock(this, '⏳ Caricamento...', async () => {
            try {
                if (statusEl) statusEl.textContent = `Caricamento... Eredito Campionato: ${campionato.toUpperCase()}, Circuito: ${circuito || 'Nessuno'}`;
                
                await loadBrowserFolders();
                await loadMediaData();
                
                if (statusEl) {
                    statusEl.textContent = `✅ Dati caricati! Campionato: ${campionato.toUpperCase()}` + (circuito ? `, Circuito: ${circuito}` : '');
                    statusEl.style.color = '#27ae60';
                }
            } catch (err) {
                if (statusEl) {
                    statusEl.textContent = `❌ Errore caricamento: ${err.message}`;
                    statusEl.style.color = '#e74c3c';
                }
            }
        });
    });

    document.getElementById('selected-category')?.addEventListener('change', (e) => {
        const wpCategory = document.getElementById('wp-category-name');
        if (wpCategory) wpCategory.value = e.target.value || '';

        updatePreview();
        queueAutoSave();
    });

    document.getElementById('insert-wiki-preview')?.addEventListener('click', () => {
        const wikiHtml = buildWikipediaPreviewHtml();
        const out = document.getElementById('publish-result');
        const wikiOut = document.getElementById('wiki-insert-result');

        if (!wikiHtml) {
            const msg = 'Nessun contenuto Wikipedia disponibile. Carica una voce nel Modulo E e riprova.';
            if (out) out.textContent = msg;
            if (wikiOut) {
                wikiOut.className = 'notice notice-warn';
                wikiOut.textContent = msg;
            }
            return;
        }

        updatePreview();
        queueAutoSave();

        const okMsg = 'Modulo E inserito in Anteprima HTML formattata.';
        if (out) out.textContent = okMsg;
        if (wikiOut) {
            wikiOut.className = 'notice notice-ok';
            wikiOut.textContent = okMsg;
        }

        activateTab('mod-g');
    });

    document.getElementById('generate-seo')?.addEventListener('click', function() {
        const out = document.getElementById('gemini-result');
        if (out) { out.className = ''; out.textContent = '⏳ Generazione in corso...'; }
        withBtnLock(this, '⏳ Generazione in corso...', async () => {
            try {
                await generateSeoWithGemini();
            } catch (err) {
                if (out) { out.className = 'notice notice-warn'; out.textContent = `Errore Gemini: ${err.message}`; }
            }
        });
    });

    document.getElementById('load-circuit-temperature')?.addEventListener('click', function() {
        withBtnLock(this, '⏳ Caricamento...', async () => {
            try {
                await loadCircuitTemperature();
            } catch (err) {
                const out = document.getElementById('publish-result');
                if (out) out.textContent = `Errore temperature circuito: ${err.message}`;
            }
        });
    });

    document.getElementById('load-pirelli')?.addEventListener('click', function() {
        withBtnLock(this, '⏳ Caricamento...', async () => {
            try {
                await loadPirelli();
            } catch (err) {
                const out = document.getElementById('publish-result');
                if (out) out.textContent = `Errore Pirelli: ${err.message}`;
            }
        });
    });

    document.getElementById('load-standing-csv')?.addEventListener('click', function() {
        try {
            loadStandingCsv();
        } catch (err) {
            const status = document.getElementById('standing-status');
            if (status) {
                status.textContent = `Errore: ${err.message}`;
                status.style.backgroundColor = '#ffcdd2';
                status.style.display = 'block';
            }
        }
    });

    document.getElementById('load-session-result')?.addEventListener('click', function() {
        withBtnLock(this, '⏳ Caricamento...', async () => {
            try {
                await loadSessionResultLatest();
            } catch (err) {
                const out = document.getElementById('publish-result');
                if (out) out.textContent = `Errore classifica sessione: ${err.message}`;
            }
        });
    });

    document.getElementById('wp-site')?.addEventListener('change', async () => {
        try {
            await loadWordPressMeta();
        } catch (err) {
            const out = document.getElementById('publish-result');
            if (out) out.textContent = `Errore WordPress: ${err.message}`;
        }
    });

    document.getElementById('wp-parent-page')?.addEventListener('change', (e) => {
        const selected = e.target.selectedOptions[0];
        const postType = selected?.dataset?.postType || '';
        if (postType) {
            const ptSelect = document.getElementById('wp-post-type');
            if (ptSelect) ptSelect.value = postType;
        }
    });

    /* ── Modulo G tab switching ── */
    document.querySelectorAll('.g-mode-tab').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.g-mode-tab').forEach(b => {
                b.style.color = '#888';
                b.style.borderBottomColor = 'transparent';
                b.style.fontWeight = '';
                b.classList.remove('active');
            });
            btn.style.color = '#fff';
            btn.style.borderBottomColor = '#e10600';
            btn.style.fontWeight = '500';
            btn.classList.add('active');
            const panelId = btn.dataset.panel;
            ['g-panel-pubblica', 'g-panel-aggiorna'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.style.display = (id === panelId) ? '' : 'none';
            });
            // Pre-popola data/ora corrente nel campo Aggiorna se vuoto
            if (panelId === 'g-panel-aggiorna') {
                const dateInput = document.getElementById('wp-update-date');
                if (dateInput && !dateInput.value) {
                    const now = new Date();
                    dateInput.value = now.getFullYear() + '-'
                        + String(now.getMonth() + 1).padStart(2, '0') + '-'
                        + String(now.getDate()).padStart(2, '0') + 'T'
                        + String(now.getHours()).padStart(2, '0') + ':'
                        + String(now.getMinutes()).padStart(2, '0');
                }
            }
        });
    });

    /* ── Sitemap modal ── */
    document.getElementById('browse-sitemap-btn')?.addEventListener('click', openSitemapModal);
    document.getElementById('sitemap-modal-close')?.addEventListener('click', () => {
        const modal = document.getElementById('sitemap-modal');
        if (modal) modal.style.display = 'none';
    });
    document.getElementById('sitemap-modal')?.addEventListener('click', (e) => {
        if (e.target === document.getElementById('sitemap-modal')) {
            e.target.style.display = 'none';
        }
    });
    document.getElementById('sitemap-search')?.addEventListener('input', renderSitemapList);

    /* ── review-edit-from-update ── */
    document.getElementById('review-edit-from-update')?.addEventListener('click', () => activateTab('mod-a'));

    document.getElementById('review-publish')?.addEventListener('click', function() {
        withBtnLock(this, '⏳ Pubblicazione in corso...', async () => {
            try {
                await preUploadImagesToWordPress();
                const originalContent = buildReviewHtml();
                const result = await publishReviewArticle();

                if (result.content && originalContent) {
                    const parser = new DOMParser();
                    const origImgs = Array.from(parser.parseFromString(originalContent, 'text/html').querySelectorAll('img'));
                    const newImgs = Array.from(parser.parseFromString(result.content, 'text/html').querySelectorAll('img'));
                    let changed = false;
                    origImgs.forEach((origImg, i) => {
                        const oldSrc = origImg.getAttribute('src') || '';
                        const newSrc = newImgs[i]?.getAttribute('src') || '';
                        if (oldSrc && newSrc && oldSrc !== newSrc) {
                            const entry = state.mediaImages.find(m => m.url === oldSrc);
                            if (entry) {
                                state.wpUrlMap[entry.token] = newSrc;
                                changed = true;
                            }
                        }
                    });
                    if (changed) {
                        updatePreview();
                        queueAutoSave();
                    }
                }

                const out = document.getElementById('publish-result');
                const publishedStatus = String(result.status || '').toLowerCase();
                const publishedId = Number(result.id || 0);
                const siteSeoApplied = Boolean(result?.siteseo?.applied);
                const siteSeoError = String(result?.siteseo?.error || '').trim();
                const siteSeoMethod = String(result?.siteseo?.method || '').trim();

                if (publishedStatus === 'draft' && publishedId > 0) {
                    const editUrl = `https://www.formulapaddock.it/wp-admin/post.php?post=${publishedId}&action=edit`;
                    if (out) {
                        const siteSeoMethodMsg = siteSeoMethod ? ` Metodo: ${siteSeoMethod}.` : '';
                        const siteSeoMsg = siteSeoApplied
                            ? ` SiteSEO applicato.${siteSeoMethodMsg}`
                            : (siteSeoError ? ` SiteSEO non applicato: ${siteSeoError}${siteSeoMethodMsg}` : ` SiteSEO non applicato.${siteSeoMethodMsg}`);
                        out.textContent = `Bozza salvata con successo: ${editUrl}.${siteSeoMsg}`;
                    }
                    window.location.href = editUrl;
                    return;
                }

                if (result.link) {
                    if (out) {
                        const siteSeoMethodMsg = siteSeoMethod ? ` Metodo: ${siteSeoMethod}.` : '';
                        const siteSeoMsg = siteSeoApplied
                            ? ` SiteSEO applicato.${siteSeoMethodMsg}`
                            : (siteSeoError ? ` SiteSEO non applicato: ${siteSeoError}${siteSeoMethodMsg}` : ` SiteSEO non applicato.${siteSeoMethodMsg}`);
                        out.textContent = `Pubblicato con successo: ${result.link}.${siteSeoMsg}`;
                    }
                    const category = (document.getElementById('wp-category-name')?.value || '').trim();
                    const socialUrl = `social/index.php?url=${encodeURIComponent(result.link)}${category ? '&category=' + encodeURIComponent(category) : ''}`;
                    window.location.href = socialUrl;
                } else {
                    if (out) {
                        const resultMsg = publishedId > 0 
                            ? `Pubblicazione completata (ID ${publishedId})` 
                            : `Pubblicazione completata con successo${publishedStatus ? ` - Stato: ${publishedStatus}` : ''}`;
                        const siteSeoMethodMsg = siteSeoMethod ? ` Metodo: ${siteSeoMethod}.` : '';
                        const siteSeoMsg = siteSeoApplied
                            ? ` - SiteSEO applicato.${siteSeoMethodMsg}`
                            : (siteSeoError ? ` - SiteSEO non applicato: ${siteSeoError}${siteSeoMethodMsg}` : ` - SiteSEO non applicato.${siteSeoMethodMsg}`);
                        out.textContent = `${resultMsg}${siteSeoMsg}`;
                    }
                }
            } catch (err) {
                const out = document.getElementById('publish-result');
                if (out) out.textContent = `Errore pubblicazione: ${err.message}`;
            }
        });
    });

    document.getElementById('review-edit')?.addEventListener('click', () => activateTab('mod-a'));
    document.getElementById('review-update')?.addEventListener('click', function() {
        withBtnLock(this, '⏳ Aggiornamento in corso...', async () => {
            try {
                await preUploadImagesToWordPress();
                const result = await updateExistingArticle();

                const out             = document.getElementById('publish-result');
                const siteSeoApplied  = Boolean(result?.siteseo?.applied);
                const siteSeoError    = String(result?.siteseo?.error || '').trim();
                const siteSeoMethod   = String(result?.siteseo?.method || '').trim();
                const siteSeoMethodMsg = siteSeoMethod ? ` Metodo: ${siteSeoMethod}.` : '';
                const siteSeoMsg = siteSeoApplied
                    ? ` SiteSEO aggiornato.${siteSeoMethodMsg}`
                    : (siteSeoError
                        ? ` SiteSEO non aggiornato: ${siteSeoError}${siteSeoMethodMsg}`
                        : ` SiteSEO non aggiornato.${siteSeoMethodMsg}`);

                if (out) out.textContent = `✅ Articolo aggiornato: ${result.link || '(ID ' + (result.id || '?') + ')'}${siteSeoMsg}`;

            } catch (err) {
                const out = document.getElementById('publish-result');
                if (out) out.textContent = `Errore aggiornamento: ${err.message}`;
            }
        });
    });

    document.getElementById('review-view-code')?.addEventListener('click', () => {
        document.getElementById('review-content')?.classList.remove('hidden');
        document.getElementById('review-preview-pane')?.classList.add('hidden');
        document.getElementById('review-view-code')?.classList.add('active');
        document.getElementById('review-view-fmt')?.classList.remove('active');
    });

    document.getElementById('review-view-fmt')?.addEventListener('click', () => {
        const pane = document.getElementById('review-preview-pane');
        if (pane) pane.innerHTML = buildReviewHtml() || '<em>Nessun contenuto da visualizzare.</em>';
        document.getElementById('review-content')?.classList.add('hidden');
        pane?.classList.remove('hidden');
        document.getElementById('review-view-fmt')?.classList.add('active');
        document.getElementById('review-view-code')?.classList.remove('active');
    });

    document.getElementById('open-folder-modal')?.addEventListener('click', openFolderModal);
    document.getElementById('folder-modal-close')?.addEventListener('click', closeFolderModal);
    document.getElementById('folder-modal-use-current')?.addEventListener('click', async () => {
        try {
            const folder = state.browserCurrentSubfolder ? `uploads/${state.browserCurrentSubfolder}` : 'uploads';
            await selectBrowserFolder(folder);
            closeFolderModal();
        } catch (err) {
            const out = document.getElementById('publish-result');
            if (out) out.textContent = `Errore selezione cartella: ${err.message}`;
        }
    });

    document.getElementById('b-upload-images-btn')?.addEventListener('click', async () => {
        if (isStaticEnv) {
            document.getElementById('b-upload-images-input')?.click();
            return;
        }
        try {
            await openUploadFolderModal();
        } catch (err) {
            document.getElementById('b-upload-images-input')?.click();
        }
    });

    document.getElementById('b-download-photo-wall-btn')?.addEventListener('click', function () {
        if (isStaticEnv) {
            const out = document.getElementById('b-photo-wall-status');
            if (out) {
                out.className = 'notice notice-warn';
                out.classList.remove('hidden');
                out.textContent = '⚠️ Funzione server non disponibile su GitHub Pages. Usa "Carica immagini da PC/telefono" per aggiungere le tue foto.';
            }
            return;
        }
        withBtnLock(this, '⏳ Download Photo Wall...', async () => {
            try {
                const res = await fetch('api/photo-wall-sync.php', { method: 'POST' });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.ok) {
                    throw new Error(data.message || 'Sync Photo Wall non riuscita');
                }

                await loadMediaData();

                const out = document.getElementById('publish-result');
                if (out) {
                    const cacheHits = Number(data?.metrics?.cache_hits || 0);
                    const downloaded = Number(data?.metrics?.image_download_success || 0);
                    out.textContent = `Photo Wall aggiornato: ${downloaded} nuove immagini, ${cacheHits} già in cache.`;
                }
            } catch (err) {
                const out = document.getElementById('publish-result');
                if (out) out.textContent = `Errore download Photo Wall: ${err.message}`;
            }
        });
    });

    document.getElementById('b-upload-images-input')?.addEventListener('change', async (e) => {
        const input = e.target;
        const files = input?.files;
        if (!files || !files.length) return;
        const btn = document.getElementById('b-upload-images-btn');
        withBtnLock(btn, '⏳ Upload in corso...', async () => {
            try {
                await uploadImagesToSelectedFolder(files);
            } catch (err) {
                const out = document.getElementById('publish-result');
                if (out) out.textContent = `Errore upload immagini: ${err.message}`;
            } finally {
                if (input) input.value = '';
            }
        });
    });

    document.getElementById('img-picker-close')?.addEventListener('click', closeImgPickerModal);

    document.getElementById('upload-folder-cancel')?.addEventListener('click', closeUploadFolderModal);

    document.getElementById('upload-folder-confirm')?.addEventListener('click', async () => {
        try {
            const selectedFolder = state.uploadFolderSelected;
            if (!selectedFolder) {
                throw new Error('Nessuna cartella selezionata');
            }

            state.browserSelectedFolder = selectedFolder;
            closeUploadFolderModal();

            document.getElementById('b-upload-images-input')?.click();
        } catch (err) {
            const out = document.getElementById('publish-result');
            if (out) out.textContent = `Errore selezione cartella: ${err.message}`;
        }
    });

    document.getElementById('upload-new-folder-btn')?.addEventListener('click', async function () {
        const input = document.getElementById('upload-new-folder-name');
        const folderName = input?.value || '';

        withBtnLock(this, '⏳ Creazione...', async () => {
            try {
                await createNewUploadFolder(folderName);
            } catch (err) {
                const resultDiv = document.getElementById('upload-new-folder-result');
                if (resultDiv) {
                    resultDiv.textContent = `❌ Errore: ${err.message}`;
                    resultDiv.style.display = 'block';
                    resultDiv.className = 'notice notice-warn';
                }
            }
        });
    });

    document.getElementById('b-go-review')?.addEventListener('click', () => activateTab('mod-g'));
    document.getElementById('b-auto-place-media')?.addEventListener('click', async () => {
        const images = collectSelectedImages();
        if (!images.length) {
            updateAutoPlaceResult('Seleziona prima almeno un’immagine da associare ai titoli H2.', true);
            return;
        }
        state.autoPlacementEnabled = true;
        updatePreview();
        updateAutoPlaceResult('Immagini posizionate automaticamente dopo i paragrafi e grafici accodati in fondo all’articolo.');
        queueAutoSave();
    });

    document.getElementById('open-settings')?.addEventListener('click', openSettingsModal);
    document.getElementById('settings-close')?.addEventListener('click', closeSettingsModal);
    document.getElementById('settings-save')?.addEventListener('click', function() {
        withBtnLock(this, '⏳ Salvataggio...', saveSettings);
    });
    document.getElementById('settings-test-gemini')?.addEventListener('click', function() {
        withBtnLock(this, '⏳ Test...', testGeminiSettings);
    });
    document.getElementById('settings-add-site')?.addEventListener('click', () => {
        state.settingsSites.push(createEmptySite(state.settingsSites.length));
        renderSettingsSites();
    });
    document.getElementById('settings-key-toggle')?.addEventListener('click', () => {
        const input = document.getElementById('settings-gemini-key');
        if (input) input.type = input.type === 'password' ? 'text' : 'password';
    });

    document.getElementById('open-json-import')?.addEventListener('click', async () => {
        try {
            await loadUploadJsonFiles();
            openJsonImportModal();
        } catch (err) {
            const out = document.getElementById('publish-result');
            if (out) out.textContent = `Errore elenco JSON upload: ${err.message}`;
        }
    });

    document.getElementById('open-article-modal')?.addEventListener('click', async () => {
        try {
            await loadArticlesList();
            openArticleModal();
        } catch (err) {
            const out = document.getElementById('publish-result');
            if (out) out.textContent = `Errore archivio articoli: ${err.message}`;
        }
    });
    document.getElementById('article-modal-close')?.addEventListener('click', closeArticleModal);
    document.getElementById('article-search')?.addEventListener('input', renderArticlesList);

    document.getElementById('json-import-close')?.addEventListener('click', closeJsonImportModal);
    document.getElementById('json-import-confirm')?.addEventListener('click', function() {
        withBtnLock(this, '⏳ Importazione...', async () => {
            try {
                await importSelectedJsonToRawText();
            } catch (err) {
                const out = document.getElementById('publish-result');
                if (out) out.textContent = `Errore import JSON: ${err.message}`;
            }
        });
    });

    ['raw-text', 'review-title', 'review-content'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('input', () => {
            if (id === 'review-content') {
                refreshH2SelectionState();
            }
            updatePreview();
            if (id !== 'raw-text') queueAutoSave();
        });
        el.addEventListener('change', () => {
            if (id === 'review-content') {
                refreshH2SelectionState();
            }
            updatePreview();
            if (id !== 'raw-text') queueAutoSave();
        });
    });

    document.getElementById('seo-generate-title')?.addEventListener('click', function() {
        withBtnLock(this, '⏳ Generazione...', async () => {
            const out = document.getElementById('publish-result');
            try {
                applySeoField('title');
                if (out) out.textContent = 'Titolo SEO generato.';
            } catch (err) {
                if (out) out.textContent = `Errore generazione titolo SEO: ${err.message}`;
            }
        });
    });

    document.getElementById('seo-generate-description')?.addEventListener('click', function() {
        withBtnLock(this, '⏳ Generazione...', async () => {
            const out = document.getElementById('publish-result');
            try {
                applySeoField('description');
                if (out) out.textContent = 'Meta description generata (max 160).';
            } catch (err) {
                if (out) out.textContent = `Errore generazione meta description: ${err.message}`;
            }
        });
    });

    document.getElementById('seo-generate-focus')?.addEventListener('click', function() {
        withBtnLock(this, '⏳ Generazione...', async () => {
            const out = document.getElementById('publish-result');
            try {
                applySeoField('focus');
                if (out) out.textContent = 'Focus keyword generata.';
            } catch (err) {
                if (out) out.textContent = `Errore generazione focus keyword: ${err.message}`;
            }
        });
    });

    document.getElementById('seo-generate-all')?.addEventListener('click', function() {
        withBtnLock(this, '⏳ Generazione...', async () => {
            const out = document.getElementById('publish-result');
            try {
                applyAllSeoFields();
                if (out) out.textContent = 'Campi SEO generati (titolo, meta description, focus keyword).';
            } catch (err) {
                if (out) out.textContent = `Errore generazione SEO: ${err.message}`;
            }
        });
    });
}

let reviewAreaInitialized = false;

function initializeReviewArea() {
    if (!reviewAreaInitialized) {
        attachListeners();
        reviewAreaInitialized = true;
    }

    refreshH2SelectionState();
    updatePreview();
    loadWordPressMeta().catch(err => console.error('loadWordPressMeta:', err));
}

document.addEventListener('DOMContentLoaded', () => {
    const safeRun = (fn) => {
        try {
            const result = fn();
            if (result && typeof result.catch === 'function') {
                result.catch(err => console.error('Init async error:', err));
            }
        } catch (err) {
            console.error('Init error:', err);
        }
    };

    safeRun(() => setupTabs());
    // Inizializzo Post Gara subito, così i box team sono sempre visibili
    safeRun(() => initializePostGara());

    safeRun(() => loadSettingsData());
    safeRun(() => loadSitemapData());
    safeRun(() => loadUploadJsonFiles());
    safeRun(() => loadCircuits());
    safeRun(() => initializeReviewArea());
    safeRun(() => initializeModuleB());
});

