function openArticleModal() {
    const modal = document.getElementById('article-modal');
    if (!modal) return;
    const search = document.getElementById('article-search');
    if (search) search.value = '';
    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
}

function closeArticleModal() {
    const modal = document.getElementById('article-modal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
}

function renderCategories() {
    const select = document.getElementById('selected-category');
    if (!select) return;

    const old = select.value;
    select.innerHTML = '<option value="">Seleziona categoria da sitemap</option>';
    state.sitemapCategories.forEach(cat => {
        const opt = document.createElement('option');
        opt.value = cat.label;
        opt.textContent = `${cat.label} (${cat.slug})`;
        select.appendChild(opt);
    });

    if (old) select.value = old;
    renderWpCategorySelect();
}

function renderWpCategorySelect() {
    const select = document.getElementById('wp-category-name');
    if (!select || select.tagName !== 'SELECT') return;

    const old = select.value;
    select.innerHTML = '<option value="">Seleziona categoria</option>';
    state.sitemapCategories.forEach(cat => {
        const opt = document.createElement('option');
        opt.value = cat.label;
        opt.textContent = `${cat.label} (${cat.slug})`;
        select.appendChild(opt);
    });

    if (old) select.value = old;
}

async function loadSitemapData() {
    const data = await apiGet('api/sitemaps.php');
    state.sitemapCategories = data.categories || [];
    state.internalLinks = data.links || [];
    state.sitemapPages = data.pages || [];
    state.sitemapPostTypes = data.post_types || [];
    renderCategories();
    renderSitemapPostTypes();
    renderSitemapParentPages();
}

function renderSitemapPostTypes() {
    const select = document.getElementById('wp-post-type');
    if (!select) return;

    const current = select.value;
    select.innerHTML = '';

    state.sitemapPostTypes.forEach(pt => {
        const opt = document.createElement('option');
        opt.value = pt;
        opt.textContent = pt.charAt(0).toUpperCase() + pt.slice(1).replace(/_/g, ' ');
        select.appendChild(opt);
    });

    if (current && [...select.options].some(o => o.value === current)) {
        select.value = current;
    }
}

function renderSitemapParentPages() {
    const select = document.getElementById('wp-parent-page');
    if (!select) return;

    const current = select.value;
    select.innerHTML = '<option value="">Nessuna</option>';

    state.sitemapPages.forEach(page => {
        const opt = document.createElement('option');
        opt.value = page.url;
        opt.dataset.postType = page.post_type;
        const date = page.lastmod ? ` (${page.lastmod.substring(0, 10)})` : '';
        opt.textContent = `${page.title}${date}`;
        select.appendChild(opt);
    });

    if (current && [...select.options].some(o => o.value === current)) {
        select.value = current;
    }
}

async function loadMediaData() {
    const data = await apiGet('api/media.php');
    state.mediaImages = data.images || [];
    state.mediaCategories = data.categories || [];

    renderH2Cards();
    updatePreview();
}

async function generateSeoWithGemini() {
    const form = document.getElementById('content-form');
    const formData = Object.fromEntries(new FormData(form).entries());

    const rawText = (formData.raw_text || '').trim();
    if (!rawText) {
        const out = document.getElementById('gemini-result');
        if (out) {
            out.className = 'notice notice-warn';
            out.textContent = 'Inserisci il testo grezzo nel Modulo A prima di generare la bozza SEO.';
        }
        return;
    }

    const trendsMainKeyword = (formData.trends_main_keyword || '').trim();
    const trendsKeywords = (formData.trends_keywords || '').trim();

    const payload = {
        trends_main_keyword: trendsMainKeyword,
        trends_keywords: trendsKeywords,
        main_keyword: trendsMainKeyword,
        related_keywords: trendsKeywords,
        long_tail: (formData.long_tail || '').trim(),
        raw_text: rawText,
        images: (formData.images || '').trim(),
        category_name: (formData.category_name || '').trim(),
        campionato: document.getElementById('campionato-select')?.value || 'f1',
        circuito: document.getElementById('circuito-select-a')?.value || '',
        is_live_session: document.getElementById('live-session-check')?.checked ? 1 : 0,
        live_session_name: document.getElementById('live-session-name')?.value || '',
        internal_links: Array.isArray(state.internalLinks) ? state.internalLinks : []
    };

    const data = await apiPost('api/gemini-generate.php', payload);
    const articleRaw = (data.draft_html || data.draft_text || '').trim();

    if (!articleRaw) {
        const out = document.getElementById('gemini-result');
        if (out) { out.className = 'notice notice-warn'; out.textContent = 'Gemini non ha restituito contenuto. Verifica la chiave API in config/app.php.'; }
        return;
    }

    const isFallback = data.source === 'fallback';

    const detectedTitle = extractTitleFromArticle(articleRaw);
    if (detectedTitle) {
        const title = document.getElementById('review-title');
        if (title) title.value = detectedTitle;
    }

    const review = document.getElementById('review-content');
    if (review) review.value = articleRaw;

    if (typeof buildSiteSeoData === 'function' && typeof fillSeoFields === 'function') {
        const contentNoH1 = articleRaw.replace(/<h1\b[^>]*>[\s\S]*?<\/h1>/i, '').trim();
        fillSeoFields(buildSiteSeoData(detectedTitle || '', contentNoH1 || articleRaw));
    }

    refreshH2SelectionState();
    updatePreview();
    queueAutoSave();

    const out = document.getElementById('gemini-result');
    if (isFallback) {
        let msg;
        if (data.reason === 'no_key') {
            msg = '⚠️ Chiave API Gemini non configurata. Testo fallback generato. Imposta gemini_api_key in config/app.php e riprova.';
        } else {
            msg = '⚠️ ' + (data.warning || 'Gemini non disponibile. Usato testo di fallback.');
        }
        const endpointsToTry = [
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent',
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash:generateContent',
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent',
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent',
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent',
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-lite:generateContent'
        ];
        if (out) { out.className = 'notice notice-warn'; out.textContent = msg; }
    } else {
        if (out) { out.className = 'notice notice-ok'; out.textContent = '✅ Articolo generato. Salvataggio in corso...'; }
        try { await saveGeneratedArticle(); } catch (_) {}
        if (out) { out.className = 'notice notice-ok'; out.textContent = '✅ Articolo generato e salvato. Scegli le immagini per i titoli H2 nel Modulo B...'; }
        setTimeout(() => activateTab('mod-b'), 1200);
    }
}

async function loadArticlesList() {
    const data = await apiGet('api/articles.php?action=list');
    state.articles = data.articles || [];
    renderArticlesList();
}

function renderArticlesList() {
    const wrap = document.getElementById('articles-list');
    if (!wrap) return;
    wrap.innerHTML = '';

    const query = (document.getElementById('article-search')?.value || '').trim().toLowerCase();
    const articles = query
        ? state.articles.filter(a =>
            (a.title || '').toLowerCase().includes(query) ||
            (a.category || '').toLowerCase().includes(query)
          )
        : state.articles;

    if (!articles.length) {
        wrap.innerHTML = `<div class="article-row">${query ? 'Nessun articolo trovato.' : 'Nessun articolo salvato.'}</div>`;
        return;
    }

    articles.forEach(article => {
        const row = document.createElement('div');
        row.className = 'article-row';
        if (state.selectedArticleId === article.id) row.classList.add('active');

        const title = document.createElement('div');
        title.className = 'article-row-title';
        title.textContent = article.title || '(senza titolo)';

        const meta = document.createElement('div');
        meta.className = 'article-row-meta';
        meta.textContent = `${article.category || '-'} · ${article.updated_at || ''}`;

        const actions = document.createElement('div');
        actions.className = 'article-row-actions';

        const openBtn = document.createElement('button');
        openBtn.type = 'button';
        openBtn.textContent = 'Carica e modifica';
        openBtn.addEventListener('click', () => selectArticle(article.id));

        const delBtn = document.createElement('button');
        delBtn.type = 'button';
        delBtn.textContent = 'Elimina';
        delBtn.addEventListener('click', async () => {
            await deleteArticle(article.id);
        });

        actions.appendChild(openBtn);
        actions.appendChild(delBtn);

        row.appendChild(title);
        row.appendChild(meta);
        row.appendChild(actions);
        wrap.appendChild(row);
    });
}

async function saveGeneratedArticle(options = {}) {
    const { silent = false, autosave = false } = options;
    const title = (document.getElementById('review-title')?.value || '').trim();
    const content = (document.getElementById('review-content')?.value || '').trim();
    const category = (document.getElementById('selected-category')?.value || '').trim();
    const signature = JSON.stringify({ id: state.selectedArticleId || '', title, content, category, map: state.h2ImageMap, auto: state.autoPlacementEnabled, wpUrls: state.wpUrlMap || {}, wpIds: state.wpMediaIdMap || {}, customCharts: state.customCharts || [], paragraphChartMap: state.paragraphChartMap || {} });

    if (!title || !content) {
        if (autosave) return null;
        throw new Error('Titolo o contenuto articolo mancanti in revisione');
    }

    if (autosave && signature === state.lastAutoSaveSignature) {
        return null;
    }

    const payload = {
        id: state.selectedArticleId || '',
        title,
        content,
        category,
        h2_image_map: state.h2ImageMap,
        auto_place_media: state.autoPlacementEnabled,
        wp_url_map: state.wpUrlMap || {},
        wp_media_id_map: state.wpMediaIdMap || {},
        custom_charts: state.customCharts || [],
        paragraph_chart_map: state.paragraphChartMap || {}
    };

    const saved = await apiPost('api/articles.php?action=save', payload);
    const article = saved.article || null;
    if (!article) throw new Error('Salvataggio non riuscito');

    state.selectedArticleId = article.id;
    state.lastAutoSaveSignature = signature;
    await loadArticlesList();

    if (!silent) {
        const out = document.getElementById('publish-result');
        if (out) out.textContent = autosave ? 'Articolo salvato automaticamente.' : 'Articolo salvato in archivio.';
    }

    return article;
}

async function selectArticle(id) {
    const data = await apiGet(`api/articles.php?action=get&id=${encodeURIComponent(id)}`);
    const article = data.article;
    if (!article) return;

    state.isHydratingArticle = true;
    state.selectedArticleId = article.id;
    state.autoPlacementEnabled = Boolean(article.auto_place_media);
    state.h2ImageMap = article.h2_image_map && typeof article.h2_image_map === 'object' ? article.h2_image_map : {};
    state.wpUrlMap = article.wp_url_map && typeof article.wp_url_map === 'object' ? article.wp_url_map : {};
    state.wpMediaIdMap = article.wp_media_id_map && typeof article.wp_media_id_map === 'object' ? article.wp_media_id_map : {};
    state.customCharts = Array.isArray(article.custom_charts) ? article.custom_charts : [];
    state.paragraphChartMap = article.paragraph_chart_map && typeof article.paragraph_chart_map === 'object' ? article.paragraph_chart_map : {};

    const reviewTitle = document.getElementById('review-title');
    if (reviewTitle) reviewTitle.value = article.title || '';

    const reviewContent = document.getElementById('review-content');
    if (reviewContent) reviewContent.value = article.content || '';

    const categorySelect = document.getElementById('selected-category');
    if (categorySelect) categorySelect.value = article.category || '';

    const wpCategory = document.getElementById('wp-category-name');
    if (wpCategory) wpCategory.value = article.category || '';

    state.isHydratingArticle = false;
    state.lastAutoSaveSignature = JSON.stringify({
        id: state.selectedArticleId || '',
        title: article.title || '',
        content: article.content || '',
        category: article.category || '',
        map: state.h2ImageMap,
        auto: state.autoPlacementEnabled,
        wpUrls: state.wpUrlMap,
        wpIds: state.wpMediaIdMap,
        customCharts: state.customCharts,
        paragraphChartMap: state.paragraphChartMap
    });

    renderArticlesList();
    refreshH2SelectionState();
    if (typeof window.refreshCustomChartsModule === 'function') {
        window.refreshCustomChartsModule();
    }
    updatePreview();
    closeArticleModal();
    activateTab('mod-b');
}

async function deleteArticle(id) {
    await apiPost('api/articles.php?action=delete', { id });
    if (state.selectedArticleId === id) {
        state.selectedArticleId = '';
        state.lastAutoSaveSignature = '';
    }
    await loadArticlesList();
    updatePreview();
}

async function clearAllArticles() {
    await apiPost('api/articles.php?action=clear', {});
    state.articles = [];
    state.selectedArticleId = '';
    state.lastAutoSaveSignature = '';
    renderArticlesList();

    const out = document.getElementById('publish-result');
    if (out) out.textContent = 'Archivio articoli svuotato.';
}

async function loadUploadJsonFiles() {
    const data = await apiGet('api/upload-json.php?action=list');
    state.uploadJsonFiles = data.files || [];

    const select = document.getElementById('json-file-select');
    if (!select) return;

    select.innerHTML = '<option value="">Seleziona file .json</option>';
    state.uploadJsonFiles.forEach(file => {
        const opt = document.createElement('option');
        opt.value = file.token;
        opt.textContent = file.token;
        select.appendChild(opt);
    });
}

function openJsonImportModal() {
    const modal = document.getElementById('json-import-modal');
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
}

function closeJsonImportModal() {
    const modal = document.getElementById('json-import-modal');
    if (modal) { modal.classList.add('hidden'); modal.setAttribute('aria-hidden', 'true'); }
}

async function importSelectedJsonToRawText() {
    const select = document.getElementById('json-file-select');
    const token = select?.value || '';
    if (!token) return;

    const data = await apiGet(`api/upload-json.php?action=read&token=${encodeURIComponent(token)}`);
    const area = document.getElementById('raw-text');
    if (area) area.value = data.text || '';

    closeJsonImportModal();
    updatePreview();
}

