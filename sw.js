const CACHE_NAME = 'foc-connect-v1';
const urlsToCache = [
    '/foc-connect/',
    '/foc-connect/dashboard.php',
    '/foc-connect/login.php',
    '/foc-connect/chat.php',
    '/foc-connect/materials.php',
    '/foc-connect/assignments.php',
    '/foc-connect/announcements.php',
    '/foc-connect/alumni-directory.php',
    '/foc-connect/job-board.php',
    '/foc-connect/mentorship.php',
    '/foc-connect/settings.php',
    '/foc-connect/manifest.json'
];

// Install service worker
self.addEventListener('install', event => {
    console.log('Service Worker installing...');
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('Caching files...');
                return cache.addAll(urlsToCache);
            })
    );
});

// Fetch from cache or network
self.addEventListener('fetch', event => {
    event.respondWith(
        caches.match(event.request)
            .then(response => {
                if (response) {
                    return response;
                }
                return fetch(event.request);
            })
    );
});

// Activate and clean old cache
self.addEventListener('activate', event => {
    console.log('Service Worker activating...');
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cache => {
                    if (cache !== CACHE_NAME) {
                        console.log('Deleting old cache:', cache);
                        return caches.delete(cache);
                    }
                })
            );
        })
    );
});