function buildReviewHtml(options = {}) {
    const { applyAutoImages = state.autoPlacementEnabled } = options;
    const reviewOutput = (document.getElementById('review-content')?.value || '').trim();
    const articleHtml = toArticleHtml(reviewOutput);
    const appendixHtml = [
        buildCircuitPreviewHtml(),
        buildPirelliPreviewHtml(),
        buildWikipediaPreviewHtml()
    ].filter(Boolean).join('');

    if (!articleHtml && !appendixHtml) return '';

    if (!articleHtml) {
        return appendChartsToArticleHtml(appendixHtml);
    }

    const parser = new DOMParser();
    const doc = parser.parseFromString(`<div id="preview-root">${articleHtml}</div>`, 'text/html');
    const root = doc.getElementById('preview-root');
    if (!root) return appendChartsToArticleHtml([articleHtml, appendixHtml].filter(Boolean).join(''));

    removeAutoPlacedNodes(root);

    if (applyAutoImages) {
        const images = collectSelectedImages();
        const paragraphs = Array.from(root.querySelectorAll('p'));
        images.forEach((img, index) => {
            const paragraph = paragraphs[index];
            if (!paragraph) return;
            paragraph.insertAdjacentElement('afterend', buildAutoImageNode(doc, img));
        });
    } else {
        root.innerHTML = applyH2ImageLayout(root.innerHTML);
    }

    if (typeof window.applyCustomChartsToArticleRoot === 'function') {
        window.applyCustomChartsToArticleRoot(root, doc);
    }

    return appendChartsToArticleHtml([root.innerHTML, appendixHtml].filter(Boolean).join(''));
}

function appendChartsToArticleHtml(articleHtml) {
    if (!articleHtml) return articleHtml;

    const parser = new DOMParser();
    const doc = parser.parseFromString(`<div id="preview-root">${articleHtml}</div>`, 'text/html');
    const root = doc.getElementById('preview-root');
    if (!root) return articleHtml;

    removeAutoPlacedNodes(root);
    buildAutoChartNodes(doc).forEach(node => root.appendChild(node));
    return root.innerHTML;
}

function buildFullArticleHtml() {
    const reviewContent = (document.getElementById('review-content')?.value || '').trim();
    const reviewHtml = toArticleHtml(reviewContent);
    const appendixHtml = [
        buildCircuitPreviewHtml(),
        buildPirelliPreviewHtml(),
        buildWikipediaPreviewHtml(),
        buildTeamPreviewHtml()
    ].filter(Boolean).join('');

    return `${reviewHtml}${appendixHtml}`;
}

function updatePreview() {
    const articleSource = (document.getElementById('review-content')?.value || '').trim();
    const articleHtml = buildReviewHtml();
    const detectedTitle = extractTitleFromArticle(articleSource) || 'Anteprima articolo';

    const titleEl = document.getElementById('preview-title');
    if (titleEl) titleEl.value = detectedTitle;

    // Popola review-title automaticamente se vuoto (utile dopo generateSeoArticle)
    const reviewTitleEl = document.getElementById('review-title');
    if (reviewTitleEl && !reviewTitleEl.value.trim() && detectedTitle !== 'Anteprima articolo') {
        reviewTitleEl.value = detectedTitle;
    }

    const renderBox = document.getElementById('preview-html-render');
    if (renderBox) renderBox.innerHTML = articleHtml;
}

