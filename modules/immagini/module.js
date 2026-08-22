function collectSelectedImages() {
        return Object.keys(state.h2ImageMap)
            .sort((a, b) => Number(a) - Number(b))
            .map(key => state.mediaImages.find(x => x.token === state.h2ImageMap[key]))
            .filter(Boolean);
    }

function resolveImgUrl(img) {
    return (state.wpUrlMap && state.wpUrlMap[img.token]) || img.url;
}

function removeAutoPlacedNodes(root) {
    root.querySelectorAll('[data-auto-image="1"], [data-auto-chart="1"]').forEach(node => node.remove());
}

function buildAutoImageNode(doc, img) {
    const figure = doc.createElement('figure');
    figure.className = 'auto-media-figure';
    figure.setAttribute('data-auto-image', '1');

    const image = doc.createElement('img');
    image.setAttribute('src', resolveImgUrl(img));
    image.alt = img.file || 'Immagine articolo';
    figure.appendChild(image);

    return figure;
}

function applyH2ImageLayout(articleHtml) {
    if (!articleHtml) return articleHtml;

    const parser = new DOMParser();
    const doc = parser.parseFromString(`<div id="layout-root">${articleHtml}</div>`, 'text/html');
    const root = doc.getElementById('layout-root');
    if (!root) return articleHtml;

    const h2Elements = Array.from(root.querySelectorAll('h2'));
    
    h2Elements.forEach((h2, idx) => {
        const imageToken = state.h2ImageMap[String(idx)];
        if (!imageToken) return;

        const img = state.mediaImages.find(x => x.token === imageToken);
        if (!img) return;

        const flexContainer = doc.createElement('div');
        flexContainer.className = 'h2-image-flex';
        flexContainer.style.display = 'flex';
        flexContainer.style.flexWrap = 'wrap';
        flexContainer.style.gap = '12px';
        flexContainer.style.alignItems = 'flex-start';
        flexContainer.style.marginBottom = '14px';
        flexContainer.style.paddingBottom = '10px';
        flexContainer.style.borderBottom = '1px solid #e5e5e5';

        const imageLeft = doc.createElement('div');
        imageLeft.className = 'h2-image-left';
        imageLeft.style.flex = '0 1 36%';
        imageLeft.style.minWidth = '220px';

        const image = doc.createElement('img');
        image.setAttribute('src', resolveImgUrl(img));
        image.alt = img.file || 'Immagine articolo';
        image.style.width = '100%';
        image.style.height = 'auto';
        image.style.display = 'block';
        image.style.borderRadius = '6px';
        imageLeft.appendChild(image);

        const contentRight = doc.createElement('div');
        contentRight.className = 'h2-image-right';
        contentRight.style.flex = '1 1 320px';
        contentRight.style.minWidth = '0';
        contentRight.appendChild(h2.cloneNode(true));

        let nextNode = h2.nextElementSibling;
        const nodesToMove = [];
        while (nextNode && nextNode.tagName !== 'H2') {
            nodesToMove.push(nextNode);
            nextNode = nextNode.nextElementSibling;
        }

        nodesToMove.forEach(node => {
            contentRight.appendChild(node.cloneNode(true));
        });

        flexContainer.appendChild(imageLeft);
        flexContainer.appendChild(contentRight);
        h2.parentNode?.replaceChild(flexContainer, h2);
        nodesToMove.forEach(node => node.remove());
    });

    return root.innerHTML;
}

function updateAutoPlaceResult(message = '', isError = false) {
    const out = document.getElementById('b-auto-place-result');
    if (!out) return;
    out.className = isError ? 'notice notice-warn' : 'notice notice-ok';
    out.textContent = message;
}

function extractH2TitlesFromSource(source) {
    if (!source) return [];

    if (/<\s*h2\b/i.test(source)) {
        const parser = new DOMParser();
        const doc = parser.parseFromString(source, 'text/html');
        return Array.from(doc.querySelectorAll('h2')).map(el => el.textContent.trim());
    }

    return source.split('\n')
        .filter(line => line.trim().startsWith('H2:'))
        .map(line => line.trim().replace('H2:', '').trim());
}

function refreshH2SelectionState() {
    const source = (document.getElementById('review-content')?.value || '').trim();
    state.reviewH2Titles = extractH2TitlesFromSource(source);

    const allowed = new Set(state.reviewH2Titles.map((_, idx) => String(idx)));
    Object.keys(state.h2ImageMap).forEach(key => {
        if (!allowed.has(String(key))) {
            delete state.h2ImageMap[key];
        }
    });

    renderH2Cards();
}

