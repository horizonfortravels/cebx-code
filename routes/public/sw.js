/**
 * ═══════════════════════════════════════════════════════
 *  Shipping Gateway — Service Worker (PWA)
 * ═══════════════════════════════════════════════════════
 *
 *  استراتيجيات التخزين المؤقت:
 *  ─────────────────────────────
 *  1. Cache First    → الأصول الثابتة (CSS, JS, Icons, Fonts)
 *  2. Network First  → صفحات HTML (للحصول على أحدث البيانات)
 *  3. Stale While    → صور + API responses (سرعة مع تحديث خلفي)
 *  4. Offline Page   → صفحة بديلة عند عدم الاتصال
 */

const CACHE_VERSION = 'sg-v1.0.0';
const STATIC_CACHE  = `static-${CACHE_VERSION}`;
const DYNAMIC_CACHE = `dynamic-${CACHE_VERSION}`;
const IMAGE_CACHE   = `images-${CACHE_VERSION}`;

// ── الأصول الأساسية للتخزين المسبق ──
const PRECACHE_ASSETS = [
    '/',
    '/offline',
    '/css/app.css',
    '/manifest.json',
    '/icons/icon-192x192.png',
    '/icons/icon-512x512.png',
];

// ── الحد الأقصى للعناصر المخزنة ──
const MAX_DYNAMIC_ITEMS = 50;
const MAX_IMAGE_ITEMS   = 80;

// ══════════════════════════════════════
//  INSTALL — تثبيت وتخزين الأصول الأساسية
// ══════════════════════════════════════
self.addEventListener('install', (event) => {
    console.log('[SW] Installing...');
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => {
                console.log('[SW] Pre-caching static assets');
                return cache.addAll(PRECACHE_ASSETS);
            })
            .then(() => self.skipWaiting()) // تفعيل فوري
    );
});

// ══════════════════════════════════════
//  ACTIVATE — حذف الكاش القديم
// ══════════════════════════════════════
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating...');
    event.waitUntil(
        caches.keys()
            .then((keys) => {
                return Promise.all(
                    keys
                        .filter((key) => key !== STATIC_CACHE && key !== DYNAMIC_CACHE && key !== IMAGE_CACHE)
                        .map((key) => {
                            console.log('[SW] Removing old cache:', key);
                            return caches.delete(key);
                        })
                );
            })
            .then(() => self.clients.claim()) // التحكم بجميع الصفحات فوراً
    );
});

// ══════════════════════════════════════
//  FETCH — استراتيجيات الجلب
// ══════════════════════════════════════
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // تجاهل الطلبات غير HTTP/HTTPS
    if (!url.protocol.startsWith('http')) return;

    // تجاهل طلبات API (لا نريد تخزينها مؤقتاً)
    if (url.pathname.startsWith('/api/')) return;

    // تجاهل طلبات POST/PUT/DELETE
    if (request.method !== 'GET') return;

    // تجاهل طلبات livewire / broadcasting
    if (url.pathname.startsWith('/livewire/') || url.pathname.startsWith('/broadcasting/')) return;

    // ── استراتيجية الصور: Stale While Revalidate ──
    if (isImageRequest(request)) {
        event.respondWith(staleWhileRevalidate(request, IMAGE_CACHE, MAX_IMAGE_ITEMS));
        return;
    }

    // ── استراتيجية الأصول الثابتة: Cache First ──
    if (isStaticAsset(request)) {
        event.respondWith(cacheFirst(request, STATIC_CACHE));
        return;
    }

    // ── استراتيجية صفحات HTML: Network First ──
    if (request.headers.get('accept')?.includes('text/html')) {
        event.respondWith(networkFirst(request, DYNAMIC_CACHE));
        return;
    }

    // ── الباقي: Network First مع fallback ──
    event.respondWith(networkFirst(request, DYNAMIC_CACHE));
});

// ══════════════════════════════════════
//  استراتيجيات التخزين المؤقت
// ══════════════════════════════════════

