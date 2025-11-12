/* ElectroDomus PWA – Service Worker */
const CACHE_VERSION = 'v1.20.0';
const STATIC_CACHE = `electrodomus-static-${CACHE_VERSION}`;
const STATIC_ASSETS = [
  '/electrodomus/public/',
  '/electrodomus/public/index.php',
  '/electrodomus/public/dashboard.php',
  '/electrodomus/public/contenidos.php',
  '/electrodomus/public/simulaciones.php',
  '/electrodomus/public/evaluaciones.php',
  '/electrodomus/public/assets/css/style.css',
  // Simulaciones nuevas
  '/electrodomus/public/assets/js/sim-foco.js',
  '/electrodomus/public/assets/js/sim-tomacorriente.js',
  '/electrodomus/public/assets/js/sim-bomba.js',
  '/electrodomus/public/assets/js/sim-3way.js',
  // Página offline
  '/electrodomus/public/offline.html'
];

// Instalar: precache de estáticos
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then((cache) => cache.addAll(STATIC_ASSETS))
  );
  self.skipWaiting();
});

// Activar: limpieza de versiones viejas
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.map((k) =>
        (k.startsWith('electrodomus-static-') && k !== STATIC_CACHE) ? caches.delete(k) : null
      ))
    )
  );
  self.clients.claim();
});

/*
  Estrategia:
  - HTML/PHP: network-first (si falla => caché => offline.html)
  - CSS/JS/imagenes: cache-first
*/
self.addEventListener('fetch', (event) => {
  const req = event.request;
  const isHTML = req.headers.get('accept')?.includes('text/html');

  if (isHTML) {
    // network first
    event.respondWith(
      fetch(req)
        .then((res) => {
          const copy = res.clone();
          caches.open(STATIC_CACHE).then((c) => c.put(req, copy)).catch(()=>{});
          return res;
        })
        .catch(() => caches.match(req).then((r) => r || caches.match('/electrodomus/public/offline.html')))
    );
  } else {
    // assets: cache first
    event.respondWith(
      caches.match(req).then((cached) => cached || fetch(req).then((res) => {
        const copy = res.clone();
        caches.open(STATIC_CACHE).then((c) => c.put(req, copy)).catch(()=>{});
        return res;
      }))
    );
  }
});

// Permitir activación inmediata desde la página (para el banner de actualización)
self.addEventListener('message', (event) => {
  if (event?.data?.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});
