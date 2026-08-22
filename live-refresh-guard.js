/* Formula Paddock Live Timing refresh guard
 * Loaded BEFORE dashboard.js.
 * Prevents browser/proxy caching of live API GET requests and provides
 * a conservative watchdog if the dashboard polling stops completely.
 */
(function () {
    'use strict';

    const DATA_EXT_RE = /\.(?:js|mjs|css|png|jpe?g|gif|webp|svg|ico|woff2?|ttf|map)(?:$|\?)/i;
    const STALE_AFTER_MS = 25000;
    const MIN_RELOAD_GAP_MS = 60000;
    const WATCH_EVERY_MS = 3000;

    function shouldBust(rawUrl, method) {
        if (String(method || 'GET').toUpperCase() !== 'GET') return false;
        try {
            const url = new URL(String(rawUrl), window.location.href);
            if (url.origin !== window.location.origin) return false;
            if (DATA_EXT_RE.test(url.pathname)) return false;
            return true;
        } catch (_) {
            return false;
        }
    }

    function bustUrl(rawUrl) {
        try {
            const url = new URL(String(rawUrl), window.location.href);
            url.searchParams.set('_fp_live_ts', String(Date.now()));
            return url.toString();
        } catch (_) {
            return rawUrl;
        }
    }

    // ---- fetch() no-cache guard ----
    if (typeof window.fetch === 'function') {
        const nativeFetch = window.fetch.bind(window);
        window.fetch = function (input, init) {
            const opts = Object.assign({}, init || {});
            const isRequest = typeof Request !== 'undefined' && input instanceof Request;
            const rawUrl = isRequest ? input.url : input;
            const method = opts.method || (isRequest ? input.method : 'GET');

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
                try {
                    input = new Request(freshUrl, input);
                } catch (_) {
                    input = freshUrl;
                }
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
        console.warn('[FP Live] Aggiornamento fermo: ricarico il dashboard con cache-buster.');

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

    console.info('[FP Live] Refresh guard attivo.');
})();
