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
            const res = await fetch(BASE_PATH + 'api/infografica.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    article_html:        content.html,
                    article_title:       content.title,
                    custom_instructions: instructionsEl ? instructionsEl.value.trim() : '',
                }),
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.error || data.message || 'Errore sconosciuto');
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
