(() => {
    const TEXT_KEYS = ['raw_text', 'text', 'content', 'article', 'body', 'message', 'description', 'transcript'];
    const NESTED_KEYS = ['data', 'payload', 'result', 'response', 'item', 'document'];

    function candidateToText(value, depth = 0) {
        if (depth > 6 || value == null) return '';

        if (typeof value === 'string') {
            return value.trim();
        }

        if (Array.isArray(value)) {
            const parts = value
                .map(item => candidateToText(item, depth + 1))
                .filter(Boolean);
            return parts.join('\n\n').trim();
        }

        if (typeof value !== 'object') return '';

        for (const key of TEXT_KEYS) {
            if (!Object.prototype.hasOwnProperty.call(value, key)) continue;
            const text = candidateToText(value[key], depth + 1);
            if (text) return text;
        }

        for (const key of NESTED_KEYS) {
            if (!Object.prototype.hasOwnProperty.call(value, key)) continue;
            const text = candidateToText(value[key], depth + 1);
            if (text) return text;
        }

        return '';
    }

    function extractText(decoded) {
        const text = candidateToText(decoded);
        if (text) return text;

        if (typeof decoded === 'string') return decoded.trim();

        return JSON.stringify(decoded, null, 2) || '';
    }

    function showStatus(message, ok = true) {
        const out = document.getElementById('gemini-result') || document.getElementById('publish-result');
        if (!out) return;
        out.className = ok ? 'notice notice-ok' : 'notice notice-warn';
        out.textContent = message;
    }

    async function importLocalJson(file) {
        if (!file) return;

        const isJson = file.name.toLowerCase().endsWith('.json') || file.type === 'application/json';
        if (!isJson) {
            throw new Error('Seleziona un file con estensione .json');
        }

        const raw = (await file.text()).replace(/^\uFEFF/, '').trim();
        if (!raw) {
            throw new Error('Il file JSON è vuoto');
        }

        let decoded;
        try {
            decoded = JSON.parse(raw);
        } catch (err) {
            throw new Error(`JSON non valido: ${err.message}`);
        }

        const text = extractText(decoded).trim();
        if (!text) {
            throw new Error('Nel JSON non è stato trovato testo importabile');
        }

        const area = document.getElementById('raw-text');
        if (!area) {
            throw new Error('Campo "Testo grezzo" non trovato');
        }

        area.value = text;
        area.dispatchEvent(new Event('input', { bubbles: true }));
        area.focus();
        showStatus(`✅ Importato ${file.name} nel testo grezzo.`);
    }

    function ensureFileInput() {
        let input = document.getElementById('json-local-import-input');
        if (input) return input;

        input = document.createElement('input');
        input.type = 'file';
        input.id = 'json-local-import-input';
        input.accept = '.json,application/json';
        input.hidden = true;
        document.body.appendChild(input);

        input.addEventListener('change', async () => {
            const file = input.files?.[0] || null;
            try {
                await importLocalJson(file);
            } catch (err) {
                showStatus(`❌ Errore import JSON: ${err.message}`, false);
            } finally {
                input.value = '';
            }
        });

        return input;
    }

    // Intercetta il vecchio pulsante prima dell'handler server-side di app.js.
    // In questo modo "Importa testo da JSON (upload)" apre davvero un file locale.
    document.addEventListener('click', (event) => {
        const button = event.target.closest?.('#open-json-import');
        if (!button) return;

        event.preventDefault();
        event.stopImmediatePropagation();
        ensureFileInput().click();
    }, true);
})();