function renderH2Cards() {
    const wrap = document.getElementById('b-h2-cards');
    if (!wrap) return;
    wrap.innerHTML = '';

    if (!state.reviewH2Titles.length) {
        wrap.innerHTML = '<div class="folder-item muted">Nessun titolo H2 trovato. Genera prima il testo con Gemini.</div>';
        return;
    }

    state.reviewH2Titles.forEach((title, idx) => {
        const token = state.h2ImageMap[String(idx)];
        const img = token ? state.mediaImages.find(x => x.token === token) : null;

        const card = document.createElement('div');
        card.className = 'h2-card';

        const info = document.createElement('div');
        info.className = 'h2-card-info';

        const lbl = document.createElement('span');
        lbl.className = 'h2-card-label';
        lbl.textContent = `H2 ${idx + 1}`;

        const titleEl = document.createElement('span');
        titleEl.className = 'h2-card-title';
        titleEl.textContent = title;

        info.appendChild(lbl);
        info.appendChild(titleEl);

        const actions = document.createElement('div');
        actions.className = 'h2-card-actions';

        if (img) {
            const thumb = document.createElement('img');
            thumb.src = resolveImgUrl(img);
            thumb.alt = img.file;
            thumb.className = 'h2-card-thumb';
            actions.appendChild(thumb);
        }

        const pickBtn = document.createElement('button');
        pickBtn.type = 'button';
        pickBtn.className = 'btn-sm';
        pickBtn.textContent = img ? '🔄 Cambia da libreria' : '🖼️ Scegli da libreria';
        pickBtn.addEventListener('click', () => openImgPickerModal(idx));
        actions.appendChild(pickBtn);

        if (img) {
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn-sm btn-sm-danger';
            removeBtn.textContent = 'Rimuovi';
            removeBtn.addEventListener('click', () => {
                delete state.h2ImageMap[String(idx)];
                renderH2Cards();
                updatePreview();
                queueAutoSave();
            });
            actions.appendChild(removeBtn);
        }

        card.appendChild(info);
        card.appendChild(actions);
        wrap.appendChild(card);
    });
}

function saveCustomImagesToStorage() {
    try {
        const localImgs = (state.mediaImages || []).filter(img => img && img.token && img.token.startsWith('img_local_'));
        localStorage.setItem('fp_custom_images', JSON.stringify(localImgs));
    } catch(e) {
        console.warn('Impossibile salvare immagini in localStorage:', e);
    }
}

function loadCustomImagesFromStorage() {
    try {
        const stored = localStorage.getItem('fp_custom_images');
        if (!stored) return;
        const localImgs = JSON.parse(stored);
        if (Array.isArray(localImgs) && localImgs.length > 0) {
            const existingTokens = new Set((state.mediaImages || []).map(x => x.token));
            localImgs.forEach(img => {
                if (img && img.token && !existingTokens.has(img.token)) {
                    state.mediaImages.unshift(img);
                }
            });
        }
    } catch(e) {
        console.warn('Impossibile caricare immagini da localStorage:', e);
    }
    ensureCategoriesFromImages();
}

function ensureCategoriesFromImages() {
    if (!state.mediaImages || !state.mediaImages.length) return;
    const catMap = {};
    state.mediaImages.forEach(img => {
        const folder = img.folder || 'Upload PC';
        const cat = img.category || 'Generale';
        const key = `${folder}/${cat}`;
        if (!catMap[key]) {
            catMap[key] = { folder, category: cat, files_count: 0, has_images: true };
        }
        catMap[key].files_count++;
    });
    if (!state.mediaCategories) state.mediaCategories = [];
    const existingKeys = new Set(state.mediaCategories.map(c => `${c.folder}/${c.category}`));
    Object.values(catMap).forEach(c => {
        const k = `${c.folder}/${c.category}`;
        if (!existingKeys.has(k)) {
            state.mediaCategories.push(c);
        }
    });
}

function openImgPickerModal(h2Idx) {
    state.imgPickerMode = 'h2';
    state.postgaraPickerTeamIdx = null;
    state.imgPickerTargetH2 = h2Idx;
    state.imgPickerFolderKey = '';

    const modal = document.getElementById('img-picker-modal');
    if (!modal) return;

    const label = document.getElementById('img-picker-h2-label');
    if (label) label.textContent = `H2 ${h2Idx + 1}: ${state.reviewH2Titles[h2Idx] || ''}`;

    ensureCategoriesFromImages();

    if (!state.imgPickerFolderKey && state.mediaCategories && state.mediaCategories.length > 0) {
        const firstCat = state.mediaCategories.find(c => c.has_images) || state.mediaCategories[0];
        if (firstCat) {
            state.imgPickerFolderKey = mediaFolderKey(firstCat.folder, firstCat.category);
        }
    }

    renderImgPickerFolders();
    renderImgPickerGallery(state.imgPickerFolderKey || '');

    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
}

async function openPostGaraUploadsPicker(teamIdx) {
    state.imgPickerMode = 'postgara';
    state.imgPickerTargetH2 = null;
    state.postgaraPickerTeamIdx = teamIdx;
    state.postgaraUploadsSubfolder = '';

    const team = postgara?.teams?.[teamIdx];
    const label = document.getElementById('img-picker-h2-label');
    if (label) {
        label.textContent = team ? `${team.name} · uploads` : 'Post Gara · uploads';
    }

    const modal = document.getElementById('img-picker-modal');
    if (!modal) return;

    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');

    await renderPostGaraUploadsPicker('');
}