async function loadWordPressMeta() {
    const site = document.getElementById('wp-site')?.value;
    if (!site) return;

    const data = await apiGet(`api/wordpress.php?action=meta&site=${encodeURIComponent(site)}`);
    state.wpPages = data.pages || [];

    ['wp-category-name', 'wp-update-category-name'].forEach(id => {
        const wpCategory = document.getElementById(id);
        if (wpCategory) {
            const currentValue = wpCategory.value;
            wpCategory.innerHTML = '<option value="">— categoria —</option>';
            (data.categories || [])
                .sort((a, b) => (a.name || '').localeCompare(b.name || '', 'it'))
                .forEach(cat => {
                    const opt = document.createElement('option');
                    opt.value = cat.name;
                    opt.textContent = cat.name;
                    wpCategory.appendChild(opt);
                });
            const defaultCategory = data.defaults?.category || '';
            wpCategory.value = currentValue && wpCategory.querySelector(`option[value="${CSS.escape(currentValue)}"]`)
                ? currentValue
                : defaultCategory;
        }
    });

    ['wp-parent-page', 'wp-update-parent-page'].forEach(id => {
        const parentSelect = document.getElementById(id);
        if (parentSelect) {
            const currentParent = parentSelect.value;
            parentSelect.innerHTML = '<option value="">Nessuna</option>';
            (data.pages || [])
                .sort((a, b) => new Date(b.date || 0) - new Date(a.date || 0))
                .forEach(page => {
                    const opt = document.createElement('option');
                    opt.value = page.id;
                    opt.dataset.postType = page.post_type || 'page';
                    const prefix = page.post_type === 'page' ? (page.parent ? '— ' : '') : `[${page.post_type}] `;
                    opt.textContent = prefix + page.title;
                    parentSelect.appendChild(opt);
                });
            const defaultParent = data.defaults?.parent_page || '';
            parentSelect.value = currentParent && parentSelect.querySelector(`option[value="${CSS.escape(currentParent)}"]`)
                ? currentParent
                : defaultParent;
        }
    });
}

function normalizeSeoWhitespace(value) {
    return String(value || '').replace(/\s+/g, ' ').trim();
}

const SEO_META_DESCRIPTION_MAX = 160;
const SEO_WEAK_TRAILING_WORDS = new Set([
    'e', 'o', 'ma', 'che', 'di', 'del', 'della', 'dello', 'dei', 'degli', 'delle',
    'a', 'ad', 'da', 'in', 'con', 'su', 'per', 'tra', 'fra', 'il', 'lo', 'la', 'i',
    'gli', 'le', 'un', 'una', 'uno', 'piu', 'più', 'anche'
]);

function truncateSeoText(text, maxLength) {
    const value = normalizeSeoWhitespace(text);
    if (value.length <= maxLength) return value;

    const slice = value.slice(0, Math.max(0, maxLength - 1));
    const lastSpace = slice.lastIndexOf(' ');
    const trimmed = (lastSpace > 20 ? slice.slice(0, lastSpace) : slice).trim();
    return `${trimmed}…`;
}

function limitSeoText(text, maxLength) {
    const value = normalizeSeoWhitespace(text);
    if (value.length <= maxLength) return tidySeoEnding(value, maxLength);

    const slice = value.slice(0, maxLength + 1);
    const lastSpace = slice.lastIndexOf(' ');
    const trimmed = (lastSpace > 20 ? slice.slice(0, lastSpace) : value.slice(0, maxLength)).trim();
    return tidySeoEnding(trimmed, maxLength);
}

function tidySeoEnding(text, maxLength) {
    let value = normalizeSeoWhitespace(text).replace(/[,:;.!?\s]+$/g, '');
    let parts = value.split(' ');

    while (parts.length > 1 && SEO_WEAK_TRAILING_WORDS.has(parts[parts.length - 1].toLowerCase())) {
        parts.pop();
    }

    value = parts.join(' ').replace(/[,:;.!?\s]+$/g, '');

    if (value && value.length + 1 <= maxLength) {
        return `${value}.`;
    }

    return value;
}

function buildSeoMetaDescription(focusKeyphrase, plainContent) {
    const fallback = 'analisi, strategie e protagonisti del weekend. Scopri tutti i dettagli della gara.';
    const content = normalizeSeoWhitespace(plainContent);
    const firstSentences = content.match(/[^.!?]+[.!?]+/g) || [];
    let teaser = firstSentences.slice(0, 2).join(' ').trim();

    if (!teaser) {
        teaser = content || fallback;
    }

    return limitSeoText(teaser || fallback, SEO_META_DESCRIPTION_MAX);
}

function sanitizeArticleText(htmlOrText) {
    return normalizeSeoWhitespace(
        String(htmlOrText || '')
            .replace(/<[^>]*>/g, ' ')
            .replace(/&nbsp;/gi, ' ')
            .replace(/&amp;/gi, '&')
    );
}

