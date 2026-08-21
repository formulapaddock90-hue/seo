<section id="mod-infografica" class="tab-content">
    <h2>Modulo G: Grafica e Scala</h2>
    <p style="color:#999;margin-top:0;margin-bottom:16px;font-size:.9rem;">
        Genera infografiche HTML/CSS dalla notizia corrente. Il layout si scala automaticamente alla larghezza del pannello.
    </p>

    <div>
        <label>Istruzioni aggiuntive <span style="color:#666;font-weight:normal;">(opzionale)</span>
            <input type="text" id="infografica-instructions"
                   placeholder="es. concentrati sui pit stop, usa i colori della Ferrari, mostra solo il podio…"
                   style="margin-top:6px;">
        </label>
    </div>

    <div class="form-actions-row" style="margin-top:12px;">
        <button type="button" id="infografica-generate">🎨 Genera Infografica</button>
        <button type="button" id="infografica-regenerate" class="hidden">🔄 Rigenera</button>
    </div>

    <div id="infografica-status" class="hidden" style="margin-top:12px;padding:10px 14px;border-radius:6px;font-size:.9rem;"></div>

    <div id="infografica-preview-wrap" class="hidden" style="margin-top:20px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;flex-wrap:wrap;gap:8px;">
            <h3 style="margin:0;">Anteprima</h3>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button type="button" id="infografica-copy-html">📋 Copia HTML</button>
                <button type="button" id="infografica-download-html">⬇️ Scarica HTML</button>
            </div>
        </div>
        <!-- wrapper per lo scale: overflow hidden per nascondere ciò che esce -->
        <div id="infografica-scale-wrap" style="position:relative;width:100%;overflow:hidden;border:1px solid #2a2a2a;border-radius:8px;background:#0f0f0f;">
            <iframe id="infografica-frame"
                    scrolling="no"
                    style="width:800px;border:none;display:block;transform-origin:top left;"
                    title="Anteprima infografica">
            </iframe>
        </div>
    </div>

    <textarea id="infografica-html-source" style="display:none;"></textarea>
</section>