function closeImgPickerModal() {
    const modal = document.getElementById('img-picker-modal');
    if (modal) { modal.classList.add('hidden'); modal.setAttribute('aria-hidden', 'true'); }
    state.imgPickerMode = 'h2';
    state.postgaraPickerTeamIdx = null;
}

function renderImgPickerFolders() {
    if (state.imgPickerMode !== 'h2') return;

    const wrap = document.getElementById('img-picker-folders');
    if (!wrap) return;
    wrap.innerHTML = '';

    const withImages = state.mediaCategories.filter(c => c.has_images);
    if (!withImages.length) {
        wrap.innerHTML = '<div class="folder-item">Nessuna cartella con immagini.</div>';
        return;
    }

    withImages.forEach(cat => {
        const key = mediaFolderKey(cat.folder, cat.category);
        const item = document.createElement('div');
        item.className = 'folder-item';
        if (state.imgPickerFolderKey === key) item.classList.add('active');

        const title = document.createElement('div');
        title.className = 'folder-title';
        title.textContent = `${cat.folder}/${cat.category} (${cat.files_count})`;
        item.appendChild(title);

        item.addEventListener('click', () => {
            state.imgPickerFolderKey = key;
            renderImgPickerFolders();
            renderImgPickerGallery(key);
        });

        wrap.appendChild(item);
    });
}

function renderImgPickerGallery(folderKey) {
    if (state.imgPickerMode !== 'h2') return;

    const wrap = document.getElementById('img-picker-gallery');
    if (!wrap) return;
    wrap.innerHTML = '';

    if (!folderKey) {
        wrap.innerHTML = '<div class="muted">Seleziona una cartella per vedere le immagini.</div>';
        return;
    }

    const filtered = state.mediaImages.filter(img => mediaFolderKey(img.folder, img.category) === folderKey);
    if (!filtered.length) {
        wrap.innerHTML = '<div class="muted">Nessuna immagine in questa cartella.</div>';
        return;
    }

    filtered.forEach(img => {
        const card = document.createElement('div');
        card.className = 'picker-img-card';

        const thumb = document.createElement('img');
        thumb.src = img.url;
        thumb.alt = img.file;
        thumb.loading = 'lazy';
        thumb.decoding = 'async';
        thumb.className = 'picker-img-thumb';

        const name = document.createElement('div');
        name.className = 'picker-img-name';
        name.textContent = img.file;

        card.appendChild(thumb);
        card.appendChild(name);

        card.addEventListener('click', () => {
            if (state.imgPickerTargetH2 !== null) {
                state.h2ImageMap[String(state.imgPickerTargetH2)] = img.token;
                renderH2Cards();
                updatePreview();
                queueAutoSave();
            }
            closeImgPickerModal();
        });

        wrap.appendChild(card);
    });
}