function extractFocusKeyphrase(title, contentText) {
    const source = `${title} ${contentText}`.toLowerCase();
    const stopWords = new Set([
        'il', 'lo', 'la', 'i', 'gli', 'le', 'un', 'una', 'di', 'a', 'da', 'in', 'con', 'su', 'per', 'tra', 'fra',
        'e', 'o', 'ma', 'che', 'del', 'della', 'dello', 'dei', 'degli', 'delle', 'al', 'alla', 'allo', 'agli', 'alle',
        'nel', 'nella', 'nello', 'nei', 'negli', 'nelle', 'dopo', 'prima', 'come', 'piu', 'più', 'meno'
    ]);

    const words = (source.match(/[a-zàèéìòù0-9]{3,}/g) || [])
        .filter(word => !stopWords.has(word));

    if (!words.length) {
        return 'analisi post gara Formula 1';
    }

    const freq = new Map();
    words.forEach(word => freq.set(word, (freq.get(word) || 0) + 1));

    const ranked = [...freq.entries()]
        .sort((a, b) => b[1] - a[1])
        .slice(0, 3)
        .map(([word]) => word);

    let phrase = ranked.join(' ');
    if (!/formula\s*1/i.test(phrase) && !/f1/i.test(phrase)) {
        phrase = `${phrase} Formula 1`.trim();
    }

    return normalizeSeoWhitespace(phrase);
}

function buildSiteSeoData(title, contentHtml) {
    const cleanTitle = normalizeSeoWhitespace(title);
    const plainContent = sanitizeArticleText(contentHtml);
    const focusKeyphrase = truncateSeoText(extractFocusKeyphrase(cleanTitle, plainContent), 60);

    const seoTitleBase = cleanTitle || 'Analisi post gara Formula 1';
    const seoTitle = truncateSeoText(`${seoTitleBase} | ${focusKeyphrase}`, 60);

    const metaDescription = buildSeoMetaDescription(focusKeyphrase, plainContent);

    return {
        seoTitle,
        metaDescription,
        focusKeyphrase
    };
}

function fillSeoFields(seo) {
    const seoTitleEl = document.getElementById('seo-title');
    const seoMetaEl = document.getElementById('seo-meta-description');
    const seoFocusEl = document.getElementById('seo-focus-keyword');

    if (seoTitleEl && !seoTitleEl.value.trim()) {
        seoTitleEl.value = seo.seoTitle || '';
    }
    if (seoMetaEl && !seoMetaEl.value.trim()) {
        seoMetaEl.value = seo.metaDescription || '';
    }
    if (seoFocusEl && !seoFocusEl.value.trim()) {
        seoFocusEl.value = seo.focusKeyphrase || '';
    }
}

function readSeoFields(fallbackSeo) {
    const seoTitle = (document.getElementById('seo-title')?.value || '').trim();
    const metaDescription = (document.getElementById('seo-meta-description')?.value || '').trim();
    const focusKeyword = (document.getElementById('seo-focus-keyword')?.value || '').trim();

    return {
        seoTitle: truncateSeoText(seoTitle || fallbackSeo.seoTitle || '', 60),
        metaDescription: limitSeoText(metaDescription || fallbackSeo.metaDescription || '', SEO_META_DESCRIPTION_MAX),
        focusKeyword: truncateSeoText(focusKeyword || fallbackSeo.focusKeyphrase || '', 80)
    };
}

function generateSeoFromCurrentArticle() {
    const title = (document.getElementById('review-title')?.value || '').trim();
    const content = buildReviewHtml();
    if (!title || !content) {
        throw new Error('Titolo o contenuto articolo mancanti in revisione');
    }

    const contentWithoutH1 = content.replace(/<h1\b[^>]*>[\s\S]*?<\/h1>/i, '').trim();
    return buildSiteSeoData(title, contentWithoutH1);
}

function applySeoField(field) {
    const seo = generateSeoFromCurrentArticle();
    if (field === 'title') {
        const el = document.getElementById('seo-title');
        if (el) el.value = seo.seoTitle;
        return;
    }
    if (field === 'description') {
        const el = document.getElementById('seo-meta-description');
        if (el) el.value = seo.metaDescription;
        return;
    }
    if (field === 'focus') {
        const el = document.getElementById('seo-focus-keyword');
        if (el) el.value = seo.focusKeyphrase;
    }
}

