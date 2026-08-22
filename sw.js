/* Formula Paddock root Service Worker.
 * Kept intentionally minimal: dashboard.js registers /sw.js with root scope.
 * This file must be served directly with HTTP 200 and must not redirect.
 */

self.addEventListener('install', () => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});
