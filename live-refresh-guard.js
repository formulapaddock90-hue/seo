/* Formula Paddock Live Timing compatibility + refresh guard
 * Loaded BEFORE dashboard.js.
 * v2.2: bypasses the missing WordPress update-standings REST route,
 * falls back to existing Formula Paddock live bridges, disables caching,
 * and protects loadSummary from standalone-page DOM mismatches.
 */
(function () {
    'use strict';

    const DATA_EXT_RE = /\.(?:js|mjs|css|png|jpe?g|gif|webp|svg|ico|woff2?|ttf|map)(?:$|\?)/i;
    const UPDATE_ROUTE_RE = /\/wp-json\/undercutf1\/v1\/update-standings\/?$/i;
    const STALE_AFTER_MS = 25000;
    const MIN_RELOAD_GAP_MS = 60000;
    const WATCH_EVERY_MS = 3000;
    const BRIDGE_SOURCES = ['/live-data.php', '/seo/api-classifica.php'];

    const diagnostics = window.FPLiveBridgeDiagnostics = window.FPLiveBridgeDiagnostics || {
        source: '',
        lastSuccessAt: 0,
        lastChangeAt: 0,
        lastFingerprint: '',
        missingDom: []
    };

    function asUrl(rawUrl) {
        try { return new URL(String(rawUrl), window.location.href); }
        catch (_) { return null; }
    }

    function isUpdateRoute(rawUrl, method) {
        if (String(method || 'GET').toUpperCase() !== 'GET') return false;
        const url = asUrl(rawUrl);
        return Boolean(url && url.origin === window.location.origin && UPDATE_ROUTE_RE.test(url.pathname));
    }

    function shouldBust(rawUrl, method) {
        if (String(method || 'GET').toUpperCase() !== 'GET') return false;
        const url = asUrl(rawUrl);
        if (!url || url.origin !== window.location.origin) return false;
        if (DATA_EXT_RE.test(url.pathname)) return false;
        return true;
    }

    function bustUrl(rawUrl) {
        const url = asUrl(rawUrl);
        if (!url) return rawUrl;
        url.searchParams.set('_fp_live_ts', String(Date.now()));
        return url.toString();
    }

    function firstArray(data) {
        if (Array.isArray(data)) return data;
        if (!data || typeof data !== 'object') return [];
        const candidates = [
            data.drivers, data.standings, data.classification, data.rows,
            data.data,
            data.data?.drivers, data.data?.standings, data.data?.classification, data.data?.rows,
            data.live?.drivers, data.live?.standings, data.live?.classification, data.live?.rows,
            data.summary?.drivers, data.summary?.standings, data.summary?.rows
        ];
        return candidates.find(Array.isArray) || [];
    }

    function pick(raw, keys, fallback = '') {
        for (const key of keys) {
            if (raw && raw[key] !== undefined && raw[key] !== null && raw[key] !== '') return raw[key];
        }
        return fallback;
    }

    function normalizeDriver(raw, index) {
        if (!raw || typeof raw !== 'object') return null;

        const position = pick(raw, ['position', 'Position', 'Posizione', 'pos'], index + 1);
        const number = pick(raw, [
            'driver_number', 'driverNumber', 'number', 'racing_number', 'racingNumber',
            'RacingNumber', 'car_number', 'carNumber', 'Numero Gara', 'N. Gara'
        ], '');
        const name = String(pick(raw, [
            'full_name', 'fullName', 'driver_name', 'driverName', 'broadcast_name', 'broadcastName',
            'BroadcastName', 'name_acronym', 'tla', 'name', 'driver', 'Pilota'
        ], '')).replace(/\s+/g, ' ').trim();
        const team = String(pick(raw, ['team_name', 'teamName', 'TeamName', 'team', 'Team'], '')).trim();
        const bestLap = pick(raw, ['best_lap', 'bestLap', 'Best Lap', 'best'], '');
        const lastLap = pick(raw, ['last_lap', 'lastLap', 'Ultimo Giro'], '');
        const laps = pick(raw, ['numberOfLaps', 'NumberOfLaps', 'total_laps', 'lapCount', 'lap_count', 'laps', 'Giri'], 0);
        const gap = pick(raw, ['gap', 'Gap', 'interval', 'time', 'Tempo'], '');

        if (!name && !number) return null;

        return Object.assign({}, raw, {
            position,
            Position: position,
            driver_number: number,
            driverNumber: number,
            number,
            racingNumber: number,
            racing_number: number,
            full_name: name,
            fullName: name,
            driver_name: name,
            driverName: name,
            broadcast_name: name,
            name: name,
            team_name: team,
            teamName: team,
            team,
            best_lap: bestLap,
            bestLap,
            last_lap: lastLap,
            lastLap,
            total_laps: laps,
            numberOfLaps: laps,
            laps,
            gap
        });
    }

    function normalizePayload(raw, source) {
        const sourceRows = firstArray(raw);
        const drivers = sourceRows.map(normalizeDriver).filter(Boolean);
        if (!drivers.length) return null;

        const payload = raw && typeof raw === 'object' && !Array.isArray(raw)
            ? Object.assign({}, raw)
            : {};

        payload.success = payload.success !== false;
        payload.ok = payload.ok !== false;
        payload.source = payload.source || source;
        payload.drivers = drivers;
        payload.standings = drivers;
        payload.classification = drivers;
        payload.rows = drivers;
        payload.count = Number(payload.count ?? payload.driver_count ?? drivers.length) || drivers.length;
        payload.driver_count = payload.count;
        payload.timestamp = payload.timestamp || payload.updated_at || payload.updatedAt || new Date().toISOString();
        payload.updated_at = payload.updated_at || payload.timestamp;
        payload.updatedAt = payload.updatedAt || payload.timestamp;

        if (payload.data && typeof payload.data === 'object' && !Array.isArray(payload.data)) {
            payload.data = Object.assign({}, payload.data, {
                drivers,
                standings: drivers,
                classification: drivers,
                rows: drivers
            });
        } else {
            payload.data = drivers;
        }

        return payload;
    }

    function fingerprintDrivers(drivers) {
        return drivers.map(d => [
            d.position, d.driver_number, d.full_name, d.gap,
            d.numberOfLaps, d.lastLap, d.bestLap
        ].join('|')).join('||');
    }

    function noteBridgePayload(payload, source) {
        const now = Date.now();
        const fingerprint = fingerprintDrivers(payload.drivers || []);
        const sourceChanged = diagnostics.source !== source;
        const dataChanged = fingerprint && diagnostics.lastFingerprint !== fingerprint;

        diagnostics.source = source;
        diagnostics.lastSuccessAt = now;

        if (sourceChanged) {
            console.info(`[FP Live] Route update-standings sostituita con ${source}.`);
        }
        if (dataChanged) {
            diagnostics.lastFingerprint = fingerprint;
            diagnostics.lastChangeAt = now;
            console.info(`[FP Live] Dati Live aggiornati: ${payload.driver_count || payload.drivers.length} piloti da ${source}.`);
        }
    }

    function jsonResponse(payload) {
        return new Response(JSON.stringify(payload), {
            status: 200,
            statusText: 'OK',
            headers: {
                'Content-Type': 'application/json; charset=utf-8',
                'Cache-Control': 'no-store, no-cache, must-revalidate'
            }
        });
    }

    async function fetchBridge(nativeFetch) {
        const errors = [];
        for (const source of BRIDGE_SOURCES) {
            try {
                const response = await nativeFetch(bustUrl(source), {
                    method: 'GET',
                    cache: 'no-store',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Cache-Control': 'no-cache, no-store, must-revalidate',
                        'Pragma': 'no-cache'
                    }
                });
                if (!response.ok) {
                    errors.push(`${source}: HTTP ${response.status}`);
                    continue;
                }
                const raw = await response.json();
                const payload = normalizePayload(raw, source);
                if (!payload) {
                    errors.push(`${source}: nessuna classifica valida`);
                    continue;
                }
                noteBridgePayload(payload, source);
                return jsonResponse(payload);
            } catch (error) {
                errors.push(`${source}: ${error?.message || error}`);
            }
        }

        const now = Date.now();
        if (!diagnostics.lastBridgeErrorAt || now - diagnostics.lastBridgeErrorAt > 15000) {
            diagnostics.lastBridgeErrorAt = now;
            console.error('[FP Live] Nessuna sorgente sostitutiva disponibile per update-standings:', errors.join(' | '));
        }

        return jsonResponse({
            success: false,
            ok: false,
            drivers: [], standings: [], classification: [], rows: [], data: [],
            count: 0,
            driver_count: 0,
            error: 'Live bridge non disponibile',
            details: errors
        });
    }

    // ---- fetch() route bridge + no-cache guard ----
    if (typeof window.fetch === 'function') {
        const nativeFetch = window.fetch.bind(window);
        window.fetch = function (input, init) {
            const opts = Object.assign({}, init || {});
            const isRequest = typeof Request !== 'undefined' && input instanceof Request;
            const rawUrl = isRequest ? input.url : input;
            const method = opts.method || (isRequest ? input.method : 'GET');

            // WordPress currently returns 404 for this route. Do not call it at all:
            // feed dashboard.js through the existing public Formula Paddock bridges.
            if (isUpdateRoute(rawUrl, method)) {
                return fetchBridge(nativeFetch);
            }

            if (!shouldBust(rawUrl, method)) {
                return nativeFetch(input, init);
            }

            const headers = new Headers(opts.headers || (isRequest ? input.headers : undefined));
            headers.set('Cache-Control', 'no-cache, no-store, must-revalidate');
            headers.set('Pragma', 'no-cache');
            opts.cache = 'no-store';
            opts.headers = headers;

            const freshUrl = bustUrl(rawUrl);
            if (isRequest) {
                try { input = new Request(freshUrl, input); }
                catch (_) { input = freshUrl; }
            } else {
                input = freshUrl;
            }

            return nativeFetch(input, opts);
        };
    }

    // ---- XMLHttpRequest no-cache guard ----
    if (typeof XMLHttpRequest !== 'undefined') {
        const nativeOpen = XMLHttpRequest.prototype.open;
        const nativeSend = XMLHttpRequest.prototype.send;

        XMLHttpRequest.prototype.open = function (method, url) {
            const args = Array.prototype.slice.call(arguments);
            this.__fpLiveNoCache = shouldBust(url, method);
            if (this.__fpLiveNoCache) args[1] = bustUrl(url);
            return nativeOpen.apply(this, args);
        };

        XMLHttpRequest.prototype.send = function () {
            if (this.__fpLiveNoCache) {
                try { this.setRequestHeader('Cache-Control', 'no-cache, no-store, must-revalidate'); } catch (_) {}
                try { this.setRequestHeader('Pragma', 'no-cache'); } catch (_) {}
            }
            return nativeSend.apply(this, arguments);
        };
    }

    // ---- Standalone DOM compatibility for dashboard.js loadSummary() ----
    function calledFromDashboardSummary() {
        const stack = String(new Error().stack || '');
        return stack.includes('loadSummary') && stack.includes('dashboard.js');
    }

    function makeCompatNode(label) {
        const node = document.createElement('div');
        node.hidden = true;
        node.setAttribute('aria-hidden', 'true');
        node.dataset.fpLiveCompat = '1';
        node.dataset.fpLiveMissing = label;
        const parent = document.body || document.documentElement;
        if (parent) parent.appendChild(node);
        if (!diagnostics.missingDom.includes(label)) {
            diagnostics.missingDom.push(label);
            console.warn(`[FP Live] Nodo DOM richiesto da dashboard.js ma assente in live.html: ${label}. Creato fallback invisibile.`);
        }
        return node;
    }

    if (typeof Document !== 'undefined') {
        const nativeGetElementById = Document.prototype.getElementById;
        Document.prototype.getElementById = function (id) {
            const found = nativeGetElementById.call(this, id);
            if (found || !calledFromDashboardSummary()) return found;
            const node = makeCompatNode(`#${id}`);
            node.id = id;
            return node;
        };

        const nativeQuerySelector = Document.prototype.querySelector;
        Document.prototype.querySelector = function (selector) {
            const found = nativeQuerySelector.call(this, selector);
            if (found || !calledFromDashboardSummary()) return found;
            const node = makeCompatNode(String(selector));
            const idMatch = String(selector).match(/^#([A-Za-z][\w:-]*)$/);
            if (idMatch) node.id = idMatch[1];
            return node;
        };
    }

    // ---- Conservative dashboard watchdog ----
    let lastStamp = '';
    let lastProgressAt = Date.now();

    function dashboardLooksActive() {
        const driverCount = (document.getElementById('driver-count')?.textContent || '').trim();
        const sessionRunning = (document.getElementById('session-running')?.textContent || '').trim().toLowerCase();
        const trackStatus = (document.getElementById('track-status')?.textContent || '').trim().toLowerCase();
        return /\d+\s+pilot/i.test(driverCount)
            || /attiv|live|running|sessione/i.test(sessionRunning)
            || (!/nessun dato|offline/.test(trackStatus) && trackStatus !== '');
    }

    function watchdogTick() {
        const el = document.getElementById('updated-at');
        const stamp = (el?.textContent || '').trim();

        if (stamp && stamp !== '-' && stamp !== lastStamp) {
            lastStamp = stamp;
            lastProgressAt = Date.now();
            return;
        }

        if (document.visibilityState !== 'visible') return;
        if (!dashboardLooksActive()) return;
        if (Date.now() - lastProgressAt < STALE_AFTER_MS) return;

        const now = Date.now();
        const lastReloadAt = Number(sessionStorage.getItem('fpLiveWatchdogReloadAt') || 0);
        if (now - lastReloadAt < MIN_RELOAD_GAP_MS) return;

        sessionStorage.setItem('fpLiveWatchdogReloadAt', String(now));
        console.warn('[FP Live] Aggiornamento visuale fermo: ricarico il dashboard con cache-buster.');
        const url = new URL(window.location.href);
        url.searchParams.set('_fp_reload', String(now));
        window.location.replace(url.toString());
    }

    function startWatchdog() {
        window.setInterval(watchdogTick, WATCH_EVERY_MS);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startWatchdog, { once: true });
    } else {
        startWatchdog();
    }

    console.info('[FP Live] Refresh guard v2.2 attivo.');
})();