function applyAllSeoFields() {
    const seo = generateSeoFromCurrentArticle();
    const titleEl = document.getElementById('seo-title');
    const metaEl = document.getElementById('seo-meta-description');
    const focusEl = document.getElementById('seo-focus-keyword');
    if (titleEl) titleEl.value = seo.seoTitle;
    if (metaEl) metaEl.value = seo.metaDescription;
    if (focusEl) focusEl.value = seo.focusKeyphrase;
}

async function publishReviewArticle() {
    const form = document.getElementById('publish-form');
    const formData = Object.fromEntries(new FormData(form).entries());
    const title = (document.getElementById('review-title')?.value || '').trim();
    const content = buildReviewHtml();

    if (!title || !content) throw new Error('Titolo o contenuto articolo mancanti in revisione');

    const contentWithoutH1 = content.replace(/<h1\b[^>]*>[\s\S]*?<\/h1>/i, '').trim();
    const gutenbergContent = toGutenbergHtml(contentWithoutH1);
    const featuredMediaId = getFeaturedMediaId();
    const generatedSeo = buildSiteSeoData(title, contentWithoutH1);
    fillSeoFields(generatedSeo);
    const siteSeo = readSeoFields(generatedSeo);

    const parentValue = formData.parent_page_id || '';
    const isUrl = parentValue.startsWith('http://') || parentValue.startsWith('https://');
    const categoryName = (
        document.getElementById('wp-category-name')?.value
        || document.getElementById('selected-category')?.value
        || ''
    ).trim();

    const payload = {
        site: formData.site,
        title,
        content: gutenbergContent,
        post_type: formData.post_type || 'post',
        status: formData.publish_status || 'draft',
        category_name: categoryName,
        parent_page_id: isUrl ? 0 : (parentValue ? Number(parentValue) : 0),
        parent_page_url: isUrl ? parentValue : '',
        featured_media_id: featuredMediaId,
        seo_title: siteSeo.seoTitle,
        meta_description: siteSeo.metaDescription,
        focus_keyword: siteSeo.focusKeyword
    };

    return apiPost('api/wordpress.php?action=publish', payload);
}
async function updateExistingArticle() {
    const urlInput           = document.getElementById('existing-article-url');
    const existingUrl        = (urlInput?.value || '').trim();
    const existingId         = parseInt(urlInput?.dataset?.postId        || '0', 10) || 0;
    const existingFeaturedId = parseInt(urlInput?.dataset?.featuredMedia || '0', 10) || 0;
    const existingPostType   = (urlInput?.dataset?.postType || '').trim();   // tipo reale da sitemap
    if (!existingUrl) throw new Error('Inserisci l\'URL dell\'articolo da aggiornare');

    const form      = document.getElementById('publish-form');
    const formData  = Object.fromEntries(new FormData(form).entries());
    const title     = (document.getElementById('review-title')?.value || '').trim();
    const content   = buildReviewHtml();
    if (!title || !content) throw new Error('Titolo o contenuto articolo mancanti in revisione');

    const contentWithoutH1 = content.replace(/<h1\b[^>]*>[\s\S]*?<\/h1>/i, '').trim();
    const gutenbergContent  = toGutenbergHtml(contentWithoutH1);
    // Priorità: immagine selezionata in Modulo B > immagine già in evidenza sull'articolo
    const featuredMediaId   = getFeaturedMediaId() || existingFeaturedId;
    const generatedSeo      = buildSiteSeoData(title, contentWithoutH1);
    fillSeoFields(generatedSeo);
    const siteSeo = readSeoFields(generatedSeo);

    const categoryName = (
        document.getElementById('wp-update-category-name')?.value
        || document.getElementById('wp-category-name')?.value
        || document.getElementById('selected-category')?.value
        || ''
    ).trim();

    const parentPageValue = (
        document.getElementById('wp-update-parent-page')?.value
        || document.getElementById('wp-parent-page')?.value
        || ''
    ).trim();
    const isUrl = parentPageValue.startsWith('http://') || parentPageValue.startsWith('https://');

    const updateStatus = (document.getElementById('wp-update-status')?.value || formData.publish_status || 'publish');
    // Usa il tipo selezionato nel pannello aggiorna, oppure il tipo reale dell'articolo (da sitemap) o quello del form
    const resolvedPostType = (document.getElementById('wp-update-post-type')?.value || existingPostType || formData.post_type || 'post');
    // Data pubblicazione: se vuota usa la data/ora corrente
    const updateDateRaw = (document.getElementById('wp-update-date')?.value || '').trim();
    const nowDate = new Date();
    const nowIso = nowDate.getFullYear() + '-'
        + String(nowDate.getMonth() + 1).padStart(2, '0') + '-'
        + String(nowDate.getDate()).padStart(2, '0') + 'T'
        + String(nowDate.getHours()).padStart(2, '0') + ':'
        + String(nowDate.getMinutes()).padStart(2, '0') + ':'
        + String(nowDate.getSeconds()).padStart(2, '0');
    const updateDate = updateDateRaw
        ? (updateDateRaw.length === 16 ? updateDateRaw + ':00' : updateDateRaw)
        : nowIso;
    const payload = {
        site:              formData.site,
        existing_url:      existingUrl,
        post_id:           existingId > 0 ? existingId : undefined,
        title,
        content:           gutenbergContent,
        post_type:         resolvedPostType,
        status:            updateStatus,
        date:              updateDate,
        category_name:     categoryName,
        parent_page_id:    isUrl ? 0 : (parentPageValue ? Number(parentPageValue) : 0),
        parent_page_url:   isUrl ? parentPageValue : '',
        featured_media_id: featuredMediaId,
        seo_title:         siteSeo.seoTitle,
        meta_description:  siteSeo.metaDescription,
        focus_keyword:     siteSeo.focusKeyword,
    };

    return apiPost('api/wordpress.php?action=update', payload);
}