async function renderPostGaraUploadsPicker(subfolder = '') {
    const foldersWrap = document.getElementById('img-picker-folders');
    const galleryWrap = document.getElementById('img-picker-gallery');
    if (!foldersWrap || !galleryWrap) return;

    foldersWrap.innerHTML = '<div class="muted">Caricamento cartelle...</div>';
    galleryWrap.innerHTML = '<div class="muted">Caricamento immagini...</div>';

    const data = await apiGet(`api/file-browser.php?action=uploads-browse&subfolder=${encodeURIComponent(subfolder)}`);
    state.postgaraUploadsSubfolder = String(data.subfolder || '');

    const folderPath = state.postgaraUploadsSubfolder ? `uploads/${state.postgaraUploadsSubfolder}` : 'uploads';
    const files = Array.isArray(data.files) ? data.files : [];
    const subfolders = Array.isArray(data.subfolders) ? data.subfolders : [];
    const imageExts = new Set(['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
    const imageFiles = files.filter(f => imageExts.has(String(f).toLowerCase().split('.').pop()));

    foldersWrap.innerHTML = '';
    if (state.postgaraUploadsSubfolder) {
        const back = document.createElement('div');
        back.className = 'folder-item';
        back.innerHTML = '<div class="folder-title">← Indietro</div>';
        back.addEventListener('click', () => {
            const parent = state.postgaraUploadsSubfolder.includes('/')
                ? state.postgaraUploadsSubfolder.split('/').slice(0, -1).join('/')
                : '';
            renderPostGaraUploadsPicker(parent);
        });
        foldersWrap.appendChild(back);
    }

    subfolders.forEach((sf) => {
        const item = document.createElement('div');
        item.className = 'folder-item';
        item.innerHTML = `<div class="folder-title">📁 ${sf.name}</div>`;
        item.addEventListener('click', () => {
            const nextSubfolder = state.postgaraUploadsSubfolder
                ? `${state.postgaraUploadsSubfolder}/${sf.name}`
                : sf.name;
            renderPostGaraUploadsPicker(nextSubfolder);
        });
        foldersWrap.appendChild(item);
    });

    if (!subfolders.length) {
        const emptyFolders = document.createElement('div');
        emptyFolders.className = 'folder-item muted';
        emptyFolders.textContent = `Cartella corrente: ${folderPath}`;
        foldersWrap.appendChild(emptyFolders);
    }

    galleryWrap.innerHTML = '';
    if (!imageFiles.length) {
        galleryWrap.innerHTML = '<div class="muted">Nessuna immagine nella cartella selezionata.</div>';
        return;
    }

    imageFiles.forEach((file) => {
        const encodedFolder = folderPath.split('/').map(encodeURIComponent).join('/');
        const imgUrl = `${encodedFolder}/${encodeURIComponent(file)}`;

        const card = document.createElement('div');
        card.className = 'picker-img-card';

        const thumb = document.createElement('img');
        thumb.src = imgUrl;
        thumb.alt = file;
        thumb.loading = 'lazy';
        thumb.decoding = 'async';
        thumb.className = 'picker-img-thumb';

        const name = document.createElement('div');
        name.className = 'picker-img-name';
        name.textContent = file;

        card.appendChild(thumb);
        card.appendChild(name);

        card.addEventListener('click', async () => {
            if (state.postgaraPickerTeamIdx === null) return;
            try {
                await postgara.setImageFromUploads(state.postgaraPickerTeamIdx, imgUrl);
                closeImgPickerModal();
            } catch (err) {
                postgara.showError('❌ Errore selezione immagine uploads: ' + err.message);
            }
        });

        galleryWrap.appendChild(card);
    });
}

async function loadMediaData() {
    try {
        const data = await apiGet('api/media.php');
        if (data && Array.isArray(data.images) && data.images.length > 0) {
            state.mediaImages = data.images;
        }
        if (data && Array.isArray(data.categories) && data.categories.length > 0) {
            state.mediaCategories = data.categories;
        }
    } catch (e) {
        console.warn('apiGet media.php non riuscito o ambiente statico:', e);
    }

    loadCustomImagesFromStorage();
    ensureCategoriesFromImages();

    renderH2Cards();
    renderLibraryGallery();
    updatePreview();
}

function renderLibraryGallery() {
    const galleryWrap = document.getElementById('b-library-gallery');
    if (!galleryWrap) return;
    galleryWrap.innerHTML = '';

    if (!state.mediaImages || state.mediaImages.length === 0) {
        galleryWrap.innerHTML = '<div class="muted" style="grid-column: 1/-1; text-align: center; padding: 20px;">Nessuna immagine presente in libreria.</div>';
        return;
    }

    state.mediaImages.forEach(img => {
        const item = document.createElement('div');
        item.style.position = 'relative';
        item.style.border = '1px solid #333';
        item.style.borderRadius = '6px';
        item.style.overflow = 'hidden';
        item.style.background = '#222';
        item.style.padding = '5px';
        
        const imgEl = document.createElement('img');
        imgEl.src = img.url;
        imgEl.loading = 'lazy';
        imgEl.decoding = 'async';
        imgEl.style.width = '100%';
        imgEl.style.height = '90px';
        imgEl.style.objectFit = 'cover';
        imgEl.style.display = 'block';
        imgEl.style.borderRadius = '4px';
        item.appendChild(imgEl);

        const nameEl = document.createElement('div');
        nameEl.textContent = img.file;
        nameEl.style.fontSize = '0.7rem';
        nameEl.style.color = '#ccc';
        nameEl.style.padding = '4px 0 0 0';
        nameEl.style.whiteSpace = 'nowrap';
        nameEl.style.overflow = 'hidden';
        nameEl.style.textOverflow = 'ellipsis';
        item.appendChild(nameEl);

        galleryWrap.appendChild(item);
    });
}

async function loadBrowserFolders() {
    const data = await apiGet('api/file-browser.php?action=folders');
    state.browserFolders = data.folders || [];
}

async function loadAndRenderUploadsBrowser(subfolder) {
    const url = `api/file-browser.php?action=uploads-browse&subfolder=${encodeURIComponent(subfolder)}`;
    const data = await apiGet(url);
    renderUploadsBrowser(data.subfolder || subfolder, data.subfolders || [], data.files || []);
}

function renderUploadsBrowser(currentSubfolder, subfolders, files) {
    const wrap = document.getElementById('folder-modal-list');
    if (!wrap) return;
    wrap.innerHTML = '';

    state.browserCurrentSubfolder = currentSubfolder || '';

    const currentLabel = document.getElementById('folder-modal-current');
    if (currentLabel) {
        currentLabel.textContent = `Cartella corrente: ${state.browserCurrentSubfolder ? `uploads/${state.browserCurrentSubfolder}` : 'uploads'}`;
    }

    const imageExts = new Set(['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
    const isImage = f => imageExts.has(f.split('.').pop().toLowerCase());

    const folderPath = currentSubfolder ? `uploads/${currentSubfolder}` : 'uploads';

    if (currentSubfolder) {
        const back = document.createElement('div');
        back.className = 'folder-item folder-item-back';
        const t = document.createElement('div');
        t.className = 'folder-title';
        t.textContent = '\u2190 Indietro';
        back.appendChild(t);
        back.addEventListener('click', () => {
            const parent = currentSubfolder.includes('/')
                ? currentSubfolder.split('/').slice(0, -1).join('/')
                : '';
            loadAndRenderUploadsBrowser(parent);
        });
        wrap.appendChild(back);
    }

    subfolders.forEach(sf => {
        const item = document.createElement('div');
        item.className = 'folder-item folder-item-dir';
        const t = document.createElement('div');
        t.className = 'folder-title';
        t.textContent = `\uD83D\uDCC1 ${sf.name}`;
        item.appendChild(t);
        item.addEventListener('click', () => {
            const nextSubfolder = currentSubfolder ? `${currentSubfolder}/${sf.name}` : sf.name;
            loadAndRenderUploadsBrowser(nextSubfolder);
        });
        wrap.appendChild(item);
    });

    const imgFiles = files.filter(isImage);
    const otherFiles = files.filter(f => !isImage(f));

    if (imgFiles.length) {
        const grid = document.createElement('div');
        grid.className = 'uploads-photo-grid';

        imgFiles.forEach(file => {
            const parts = [folderPath, file].map(encodeURIComponent);
            const imgUrl = parts.join('/');

            const card = document.createElement('div');
            card.className = 'uploads-photo-item';

            const img = document.createElement('img');
            img.src = imgUrl;
            img.alt = file;
            img.loading = 'lazy';

            const name = document.createElement('div');
            name.className = 'uploads-photo-name';
            name.textContent = file;

            card.appendChild(img);
            card.appendChild(name);
            card.addEventListener('click', async () => {
                await selectBrowserFolder(folderPath);
                closeFolderModal();
            });
            grid.appendChild(card);
        });

        wrap.appendChild(grid);
    }

    otherFiles.forEach(file => {
        const item = document.createElement('div');
        item.className = 'folder-item folder-item-file';
        const t = document.createElement('div');
        t.className = 'folder-title';
        t.textContent = file;
        item.appendChild(t);
        item.addEventListener('click', async () => {
            await selectBrowserFolder(folderPath);
            closeFolderModal();
        });
        wrap.appendChild(item);
    });

    if (!subfolders.length && !files.length) {
        const empty = document.createElement('div');
        empty.className = 'folder-item muted';
        empty.textContent = 'Nessun contenuto.';
        wrap.appendChild(empty);
    }
}

async function selectBrowserFolder(folder) {
    state.browserSelectedFolder = folder;
    const data = await apiGet(`api/file-browser.php?action=files&folder=${encodeURIComponent(folder)}`);
    state.browserFiles = data.files || [];

    const info = document.getElementById('b-selected-folder');
    if (info) info.textContent = `Cartella selezionata: ${folder}`;

    renderBrowserFiles();

    const area = document.getElementById('b-file-content');
    if (area) area.value = '';
}

function renderBrowserFiles() {
    return;
}

function openFolderModal() {
    const modal = document.getElementById('folder-modal');
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
    loadAndRenderUploadsBrowser(state.browserCurrentSubfolder || '');
}

function closeFolderModal() {
    const modal = document.getElementById('folder-modal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
}

async function uploadImagesLocally(files) {
    const fileArray = Array.from(files);
    const newMediaItems = [];

    for (let i = 0; i < fileArray.length; i++) {
        const file = fileArray[i];
        const dataUrl = await new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = e => resolve(e.target.result);
            reader.onerror = e => reject(e);
            reader.readAsDataURL(file);
        }).catch(() => null);

        if (!dataUrl) continue;

        const token = 'img_local_' + Date.now() + '_' + i + '_' + Math.random().toString(36).substr(2,4);
        const mediaObj = {
            token: token,
            file: file.name,
            name: file.name,
            url: dataUrl,
            folder: 'Upload PC',
            category: 'Locale'
        };
        newMediaItems.push(mediaObj);
        state.mediaImages.unshift(mediaObj);
    }

    saveCustomImagesToStorage();
    ensureCategoriesFromImages();

    if (newMediaItems.length > 0) {
        autoAssignImagesToH2s(newMediaItems.map(m => ({ url: m.url, name: m.file, token: m.token })));
    }

    renderH2Cards();
    renderLibraryGallery();
    updatePreview();

    const out = document.getElementById('publish-result') || document.getElementById('mod-b-sync-status');
    if (out) {
        out.textContent = `✅ Caricate ${newMediaItems.length} immagini da PC/telefono. Assegnate agli H2.`;
        if (out.style) out.style.color = '#27ae60';
    }
}

async function uploadImagesToSelectedFolder(files) {
    if (!files || !files.length) return;

    const isStaticEnv = location.hostname.includes('github.io') || location.protocol === 'file:';
    if (isStaticEnv) {
        await uploadImagesLocally(files);
        return;
    }

    try {
        const fallbackFolder = state.browserCurrentSubfolder ? `uploads/${state.browserCurrentSubfolder}` : 'uploads';
        const folder = state.browserSelectedFolder || fallbackFolder;

        const formData = new FormData();
        formData.append('folder', folder);
        Array.from(files).forEach(file => formData.append('images[]', file));

        const res = await fetch('api/file-browser.php?action=upload-images', {
            method: 'POST',
            body: formData,
        });

        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.ok) {
            throw new Error(data.message || 'Upload immagini non riuscito');
        }

        await loadMediaData();

        if (data.uploaded && data.uploaded.length > 0) {
            autoAssignImagesToH2s(data.uploaded);
        }

        await selectBrowserFolder(folder).catch(() => {});

        const out = document.getElementById('publish-result');
        if (out) {
            out.textContent = `Caricate ${data.uploaded?.length || 0} immagini in ${folder}. Assegnate automaticamente agli H2.`;
        }
    } catch (err) {
        console.warn('Fallback upload locale:', err);
        await uploadImagesLocally(files);
    }
}

function autoAssignImagesToH2s(uploadedImages) {
    const h2Count = state.reviewH2Titles.length;

    uploadedImages.forEach((uploadedImg) => {
        const imgToken = uploadedImg.token || state.mediaImages.find(img =>
            img.url === uploadedImg.url || img.file === uploadedImg.name || img.file === uploadedImg.file
        )?.token;

        if (!imgToken) return;

        let h2Idx = -1;
        for (let i = 0; i < h2Count; i++) {
            if (!state.h2ImageMap[String(i)]) {
                h2Idx = i;
                break;
            }
        }

        if (h2Idx >= 0 && h2Idx < h2Count) {
            state.h2ImageMap[String(h2Idx)] = imgToken;
        }
    });

    renderH2Cards();
    updatePreview();
    queueAutoSave();
}

async function openUploadFolderModal() {
    const modal = document.getElementById('upload-folder-modal');
    if (!modal) return;

    state.uploadFolderPending = true;
    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');

    await loadAndRenderUploadFolderBrowser('');
}

function closeUploadFolderModal() {
    const modal = document.getElementById('upload-folder-modal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
    state.uploadFolderPending = false;
    state.uploadFolderSelected = '';
}

async function loadAndRenderUploadFolderBrowser(subfolder) {
    const url = `api/file-browser.php?action=uploads-browse&subfolder=${encodeURIComponent(subfolder)}`;
    const data = await apiGet(url);
    renderUploadFolderBrowser(data.subfolder || subfolder, data.subfolders || []);
}

function renderUploadFolderBrowser(currentSubfolder, subfolders) {
    const wrap = document.getElementById('upload-folder-list');
    if (!wrap) return;
    wrap.innerHTML = '';

    const currentLabel = document.getElementById('upload-folder-current');
    if (currentLabel) {
        const folderPath = currentSubfolder ? `uploads/${currentSubfolder}` : 'uploads';
        currentLabel.textContent = `Cartella corrente: ${folderPath}`;
    }

    const folderPath = currentSubfolder ? `uploads/${currentSubfolder}` : 'uploads';

    state.uploadFolderCurrentSubfolder = currentSubfolder || '';
    state.uploadFolderSelected = folderPath;

    if (currentSubfolder) {
        const back = document.createElement('div');
        back.className = 'folder-item folder-item-back';
        const t = document.createElement('div');
        t.className = 'folder-title';
        t.textContent = '← Indietro';
        back.appendChild(t);
        back.addEventListener('click', () => {
            const parent = currentSubfolder.includes('/')
                ? currentSubfolder.split('/').slice(0, -1).join('/')
                : '';
            loadAndRenderUploadFolderBrowser(parent);
        });
        wrap.appendChild(back);
    }

    subfolders.forEach(sf => {
        const item = document.createElement('div');
        item.className = 'folder-item folder-item-dir';
        const t = document.createElement('div');
        t.className = 'folder-title';
        t.textContent = `📁 ${sf.name}`;
        item.appendChild(t);
        item.addEventListener('click', () => {
            const nextSubfolder = currentSubfolder ? `${currentSubfolder}/${sf.name}` : sf.name;
            loadAndRenderUploadFolderBrowser(nextSubfolder);
        });
        wrap.appendChild(item);
    });

    if (!subfolders.length) {
        const empty = document.createElement('div');
        empty.className = 'folder-item muted';
        empty.textContent = `Selezionare questa cartella per l'upload: ${folderPath}`;
        wrap.appendChild(empty);
    }
}

async function createNewUploadFolder(folderName) {
    if (!folderName || !folderName.trim()) {
        throw new Error('Inserisci il nome della cartella');
    }

    const currentPath = state.uploadFolderCurrentSubfolder || '';
    const parentFolder = currentPath ? `uploads/${currentPath}` : 'uploads';

    const formData = new FormData();
    formData.append('parent_folder', parentFolder);
    formData.append('folder_name', folderName.trim());

    const res = await fetch('api/file-browser.php?action=create-folder', {
        method: 'POST',
        body: formData,
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data.ok) {
        throw new Error(data.message || 'Creazione cartella non riuscita');
    }

    const resultDiv = document.getElementById('upload-new-folder-result');
    if (resultDiv) {
        resultDiv.textContent = `✓ Cartella "${folderName}" creata con successo!`;
        resultDiv.style.display = 'block';
        resultDiv.className = 'notice notice-ok';
    }

    const input = document.getElementById('upload-new-folder-name');
    if (input) input.value = '';

    setTimeout(() => {
        loadAndRenderUploadFolderBrowser(state.uploadFolderCurrentSubfolder || '');
        if (resultDiv) resultDiv.style.display = 'none';
    }, 1500);
}

async function loadLatestXImage() {
    const btn = document.getElementById('b-load-x-latest-btn');
    const teamSelect = document.getElementById('b-x-team-select');
    const statusEl = document.getElementById('b-x-latest-status');
    const previewEl = document.getElementById('b-x-latest-preview');
    const imageEl = document.getElementById('b-x-latest-image');
    const linkEl = document.getElementById('b-x-latest-link');

    if (!btn || !teamSelect) return;
    const team = teamSelect.value;
    const selectedLabel = teamSelect.options[teamSelect.selectedIndex]?.text || 'team';
    if (!team) {
        if (statusEl) statusEl.textContent = '⚠️ Seleziona prima un team.';
        teamSelect.focus();
        return;
    }
    btn.disabled = true;
    btn.textContent = '⏳ Caricamento da X...';
    if (statusEl) statusEl.textContent = 'Ricerca dell’ultima immagine pubblicata...';

    try {
        const response = await fetch(`api/x-latest-image.php?team=${encodeURIComponent(team)}`, { cache: 'no-store' });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.ok) {
            throw new Error(data.error || `Errore X (${response.status})`);
        }
        if (!data.found || !data.image_url) {
            throw new Error(data.message || 'Nessuna immagine disponibile su Twitter/X.');
        }

        const postId = String(data.post_url || '').match(/status\/(\d+)/)?.[1] || Date.now().toString();
        const teamLabel = String(data.team_label || selectedLabel);
        const token = `img_local_x_${team}_${postId}`;
        const mediaItem = {
            token,
            url: String(data.image_url),
            file: `${team}-${postId}.jpg`,
            folder: 'Twitter Team F1',
            category: teamLabel,
        };

        state.mediaImages = (state.mediaImages || []).filter(img => img.token !== token);
        state.mediaImages.unshift(mediaItem);
        ensureCategoriesFromImages();
        saveCustomImagesToStorage();
        renderH2Cards();
        updatePreview();

        if (imageEl) imageEl.src = mediaItem.url;
        if (linkEl) {
            linkEl.href = data.post_url || `https://x.com/${encodeURIComponent(data.username || '')}`;
            linkEl.style.display = 'inline-block';
        }
        previewEl?.classList.remove('hidden');
        if (statusEl) statusEl.textContent = `✅ Ultima foto di ${teamLabel} caricata e disponibile nella selezione H2.`;
    } catch (error) {
        if (statusEl) statusEl.textContent = `❌ ${error.message}`;
        previewEl?.classList.add('hidden');
    } finally {
        btn.disabled = false;
        btn.textContent = '𝕏 Carica ultima foto';
    }
}

function initializeModuleB() {
    loadCustomImagesFromStorage();
    renderBrowserFiles();
    renderH2Cards();
    renderLibraryGallery();

    document.getElementById('b-load-x-latest-btn')?.addEventListener('click', loadLatestXImage);

    const info = document.getElementById('b-selected-folder');
    if (info) {
        info.textContent = state.browserSelectedFolder
            ? `Cartella selezionata: ${state.browserSelectedFolder}`
            : 'Cartella selezionata: uploads';
    }

    // Modal listeners
    document.getElementById('img-picker-close')?.addEventListener('click', closeImgPickerModal);
    document.getElementById('upload-folder-cancel')?.addEventListener('click', closeUploadFolderModal);
    document.getElementById('upload-folder-confirm')?.addEventListener('click', () => {
        closeUploadFolderModal();
        document.getElementById('b-upload-images-input')?.click();
    });
    document.getElementById('upload-new-folder-btn')?.addEventListener('click', () => {
        const input = document.getElementById('upload-new-folder-name');
        if (input && input.value) {
            createNewUploadFolder(input.value).catch(err => {
                const resEl = document.getElementById('upload-new-folder-result');
                if (resEl) {
                    resEl.textContent = '❌ Errore: ' + err.message;
                    resEl.className = 'notice notice-warn';
                    resEl.style.display = 'block';
                }
            });
        }
    });

    // ScrapingBee key
    const keyInput  = document.getElementById('b-scrapingbee-key');
    const statusEl  = document.getElementById('b-scrapingbee-status');

    const localKey = localStorage.getItem('fp_scrapingbee_key') || '';
    if (keyInput && localKey) {
        keyInput.value = localKey;
        if (statusEl) { statusEl.textContent = '✅ Chiave attiva'; statusEl.style.color = '#4ade80'; }
    } else if (keyInput) {
        fetch('api/settings.php')
            .then(r => r.json()).catch(() => ({}))
            .then(data => {
                const srv = data?.settings?.scrapingbee_key ?? '';
                if (srv === '***set***') {
                    if (statusEl) { statusEl.textContent = '✅ Chiave attiva (server)'; statusEl.style.color = '#4ade80'; }
                    if (keyInput) keyInput.placeholder = '••••••••••••••••••••••••• (salvata sul server)';
                }
            });
    }

    const saveBtn = document.getElementById('b-scrapingbee-save');
    if (saveBtn && keyInput) {
        saveBtn.addEventListener('click', async () => {
            const key = keyInput.value.trim();
            if (key) localStorage.setItem('fp_scrapingbee_key', key);
            else localStorage.removeItem('fp_scrapingbee_key');
            try {
                await fetch('api/settings.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ scrapingbee_key: key }),
                });
            } catch(e) {}
            if (statusEl) {
                statusEl.textContent = key ? '✅ Chiave salvata!' : '🗑️ Chiave rimossa';
                statusEl.style.color = key ? '#4ade80' : '#f87171';
            }
        });
    }

    const teamHubBtn = document.getElementById('b-download-team-hubs-btn');
    if (teamHubBtn) {
        teamHubBtn.addEventListener('click', downloadFromTeamHubs);
    }
}

window.refreshH2SelectionState = refreshH2SelectionState;
window.openImgPickerModal = openImgPickerModal;
window.closeImgPickerModal = closeImgPickerModal;
window.uploadImagesToSelectedFolder = uploadImagesToSelectedFolder;

async function downloadFromTeamHubs() {
    const isStaticEnv = location.hostname.includes('github.io') || location.protocol === 'file:';
    const statusEl  = document.getElementById('b-team-hub-status');
    const progressEl = document.getElementById('b-team-hub-progress');
    const barEl     = document.getElementById('b-team-hub-bar');
    const logEl     = document.getElementById('b-team-hub-log');
    const btn       = document.getElementById('b-download-team-hubs-btn');

    if (isStaticEnv) {
        if (statusEl) {
            statusEl.textContent = '⚠️ Download dagli hub ufficiali non disponibile su GitHub Pages (richiede backend PHP). Carica le immagini da PC/telefono.';
            statusEl.style.color = '#f87171';
        }
        return;
    }

    const checkboxes = document.querySelectorAll('#b-team-hub-checkboxes input[name="team_hub"]:checked');
    const selectedTeams = Array.from(checkboxes).map(cb => cb.value);

    if (!selectedTeams.length) {
        if (statusEl) statusEl.textContent = '⚠️ Seleziona almeno un team.';
        return;
    }

    btn.disabled = true;
    btn.textContent = '⏳ Download in corso...';
    if (statusEl)   statusEl.textContent = `Connessione a ${selectedTeams.length} hub team...`;
    if (progressEl) progressEl.style.display = 'block';
    if (barEl)      barEl.style.width = '5%';
    if (logEl)      logEl.innerHTML = '';

    const addLog = (msg) => {
        if (!logEl) return;
        const line = document.createElement('div');
        line.textContent = msg;
        logEl.appendChild(line);
        logEl.scrollTop = logEl.scrollHeight;
    };

    try {
        addLog(`→ Avvio download: ${selectedTeams.join(', ')}`);
        if (barEl) barEl.style.width = '20%';

        const sbKey = localStorage.getItem('fp_scrapingbee_key') || '';
        if (sbKey && statusEl) {
            statusEl.textContent = `Connessione con ScrapingBee attiva 🐝...`;
        }

        const res = await fetch('api/team-hub-sync.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ teams: selectedTeams, limit: 30, scrapingbee_key: sbKey }),
        });

        if (barEl) barEl.style.width = '70%';

        const data = await res.json().catch(() => ({}));

        if (barEl) barEl.style.width = '100%';

        if (!res.ok || !data.ok) {
            throw new Error(data.message || `Errore server: ${res.status}`);
        }

        let totalSaved = 0;
        Object.entries(data.results || {}).forEach(([teamId, r]) => {
            if (r.ok) {
                addLog(`✅ ${r.label}: trovate ${r.found}, salvate ${r.saved} → ${r.folder}`);
                totalSaved += r.saved;
            } else {
                addLog(`❌ ${teamId}: ${r.error || 'Errore sconosciuto'}`);
            }
        });

        if (statusEl) {
            statusEl.textContent = `✅ Download completato: ${totalSaved} immagini totali scaricate.`;
            statusEl.style.color = '#4ade80';
        }

        if (totalSaved > 0) {
            await loadMediaData();
        }

    } catch (err) {
        if (statusEl) {
            statusEl.textContent = `❌ Errore: ${err.message}`;
            statusEl.style.color = '#f87171';
        }
        addLog(`ERRORE: ${err.message}`);
    } finally {
        btn.disabled = false;
        btn.textContent = '🏁 Scarica da Hub Team';
        setTimeout(() => {
            if (barEl) barEl.style.width = '0%';
        }, 3000);
    }
}
