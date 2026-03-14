<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>غير متصل — Shipping Gateway</title>
    <style>
        :root {
            --bg: #0B0F1A;
            --sf: #111827;
            --cd: #1A2035;
            --bd: #1F2A40;
            --pr: #3B82F6;
            --tx: #F1F5F9;
            --tm: #94A3B8;
            --td: #64748B;
            --radius: 14px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: var(--bg);
            font-family: 'Segoe UI', Tahoma, sans-serif;
            color: var(--tx);
            direction: rtl;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .offline-container {
            text-align: center;
            padding: 40px 24px;
            max-width: 480px;
        }
        .offline-icon {
            width: 120px;
            height: 120px;
            margin: 0 auto 28px;
            background: var(--cd);
            border: 2px solid var(--bd);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: pulse 2s ease-in-out infinite;
        }
        .offline-icon svg {
            width: 56px;
            height: 56px;
            fill: none;
            stroke: var(--pr);
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.8; }
        }
        h1 {
            font-size: 24px;
            margin-bottom: 12px;
            color: var(--tx);
        }
        p {
            color: var(--tm);
            font-size: 14px;
            line-height: 1.7;
            margin-bottom: 28px;
        }
        .retry-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            background: var(--pr);
            color: #fff;
            border: none;
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.2s;
        }
        .retry-btn:hover {
            background: #2563EB;
            transform: translateY(-1px);
        }
        .retry-btn:active {
            transform: translateY(0);
        }
        .cached-pages {
            margin-top: 36px;
            padding-top: 24px;
            border-top: 1px solid var(--bd);
        }
        .cached-pages h3 {
            font-size: 13px;
            color: var(--td);
            margin-bottom: 12px;
        }
        .cached-links {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
        }
        .cached-links a {
            display: inline-block;
            padding: 6px 14px;
            background: var(--cd);
            border: 1px solid var(--bd);
            border-radius: 8px;
            color: var(--pr);
            font-size: 12px;
            text-decoration: none;
            transition: all 0.15s;
        }
        .cached-links a:hover {
            border-color: var(--pr);
            background: rgba(59,130,246,0.1);
        }
        .status-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #EF4444;
            margin-left: 8px;
            animation: blink 1.5s ease-in-out infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
    </style>
</head>
<body>
    <div class="offline-container">
        <div class="offline-icon">
            <svg viewBox="0 0 24 24">
                <line x1="1" y1="1" x2="23" y2="23"></line>
                <path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"></path>
                <path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"></path>
                <path d="M10.71 5.05A16 16 0 0 1 22.56 9"></path>
                <path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"></path>
                <path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path>
                <line x1="12" y1="20" x2="12.01" y2="20"></line>
            </svg>
        </div>

        <h1>
            <span class="status-dot"></span>
            لا يوجد اتصال بالإنترنت
        </h1>

        <p>
            يبدو أنك غير متصل بالإنترنت حالياً.
            <br>تحقق من اتصالك وحاول مرة أخرى.
        </p>

        <button class="retry-btn" onclick="window.location.reload()">
            ↻ إعادة المحاولة
        </button>

        <div class="cached-pages" id="cached-section" style="display:none">
            <h3>الصفحات المتاحة دون اتصال:</h3>
            <div class="cached-links" id="cached-links"></div>
        </div>
    </div>

    <script>
        // عرض الصفحات المخزنة مؤقتاً
        if ('caches' in window) {
            caches.open('dynamic-sg-v1.0.0').then(cache => {
                cache.keys().then(keys => {
                    const htmlPages = keys.filter(req =>
                        req.url.includes(location.origin) &&
                        !req.url.includes('/api/') &&
                        !req.url.match(/\.(css|js|png|jpg|svg|ico|woff)/)
                    );
                    if (htmlPages.length > 0) {
                        document.getElementById('cached-section').style.display = 'block';
                        const container = document.getElementById('cached-links');
                        const routeNames = {
                            '/': '🏠 لوحة التحكم',
                            '/shipments': '📦 الشحنات',
                            '/orders': '🛒 الطلبات',
                            '/stores': '🏪 المتاجر',
                            '/wallet': '💰 المحفظة',
                            '/users': '👥 المستخدمين',
                            '/support': '🎧 الدعم',
                            '/notifications': '🔔 الإشعارات',
                            '/settings': '⚙ الإعدادات',
                        };
                        htmlPages.forEach(req => {
                            const path = new URL(req.url).pathname;
                            const a = document.createElement('a');
                            a.href = path;
                            a.textContent = routeNames[path] || path;
                            container.appendChild(a);
                        });
                    }
                });
            });
        }

        // التحقق التلقائي من عودة الاتصال
        window.addEventListener('online', () => {
            window.location.reload();
        });
    </script>
</body>
</html>
