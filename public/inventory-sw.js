// Minimal service worker: no offline caching.
// It exists only to satisfy installability requirements.

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

// No fetch handler => the browser uses the network as usual.