async function addNewCategory() {
    const site = document.getElementById('wp-site')?.value;
    const nameInput = document.getElementById('new-category-name');
    const resultEl = document.getElementById('add-category-result');
    const name = nameInput?.value.trim();

    if (!site || !name) return;

    resultEl.textContent = '⏳ Creazione…';
    resultEl.className = 'muted';

    try {
        const data = await apiPost('api/wordpress.php?action=create_category', { site, name });
        const wpCategory = document.getElementById('wp-category-name');
        if (wpCategory) {
            const exists = wpCategory.querySelector(`option[value="${CSS.escape(name)}"]`);
            if (!exists) {
                const opt = document.createElement('option');
                opt.value = name;
                opt.textContent = name;
                const options = Array.from(wpCategory.options);
                const insertBefore = options.find(o => o.value && o.textContent.localeCompare(name, 'it') > 0);
                if (insertBefore) {
                    wpCategory.insertBefore(opt, insertBefore);
                } else {
                    wpCategory.appendChild(opt);
                }
            }
            wpCategory.value = name;
        }
        if (nameInput) nameInput.value = '';
        resultEl.textContent = '✅ Categoria aggiunta';
        resultEl.className = 'notice notice-ok';
    } catch (err) {
        resultEl.textContent = `❌ ${err.message}`;
        resultEl.className = 'notice notice-warn';
    }
}

document.getElementById('add-category-btn')?.addEventListener('click', () => addNewCategory());
document.getElementById('new-category-name')?.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); addNewCategory(); }
});

function setUpdateDateToNow() {
    const el = document.getElementById('wp-update-date');
    if (!el) return;
    const now = new Date();
    el.value = now.getFullYear() + '-'
        + String(now.getMonth() + 1).padStart(2, '0') + '-'
        + String(now.getDate()).padStart(2, '0') + 'T'
        + String(now.getHours()).padStart(2, '0') + ':'
        + String(now.getMinutes()).padStart(2, '0');
}

document.getElementById('g-tab-aggiorna')?.addEventListener('click', setUpdateDateToNow);
document.addEventListener('DOMContentLoaded', setUpdateDateToNow);
