/**
 * ═══════════════════════════════════════════════════════
 *  Shipping Gateway — PWA Registration & Helpers
 * ═══════════════════════════════════════════════════════
 *  يُضاف هذا الملف في public/js/pwa.js
 *  ويتم تحميله في layout الرئيسي
 */

(function () {
    'use strict';

    // ══════════════════════════════════════
    //  1. تسجيل Service Worker
    // ══════════════════════════════════════
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', async () => {
            try {
                const registration = await navigator.serviceWorker.register('/sw.js', {
                    scope: '/',
                });
                console.log('[PWA] Service Worker registered:', registration.scope);

                // التحقق من التحديثات
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    console.log('[PWA] New Service Worker installing...');

                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            // يوجد تحديث جديد — إظهار رسالة للمستخدم
                            showUpdateNotification();
                        }
                    });
                });
            } catch (err) {
                console.error('[PWA] Service Worker registration failed:', err);
            }
        });
    }

    // ══════════════════════════════════════
    //  2. إشعار التحديث
    // ══════════════════════════════════════
    function showUpdateNotification() {
        // إنشاء شريط إشعار التحديث
        const bar = document.createElement('div');
        bar.id = 'pwa-update-bar';
        bar.innerHTML = `
            <div style="
                position: fixed; bottom: 0; left: 0; right: 0; z-index: 9999;
                background: linear-gradient(135deg, #3B82F6, #2563EB);
                color: #fff; padding: 14px 20px;
                display: flex; align-items: center; justify-content: space-between;
                font-family: 'Segoe UI', Tahoma, sans-serif; font-size: 13px;
                box-shadow: 0 -4px 20px rgba(0,0,0,0.3);
                direction: rtl;
            ">
                <span>🔄 يتوفر تحديث جديد للتطبيق</span>
                <div style="display:flex; gap:8px;">
                    <button onclick="window.location.reload()" style="
                        background: #fff; color: #3B82F6; border: none;
                        padding: 6px 16px; border-radius: 8px; font-weight: 600;
                        cursor: pointer; font-size: 12px; font-family: inherit;
                    ">تحديث الآن</button>
                    <button onclick="this.closest('#pwa-update-bar').remove()" style="
                        background: rgba(255,255,255,0.2); color: #fff; border: none;
                        padding: 6px 12px; border-radius: 8px; cursor: pointer;
                        font-size: 12px; font-family: inherit;
                    ">لاحقاً</button>
                </div>
            </div>
        `;
        document.body.appendChild(bar);
    }

    // ══════════════════════════════════════
    //  3. مراقبة حالة الاتصال
    // ══════════════════════════════════════
    let offlineBar = null;

    function showOfflineBar() {
        if (offlineBar) return;
        offlineBar = document.createElement('div');
        offlineBar.id = 'pwa-offline-bar';
        offlineBar.innerHTML = `
            <div style="
                position: fixed; top: 0; left: 0; right: 0; z-index: 9999;
                background: #EF4444; color: #fff; padding: 8px 16px;
                text-align: center; font-size: 12px; font-weight: 600;
                font-family: 'Segoe UI', Tahoma, sans-serif;
                direction: rtl;
                animation: slideDown 0.3s ease;
            ">
                ⚡ أنت غير متصل بالإنترنت — يتم عرض النسخة المخزنة
            </div>
        `;
        document.body.appendChild(offlineBar);
    }

    function hideOfflineBar() {
        if (offlineBar) {
            offlineBar.remove();
            offlineBar = null;
        }
    }

    window.addEventListener('online', () => {
        hideOfflineBar();
        console.log('[PWA] Connection restored');
    });

    window.addEventListener('offline', () => {
        showOfflineBar();
        console.log('[PWA] Connection lost');
    });

    // التحقق الأولي
    if (!navigator.onLine) {
        showOfflineBar();
    }

    // ══════════════════════════════════════
    //  4. زر التثبيت (Install Prompt)
    // ══════════════════════════════════════
    let deferredPrompt = null;

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        showInstallButton();
    });

    function showInstallButton() {
        // التحقق من عدم وجود الزر مسبقاً
        if (document.getElementById('pwa-install-btn')) return;

        const btn = document.createElement('button');
        btn.id = 'pwa-install-btn';
        btn.innerHTML = '📲 تثبيت التطبيق';
        btn.title = 'تثبيت Shipping Gateway كتطبيق';
        Object.assign(btn.style, {
            position: 'fixed',
            bottom: '20px',
            left: '20px',
            zIndex: '9998',
            background: 'linear-gradient(135deg, #3B82F6, #8B5CF6)',
            color: '#fff',
            border: 'none',
            padding: '10px 20px',
            borderRadius: '12px',
            fontSize: '13px',
            fontWeight: '600',
            cursor: 'pointer',
            fontFamily: "'Segoe UI', Tahoma, sans-serif",
            boxShadow: '0 4px 15px rgba(59,130,246,0.4)',
            transition: 'all 0.2s',
            direction: 'rtl',
        });

        btn.addEventListener('mouseenter', () => {
            btn.style.transform = 'translateY(-2px)';
            btn.style.boxShadow = '0 6px 20px rgba(59,130,246,0.5)';
        });
        btn.addEventListener('mouseleave', () => {
            btn.style.transform = 'translateY(0)';
            btn.style.boxShadow = '0 4px 15px rgba(59,130,246,0.4)';
        });

        btn.addEventListener('click', async () => {
            if (!deferredPrompt) return;
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            console.log('[PWA] Install prompt outcome:', outcome);
            deferredPrompt = null;
            btn.remove();
        });

        document.body.appendChild(btn);
    }

    // إخفاء زر التثبيت بعد التثبيت
    window.addEventListener('appinstalled', () => {
        console.log('[PWA] App installed successfully');
        deferredPrompt = null;
        const btn = document.getElementById('pwa-install-btn');
        if (btn) btn.remove();
    });

    // ══════════════════════════════════════
    //  5. CSS Animation (inject once)
    // ══════════════════════════════════════
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideDown {
            from { transform: translateY(-100%); }
            to   { transform: translateY(0); }
        }
    `;
    document.head.appendChild(style);

})();
