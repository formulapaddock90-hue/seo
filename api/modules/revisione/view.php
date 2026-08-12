<section id="mod-g" class="tab-content">
    <h2>Modulo G: Pubblica</h2>
    <textarea id="review-content" name="review_content" style="display:none"></textarea>
    <div id="review-preview-pane" class="review-preview-pane hidden"></div>

    <form id="publish-form">
        <!-- Titolo + Sito (riga condivisa) -->
        <div style="display:grid;grid-template-columns:1fr auto;gap:8px;align-items:end;margin-bottom:8px;">
            <label style="margin:0;">Titolo articolo
                <input type="text" id="review-title" name="review_title" placeholder="Titolo articolo" style="margin-top:4px;">
            </label>
            <label style="margin:0;min-width:160px;">Sito WordPress
                <select name="site" id="wp-site" style="margin-top:4px;">
                    <?php foreach ($siteConfig['sites'] as $key => $site): ?>
                        <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($site['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <!-- SEO fieldset (compatto) -->
        <fieldset style="margin-bottom:10px;padding:8px 12px;">
            <legend>SiteSEO</legend>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:6px;">
                <label style="margin:0;font-size:.85rem;">Titolo SEO
                    <input type="text" id="seo-title" name="seo_title" maxlength="60" placeholder="(max 60)" style="margin-top:3px;">
                </label>
                <label style="margin:0;font-size:.85rem;">Focus keyword
                    <input type="text" id="seo-focus-keyword" name="focus_keyword" maxlength="80" placeholder="Parola chiave" style="margin-top:3px;">
                </label>
            </div>
            <label style="margin:0 0 6px;font-size:.85rem;">Meta description
                <textarea id="seo-meta-description" name="meta_description" maxlength="160" rows="2" placeholder="(max 160)" style="margin-top:3px;"></textarea>
            </label>
            <div style="display:flex;gap:5px;flex-wrap:wrap;">
                <button type="button" id="seo-generate-title" style="font-size:.75rem;padding:3px 7px;">Titolo SEO</button>
                <button type="button" id="seo-generate-description" style="font-size:.75rem;padding:3px 7px;">Meta desc</button>
                <button type="button" id="seo-generate-focus" style="font-size:.75rem;padding:3px 7px;">Keyword</button>
                <button type="button" id="seo-generate-all" style="font-size:.75rem;padding:3px 7px;">✨ Genera tutto</button>
            </div>
        </fieldset>

        <!-- Tab switcher -->
        <div style="display:flex;gap:0;border-bottom:2px solid #2a2a2a;margin-bottom:12px;">
            <button type="button" class="g-mode-tab active" id="g-tab-pubblica" data-panel="g-panel-pubblica"
                    style="padding:7px 14px;font-size:.85rem;border:none;border-bottom:2px solid #e10600;margin-bottom:-2px;background:transparent;color:#fff;cursor:pointer;font-weight:500;">📤 Pubblica</button>
            <button type="button" class="g-mode-tab" id="g-tab-aggiorna" data-panel="g-panel-aggiorna"
                    style="padding:7px 14px;font-size:.85rem;border:none;border-bottom:2px solid transparent;margin-bottom:-2px;background:transparent;color:#888;cursor:pointer;">🔄 Aggiorna</button>
        </div>

        <!-- Panel: Pubblica nuovo -->
        <div id="g-panel-pubblica">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:8px;">
                <label style="margin:0;font-size:.85rem;">Tipo contenuto
                    <select name="post_type" id="wp-post-type" style="margin-top:3px;">
                        <option value="post">Articolo (post)</option>
                        <option value="page">Pagina</option>
                    </select>
                </label>
                <label style="margin:0;font-size:.85rem;">Stato
                    <select name="publish_status" id="wp-status" style="margin-top:3px;">
                        <option value="draft">Bozza</option>
                        <option value="publish">Pubblica</option>
                        <option value="pending">In revisione</option>
                    </select>
                </label>
                <label style="margin:0;font-size:.85rem;">Categoria
                    <select name="category_name" id="wp-category-name" style="margin-top:3px;">
                        <option value="">— categoria —</option>
                    </select>
                </label>
            </div>
            <div style="display:grid;grid-template-columns:1fr auto auto;gap:8px;align-items:end;margin-bottom:6px;">
                <label style="margin:0;font-size:.85rem;">Pagina genitore
                    <select name="parent_page_id" id="wp-parent-page" style="margin-top:3px;">
                        <option value="">Nessuna</option>
                    </select>
                </label>
                <input type="text" id="new-category-name" placeholder="Nuova categoria…" style="margin:0;height:34px;">
                <button type="button" id="add-category-btn" style="height:34px;white-space:nowrap;">+ Cat</button>
            </div>
            <div style="min-height:16px;margin-bottom:8px;">
                <span id="add-category-result" class="muted" style="font-size:.78rem;"></span>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button type="button" id="review-publish">📤 Pubblica</button>
                <button type="button" id="review-edit">✏️ Modifica</button>
            </div>
        </div>

        <!-- Panel: Aggiorna esistente -->
        <div id="g-panel-aggiorna" style="display:none;">
            <div style="display:grid;grid-template-columns:auto auto 1fr;gap:8px;align-items:end;margin-bottom:8px;">
                <label style="margin:0;font-size:.85rem;">Stato
                    <select id="wp-update-status" style="margin-top:3px;min-width:120px;">
                        <option value="publish">Pubblica</option>
                        <option value="draft">Bozza</option>
                        <option value="pending">In revisione</option>
                    </select>
                </label>
                <label style="margin:0;font-size:.85rem;">Data pubblicazione
                    <input type="datetime-local" id="wp-update-date" style="margin-top:3px;min-width:180px;">
                </label>
                <label style="margin:0;font-size:.85rem;">URL articolo da sovrascrivere
                    <div style="display:flex;gap:6px;margin-top:3px;">
                        <input type="url" id="existing-article-url" placeholder="https://www.formulapaddock.it/…" style="flex:1;margin:0;">
                        <button type="button" id="browse-sitemap-btn" style="white-space:nowrap;height:36px;">🗺️ Sfoglia</button>
                    </div>
                </label>
            </div>
            <p style="font-size:.78rem;color:#666;margin:2px 0 10px;">Titolo e contenuto vengono rimpiazzati, l'URL resta invariato.</p>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button type="button" id="review-update" style="background:#1a2a4a;border-color:#3a5a9a;color:#8ab4f8;">🔄 Aggiorna</button>
                <button type="button" id="review-edit-from-update">✏️ Modifica</button>
            </div>
        </div>
    </form>
    <div id="publish-result"></div>

    <!-- Sitemap browser modal -->
    <div id="sitemap-modal" style="display:none;position:fixed;inset:0;z-index:2000;background:rgba(0,0,0,.82);overflow:auto;">
        <div style="background:#1a1a1a;border:1px solid #333;border-radius:10px;max-width:660px;width:calc(100% - 32px);margin:60px auto;padding:20px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                <h3 style="margin:0;font-size:1rem;">🗺️ Seleziona articolo</h3>
                <button type="button" id="sitemap-modal-close" style="background:none;border:none;color:#888;font-size:1.3rem;cursor:pointer;line-height:1;padding:0 4px;">✕</button>
            </div>
            <input type="text" id="sitemap-search" placeholder="🔍 Cerca per titolo o URL…" style="margin-bottom:10px;">
            <div id="sitemap-list" style="max-height:420px;overflow-y:auto;border:1px solid #2a2a2a;border-radius:6px;font-size:.85rem;"></div>
        </div>
    </div>
</section>
