/* GitHub Pages -> Aruba authenticated backend bridge */
(function installArubaBackendBridge() {
    'use strict';

    const isGitHubPages = location.hostname === 'formulapaddock90-hue.github.io';
    if (!isGitHubPages) return;

    const BACKEND_BASE = 'https://www.formulapaddock.it/seo/';
    const STORAGE_KEY = 'fp_aruba_backend_key';

    function backendKey() {
        return (localStorage.getItem(STORAGE_KEY) || '').trim();
    }

    function backendUrl(path) {
        if (/^https?:\/\//i.test(path)) return path;
        return BACKEND_BASE + String(path || '').replace(/^\/+/, '');
    }

    function ensureArubaBaseForNavigation() {
        let base = document.querySelector('base[data-fp-aruba]');
        if (!base) {
            base = document.createElement('base');
            base.dataset.fpAruba = '1';
            document.head.prepend(base);
        }
        base.href = BACKEND_BASE;
        window.setTimeout(() => {
            if (location.hostname === 'formulapaddock90-hue.github.io' && base?.isConnected) {
                base.remove();
            }
        }, 30000);
    }

    async function backendRequest(path, options = {}) {
        const key = backendKey();
        if (!key) {
            throw new Error('Configura la Chiave backend Aruba nelle Impostazioni ⚙️ prima di usare WordPress.');
        }

        const headers = new Headers(options.headers || {});
        headers.set('X-Content-Hub-Key', key);
        headers.set('X-Requested-With', 'XMLHttpRequest');

        const response = await fetch(backendUrl(path), {
            ...options,
            mode: 'cors',
            credentials: 'omit',
            headers
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            if (response.status === 401) {
                throw new Error('Chiave backend Aruba non valida. Apri ⚙️ e reinserisci la password del Content Hub.');
            }
            const detail = typeof data.details === 'object'
                ? (data.details?.message || JSON.stringify(data.details))
                : (data.details || '');
            const message = [data.error, data.message, detail]
                .map(v => String(v || '').trim())
                .filter(Boolean)
                .filter((v, i, arr) => arr.indexOf(v) === i)
                .join(' — ');
            throw new Error(message || `Errore backend Aruba (${response.status})`);
        }

        return data;
    }

    const bridgeGet = async (path) => backendRequest(path, { method: 'GET' });
    const bridgePost = async (path, payload) => backendRequest(path, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload ?? {})
    });

    window.FP_BACKEND_BASE = BACKEND_BASE;
    window.fpBackendRequest = backendRequest;
    window.apiGet = bridgeGet;
    window.apiPost = bridgePost;

    // Le funzioni globali dichiarate da app.js usano questi binding.
    try { apiGet = bridgeGet; } catch (_) {}
    try { apiPost = bridgePost; } catch (_) {}

    function injectBackendSettings() {
        if (document.getElementById('settings-backend-key')) return;

        const panel = document.querySelector('#settings-modal .modal-panel-settings');
        if (!panel) return;

        const sitesHead = panel.querySelector('.settings-sites-head');
        const wrap = document.createElement('div');
        wrap.id = 'settings-backend-bridge';
        wrap.style.cssText = 'margin:14px 0;padding:12px;border:1px solid #ddd;border-radius:8px;';
        wrap.innerHTML = `
            <label style="margin:0;">
                Chiave backend Aruba
                <div class="settings-key-row">
                    <input type="password" id="settings-backend-key" autocomplete="off" placeholder="Password Content Hub Aruba">
                    <button type="button" id="settings-backend-toggle" class="btn-icon" title="Mostra/Nascondi">👁</button>
                </div>
                <small>Salvata solo in questo browser. Serve per pubblicare realmente su WordPress dal GitHub Pages.</small>
            </label>
            <div style="display:flex;justify-content:flex-end;margin-top:8px;">
                <button type="button" id="settings-test-backend" class="btn-sm">Test backend Aruba</button>
            </div>
        `;

        if (sitesHead) panel.insertBefore(wrap, sitesHead);
        else panel.appendChild(wrap);

        const input = document.getElementById('settings-backend-key');
        if (input) input.value = backendKey();

        document.getElementById('settings-backend-toggle')?.addEventListener('click', () => {
            if (input) input.type = input.type === 'password' ? 'text' : 'password';
        });

        document.getElementById('settings-test-backend')?.addEventListener('click', async function () {
            const result = document.getElementById('settings-result');
            const proposed = (input?.value || '').trim();
            if (proposed) localStorage.setItem(STORAGE_KEY, proposed);
            if (result) {
                result.className = '';
                result.textContent = '⏳ Test backend Aruba...';
            }
            try {
                const data = await bridgeGet('api/settings.php');
                if (result) {
                    result.className = 'notice notice-ok';
                    result.textContent = `✅ Backend Aruba collegato. ${Array.isArray(data.sites) ? data.sites.length : 0} sito/i WordPress disponibile/i.`;
                }
                await hydrateWordPressSites(data);
            } catch (err) {
                if (result) {
                    result.className = 'notice notice-warn';
                    result.textContent = `❌ ${err.message}`;
                }
            }
        });

        document.getElementById('settings-save')?.addEventListener('click', () => {
            const value = (input?.value || '').trim();
            if (value) localStorage.setItem(STORAGE_KEY, value);
            else localStorage.removeItem(STORAGE_KEY);
            if (value) {
                setTimeout(() => hydrateWordPressSites().catch(() => {}), 0);
            }
        });
    }

    async function hydrateWordPressSites(prefetched = null) {
        if (!backendKey()) return;
        const data = prefetched || await bridgeGet('api/settings.php');
        if (!Array.isArray(data.sites)) return;

        if (window.state) {
            window.state.settingsSites = data.sites;
        }
        try { if (typeof renderSettingsSites === 'function') renderSettingsSites(); } catch (_) {}
        try { if (typeof renderWpSiteOptions === 'function') renderWpSiteOptions(); } catch (_) {}
        try { if (typeof loadWordPressMeta === 'function') await loadWordPressMeta(); } catch (_) {}
    }

    function installLivePublishCapture() {
        document.addEventListener('click', async (event) => {
            const button = event.target instanceof Element ? event.target.closest('#review-publish') : null;
            if (!button) return;

            if (button.dataset.fpLiveContextReady === '1') {
                delete button.dataset.fpLiveContextReady;
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();

            const live = Boolean(document.getElementById('live-session-check')?.checked);
            const sessionName = (document.getElementById('live-session-name')?.value || 'Sessione Live').trim();
            const meetingSelect = document.getElementById('circuito-select-a');
            const meetingName = (meetingSelect?.selectedOptions?.[0]?.textContent || meetingSelect?.value || '').trim();
            const rows = Array.isArray(window.state?.sessionResultRows) ? window.state.sessionResultRows : [];
            const out = document.getElementById('publish-result');
            const originalText = button.textContent;

            button.disabled = true;
            button.textContent = live ? '⏳ Preparo grafiche Live...' : '⏳ Preparo pubblicazione...';

            try {
                await bridgePost('api/live-social-context.php', {
                    live,
                    session_name: sessionName,
                    meeting_name: meetingName,
                    rows
                });
                // Solo a questo punto i link relativi di uscita (social/index.php)
                // devono risolversi sul backend Aruba.
                ensureArubaBaseForNavigation();
                button.dataset.fpLiveContextReady = '1';
                button.disabled = false;
                button.textContent = originalText;
                button.click();
            } catch (err) {
                button.disabled = false;
                button.textContent = originalText;
                if (out) out.textContent = `Errore preparazione Social Live: ${err.message}`;
            }
        }, true);
    }

    injectBackendSettings();
    installLivePublishCapture();
    document.addEventListener('DOMContentLoaded', () => {
        injectBackendSettings();
        if (backendKey()) {
            hydrateWordPressSites().catch(err => console.warn('Backend Aruba:', err.message));
        }
    });
})();

/* modules/infografica/module.js */
(function () {
    'use strict';

    const CONTENT_WIDTH = 800;

    const btnGenerate    = document.getElementById('infografica-generate');
    const btnRegenerate  = document.getElementById('infografica-regenerate');
    const btnCopy        = document.getElementById('infografica-copy-html');
    const btnDownload    = document.getElementById('infografica-download-html');
    const statusEl       = document.getElementById('infografica-status');
    const previewWrap    = document.getElementById('infografica-preview-wrap');
    const scaleWrap      = document.getElementById('infografica-scale-wrap');
    const frame          = document.getElementById('infografica-frame');
    const htmlSource     = document.getElementById('infografica-html-source');
    const instructionsEl = document.getElementById('infografica-instructions');

    if (!btnGenerate) return;

    /* ── Auto-scala iframe a larghezza pannello ── */
    function applyScale() {
        if (!scaleWrap || !frame) return;
        const available = scaleWrap.clientWidth || CONTENT_WIDTH;
        const scale = Math.min(1, available / CONTENT_WIDTH);
        frame.style.transform = 'scale(' + scale + ')';
        frame.style.width = CONTENT_WIDTH + 'px';
        // Adatta l'altezza del wrapper al contenuto scalato
        const contentH = frame.scrollHeight || parseInt(frame.style.height) || 400;
        scaleWrap.style.height = Math.ceil(contentH * scale) + 'px';
    }

    function resizeFrame() {
        try {
            const doc = frame.contentDocument || frame.contentWindow.document;
            if (doc && doc.body) {
                frame.style.height = (doc.body.scrollHeight + 24) + 'px';
            }
        } catch (_) {}
        applyScale();
    }

    window.addEventListener('resize', applyScale);

    /* ── Recupera contenuto articolo ── */
    function getArticleContent() {
        const reviewContent = document.getElementById('review-content');
        if (reviewContent && reviewContent.value.trim()) {
            return { html: reviewContent.value.trim(), title: getTitle() };
        }
        const geminiResult = document.getElementById('gemini-result');
        if (geminiResult && geminiResult.innerText.trim()) {
            return { html: geminiResult.innerHTML.trim(), title: getTitle() };
        }
        const rawText = document.getElementById('raw-text');
        if (rawText && rawText.value.trim()) {
            return { html: rawText.value.trim(), title: getTitle() };
        }
        return null;
    }

    function getTitle() {
        const reviewTitle = document.getElementById('review-title');
        if (reviewTitle && reviewTitle.value.trim()) return reviewTitle.value.trim();
        const previewTitle = document.getElementById('preview-title');
        if (previewTitle && previewTitle.value.trim()) return previewTitle.value.trim();
        return '';
    }

    /* ── Stato UI ── */
    function setStatus(msg, type) {
        statusEl.textContent = msg;
        statusEl.style.display = 'block';
        const styles = {
            loading: { background:'#1a1a2e', border:'1px solid #3a3a5e', color:'#aaaadd' },
            error:   { background:'#2a0000', border:'1px solid #e10600', color:'#ff6b6b' },
            success: { background:'#0a1f0a', border:'1px solid #2d6a2d', color:'#7ec87e' },
        };
        Object.assign(statusEl.style, styles[type] || {});
    }

    function clearStatus() { statusEl.textContent = ''; statusEl.style.display = 'none'; }

    /* ── Rendering anteprima ── */
    function renderPreview(html) {
        htmlSource.value = html;
        const doc = frame.contentDocument || frame.contentWindow.document;
        doc.open();
        doc.write('<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8">'
            + '<style>*{box-sizing:border-box;margin:0;padding:0;}body{background:#0f0f0f;}</style>'
            + '</head><body>' + html + '</body></html>');
        doc.close();
        frame.onload = resizeFrame;
        setTimeout(resizeFrame, 400);
        previewWrap.classList.remove('hidden');
        btnRegenerate.classList.remove('hidden');
    }

    /* ── Chiamata API ── */
    async function generate() {
        const content = getArticleContent();
        if (!content) {
            setStatus('⚠️ Nessun contenuto trovato. Genera prima un articolo nel Modulo A o carica un testo nel Modulo G.', 'error');
            return;
        }
        btnGenerate.disabled = btnRegenerate.disabled = true;
        setStatus("⏳ Gemini sta analizzando l'articolo e generando l'infografica…", 'loading');
        previewWrap.classList.add('hidden');
        try {
            const data = await apiPost('api/infografica.php', {
                article_html:        content.html,
                article_title:       content.title,
                custom_instructions: instructionsEl ? instructionsEl.value.trim() : '',
            });
            if (!data.success) throw new Error(data.error || data.message || 'Errore sconosciuto');
            clearStatus();
            setStatus('✅ Infografica generata con ' + (data.model_used || 'Gemini'), 'success');
            renderPreview(data.infografica_html);
        } catch (err) {
            setStatus('❌ ' + err.message, 'error');
        } finally {
            btnGenerate.disabled = btnRegenerate.disabled = false;
        }
    }

    btnGenerate.addEventListener('click', generate);
    btnRegenerate.addEventListener('click', generate);

    btnCopy?.addEventListener('click', () => {
        const html = htmlSource.value;
        if (!html) return;
        navigator.clipboard.writeText(html).then(() => {
            const orig = btnCopy.textContent;
            btnCopy.textContent = '✅ Copiato!';
            setTimeout(() => { btnCopy.textContent = orig; }, 2000);
        });
    });

    btnDownload?.addEventListener('click', () => {
        const html = htmlSource.value;
        if (!html) return;
        const full = '<!DOCTYPE html>\n<html lang="it">\n<head>\n<meta charset="UTF-8">\n'
            + '<title>Infografica Formula Paddock</title>\n'
            + '<style>body{background:#0f0f0f;margin:0;padding:20px;}</style>\n'
            + '</head>\n<body>\n' + html + '\n</body>\n</html>';
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([full], { type: 'text/html' }));
        a.download = 'infografica-f1.html';
        a.click();
    });

})();
