const CACHE_NAME = 'contable-pwa-v3';
const ASSETS = [
  './style.css',
  './manifest.json',
  './assets/icon-192.png',
  './assets/icon-512.png'
];

// Instalación: Almacenar recursos estáticos
self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(ASSETS))
  );
  self.skipWaiting();
});

// Activación: Limpieza de cachés antiguas
self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Estrategia Fetch: Network-First para archivos PHP/consultas, Cache-First para estáticos
self.addEventListener('fetch', (e) => {
  const requestUrl = new URL(e.request.url);

  // Peticiones dinámicas (archivos .php, llamadas con parámetros o POST) -> Network First
  if (e.request.method === 'POST' || requestUrl.pathname.endsWith('.php') || requestUrl.pathname === '/' || requestUrl.search) {
    e.respondWith(
      fetch(e.request).catch(() => caches.match(e.request))
    );
    return;
  }

  // Recurso estático (CSS, imágenes, JS estático) -> Cache First
  e.respondWith(
    caches.match(e.request).then((res) => res || fetch(e.request))
  );
});