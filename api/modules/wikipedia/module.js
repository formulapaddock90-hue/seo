function buildWikipediaPreviewHtml() {
    const frame = document.getElementById('wiki-module-frame');
    if (!frame) return '';

    let doc;
    try {
        doc = frame.contentDocument || frame.contentWindow?.document;
    } catch {
        return '';
    }

    if (!doc) return '';

    const resultCard = doc.getElementById('result-card');
    if (!resultCard || resultCard.classList.contains('hidden')) {
        return '';
    }

    const title = (doc.getElementById('result-title')?.textContent || '').trim() || 'Wikipedia F1';
    const extract = (doc.getElementById('bio-extract')?.textContent || '').trim();
    const timelineEntries = Array.from(doc.querySelectorAll('#timeline-list > *'))
        .map(node => (node.textContent || '').trim())
        .filter(Boolean)
        .slice(0, 12);

    const timelineHtml = timelineEntries.length
        ? `<ul>${timelineEntries.map(item => `<li>${escapeHtml(item)}</li>`).join('')}</ul>`
        : '';

    const wikiChartItemsHtml = Array.from(doc.querySelectorAll('#charts-section canvas'))
        .map(canvas => {
            const isCanvas = canvas && String(canvas.tagName || '').toLowerCase() === 'canvas';
            if (!isCanvas || typeof canvas.toDataURL !== 'function') return '';

            try {
                const dataUrl = canvas.toDataURL('image/png');
                if (!dataUrl || !dataUrl.startsWith('data:image/')) return '';

                const card = canvas.closest('.chart-card');
                const chartTitle = (card?.querySelector('h4')?.textContent || canvas.id || 'Grafico Wikipedia').trim();

                return [
                    '<figure class="auto-chart-figure wiki-chart-figure">',
                    `<figcaption>${escapeHtml(chartTitle)}</figcaption>`,
                    `<img src="${dataUrl}" alt="${escapeHtml(chartTitle)}">`,
                    '</figure>'
                ].join('');
            } catch {
                return '';
            }
        })
        .filter(Boolean)
        .join('');

    const wikiChartsHtml = wikiChartItemsHtml !== ''
        ? `<div class="wiki-charts-grid">${wikiChartItemsHtml}</div>`
        : '';

    return [
        '<section class="preview-appendix preview-appendix-wiki">',
        `<h2>Modulo E - Wikipedia F1: ${escapeHtml(title)}</h2>`,
        extract ? `<p>${escapeHtml(extract)}</p>` : '',
        timelineHtml,
        wikiChartsHtml,
        '</section>'
    ].join('');
}