/**
 * Cache First — جلب من الكاش أولاً، ثم الشبكة
 * مناسب للأصول الثابتة التي لا تتغير كثيراً
 */
async function cacheFirst(request, cacheName) {
    const cached = await caches.match(request);
    if (cached) return cached;

    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, response.clone());
        }
        return response;
    } catch (err) {
        console.warn('[SW] Cache First failed:', request.url);
        return caches.match('/offline');
    }
}

/**
 * Network First — جلب من الشبكة أولاً، ثم الكاش
 * مناسب لصفحات HTML التي تحتاج أحدث البيانات
 */
async function networkFirst(request, cacheName) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, response.clone());
            await trimCache(cacheName, MAX_DYNAMIC_ITEMS);
        }
        return response;
    } catch (err) {
        console.warn('[SW] Network First — falling back to cache:', request.url);
        const cached = await caches.match(request);
        if (cached) return cached;

        // إذا كان الطلب لصفحة HTML، اعرض صفحة عدم الاتصال
        if (request.headers.get('accept')?.includes('text/html')) {
            return caches.match('/offline');
        }
        return new Response('Offline', { status: 503, statusText: 'Service Unavailable' });
    }
}

/**
 * Stale While Revalidate — اعرض النسخة المخزنة فوراً وحدّث في الخلفية
 * مناسب للصور والموارد التي يمكن أن تكون قديمة قليلاً
 */
async function staleWhileRevalidate(request, cacheName, maxItems) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);

    const fetchPromise = fetch(request)
        .then((response) => {
            if (response.ok) {
                cache.put(request, response.clone());
                trimCache(cacheName, maxItems);
            }
            return response;
        })
        .catch(() => cached);

    return cached || fetchPromise;
}

// ══════════════════════════════════════
//  أدوات مساعدة
// ══════════════════════════════════════

function isStaticAsset(request) {
    const url = new URL(request.url);
    return /\.(css|js|woff2?|ttf|eot)(\?.*)?$/i.test(url.pathname);
}

function isImageRequest(request) {
    const url = new URL(request.url);
    return /\.(png|jpe?g|gif|svg|webp|ico)(\?.*)?$/i.test(url.pathname);
}

/**
 * تقليم الكاش — حذف العناصر الأقدم إذا تجاوز الحد
 */
async function trimCache(cacheName, maxItems) {
    const cache = await caches.open(cacheName);
    const keys = await cache.keys();
    if (keys.length > maxItems) {
        await cache.delete(keys[0]);
        return trimCache(cacheName, maxItems);
    }
}

// ══════════════════════════════════════
//  Push Notifications (جاهز للاستخدام لاحقاً)
// ══════════════════════════════════════
self.addEventListener('push', (event) => {
    if (!event.data) return;

    const data = event.data.json();
    const options = {
        body: data.body || '',
        icon: '/icons/icon-192x192.png',
        badge: '/icons/icon-72x72.png',
        dir: 'rtl',
        lang: 'ar',
        vibrate: [100, 50, 100],
        data: {
            url: data.url || '/',
        },
        actions: data.actions || [],
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'Shipping Gateway', options)
    );
});

// فتح الرابط عند النقر على الإشعار
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                // إذا كان هناك نافذة مفتوحة، انتقل إليها
                for (const client of clientList) {
                    if (client.url === url && 'focus' in client) {
                        return client.focus();
                    }
                }
                // وإلا افتح نافذة جديدة
                if (clients.openWindow) {
                    return clients.openWindow(url);
                }
            })
    );
});

// ══════════════════════════════════════
//  Background Sync (جاهز للاستخدام لاحقاً)
// ══════════════════════════════════════
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-shipments') {
        event.waitUntil(syncShipments());
    }
});

async function syncShipments() {
    // يمكن إضافة منطق المزامنة هنا لاحقاً
    console.log('[SW] Background sync: shipments');
}
