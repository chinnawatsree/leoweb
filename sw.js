const CACHE_NAME = 'ups-monitor-v1';
const STATIC_ASSETS = [
  '/',
  '/css/main.css',
  '/data_renew.html',
  '/ups_detail.html',
  'https://cdn.tailwindcss.com'
];

// ติดตั้ง Service Worker
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        return cache.addAll(STATIC_ASSETS);
      })
      .then(() => self.skipWaiting())
  );
});

// เปิดใช้งาน Service Worker ใหม่
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames
          .filter((name) => name !== CACHE_NAME)
          .map((name) => caches.delete(name))
      );
    })
    .then(() => self.clients.claim())
  );
});

// จัดการคำขอ fetch
self.addEventListener('fetch', (event) => {
  // ไม่แคช API calls
  if (event.request.url.includes('data.php')) {
    event.respondWith(
      fetch(event.request)
        .catch(() => {
          return caches.match(event.request);
        })
    );
    return;
  }

  // ใช้ Cache-First strategy สำหรับไฟล์ static
  event.respondWith(
    caches.match(event.request)
      .then((response) => {
        if (response) {
          return response;
        }

        return fetch(event.request)
          .then((response) => {
            // ตรวจสอบว่าเป็น valid response
            if (!response || response.status !== 200 || response.type !== 'basic') {
              return response;
            }

            // แคชสำเนาของ response
            const responseToCache = response.clone();
            caches.open(CACHE_NAME)
              .then((cache) => {
                cache.put(event.request, responseToCache);
              });

            return response;
          })
          .catch(() => {
            // ถ้าไม่สามารถเชื่อมต่อได้ ให้แสดงหน้า offline
            if (event.request.mode === 'navigate') {
              return caches.match('/offline.html');
            }
          });
      })
  );
});
