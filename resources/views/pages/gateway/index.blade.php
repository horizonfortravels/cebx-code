<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CBEX Group — بوابة إدارة الشحن</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @if(View::exists('components.pwa-meta'))
        @include('components.pwa-meta')
    @endif
    <style>
        .gateway-page {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: linear-gradient(160deg, #0B0F1A 0%, #0F172A 40%, #131B2E 100%);
            padding: 40px 20px;
            text-align: center;
        }

        /* ── Main Logo ── */
        .gateway-main-logo {
            width: 72px;
            height: 72px;
            border-radius: 18px;
            margin-bottom: 20px;
            filter: drop-shadow(0 6px 24px rgba(99, 102, 241, 0.35));
        }
        .gateway-title {
            color: #F1F5F9;
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .gateway-subtitle {
            color: #64748B;
            font-size: 13px;
            margin-bottom: 48px;
        }

        /* ── Portal Cards Grid ── */
        .portal-grid {
            display: flex;
            gap: 24px;
            justify-content: center;
            flex-wrap: wrap;
            max-width: 960px;
        }

        /* ── Individual Portal Card ── */
        .portal-card {
            width: 260px;
            border-radius: 20px;
            padding: 36px 24px 32px;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .portal-card::before {
            content: '';
            position: absolute;
            inset: 0;
            opacity: 0;
            border-radius: 20px;
            box-shadow: 0 0 40px rgba(255,255,255,0.1);
            transition: opacity 0.3s ease;
        }
        .portal-card:hover {
            transform: translateY(-6px);
        }
        .portal-card:hover::before {
            opacity: 1;
        }

        /* ── B2B Theme ── */
        .portal-b2b {
            background: linear-gradient(160deg, #1E3A5F 0%, #1E40AF 50%, #3B82F6 100%);
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        .portal-b2b:hover { box-shadow: 0 12px 40px rgba(59, 130, 246, 0.25); }

        /* ── B2C Theme ── */
        .portal-b2c {
            background: linear-gradient(160deg, #064E3B 0%, #0D6B4F 50%, #10B981 100%);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .portal-b2c:hover { box-shadow: 0 12px 40px rgba(16, 185, 129, 0.25); }

        /* ── SYS Theme ── */
        .portal-sys {
            background: linear-gradient(160deg, #3B1A6E 0%, #581CC3 50%, #8B5CF6 100%);
            border: 1px solid rgba(139, 92, 246, 0.3);
        }
        .portal-sys:hover { box-shadow: 0 12px 40px rgba(139, 92, 246, 0.25); }

        /* ── Portal Icon ── */
        .portal-icon {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            margin: 0 auto 16px;
            filter: drop-shadow(0 6px 20px rgba(0, 0, 0, 0.3));
            transition: transform 0.3s ease;
        }
        .portal-card:hover .portal-icon {
            transform: scale(1.08);
        }

        /* ── Portal Badge ── */
        .portal-badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 6px;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 16px;
        }
        .portal-b2b .portal-badge {
            background: rgba(59, 130, 246, 0.25);
            color: #93C5FD;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        .portal-b2c .portal-badge {
            background: rgba(16, 185, 129, 0.25);
            color: #6EE7B7;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .portal-sys .portal-badge {
            background: rgba(139, 92, 246, 0.25);
            color: #C4B5FD;
            border: 1px solid rgba(139, 92, 246, 0.3);
        }

        /* ── Portal Text ── */
        .portal-name {
            color: #fff;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .portal-desc {
            color: rgba(255, 255, 255, 0.6);
            font-size: 12px;
            line-height: 1.6;
        }

        /* ── Disabled State ── */
        .portal-card.disabled {
            opacity: 0.45;
            pointer-events: none;
        }
        .portal-card.disabled .portal-badge::after {
            content: ' — قريباً';
        }

        /* ── Responsive ── */
        @media (max-width: 860px) {
            .portal-grid { flex-direction: column; align-items: center; }
            .portal-card { width: 100%; max-width: 320px; }
        }
    </style>
</head>
<body>
    <div class="gateway-page">

        {{-- ═══ Main CBEX Logo (from PWA icons) ═══ --}}
        <img src="{{ asset('images/gateway-icon-xl.png') }}"
             alt="CBEX Group"
             class="gateway-main-logo"
             onerror="this.style.display='none'">

        <h1 class="gateway-title">CBEX Shipping Gateway</h1>
        <p class="gateway-subtitle">اختر بوابتك للدخول إلى نظام إدارة الشحن</p>

        {{-- ═══ Portal Cards ═══ --}}
        <div class="portal-grid">

            {{-- ── B2B — بوابة الأعمال ── --}}
            <a href="{{ url('/b2b/login') }}" class="portal-card portal-b2b">
                <img src="{{ asset('images/portal-b2b-xl.png') }}"
                     alt="CBEX B2B"
                     class="portal-icon">
                <div class="portal-badge">BUSINESS PORTAL</div>
                <div class="portal-name">بوابة الأعمال</div>
                <div class="portal-desc">
                    منصة متكاملة لإدارة شحنات شركتك — ربط المتاجر، إدارة
                    الطلبات، التتبع المباشر والتقارير المالية.
                </div>
            </a>

            {{-- ── B2C — بوابة الأفراد ── --}}
            <a href="#" class="portal-card portal-b2c disabled">
                <img src="{{ asset('images/portal-b2c-xl.png') }}"
                     alt="CBEX B2C"
                     class="portal-icon">
                <div class="portal-badge">PERSONAL SHIPPING</div>
                <div class="portal-name">بوابة الأفراد</div>
                <div class="portal-desc">
                    أرسل واستلم شحناتك الشخصية بكل سهولة — تتبع
                    مباشر، دفتر عناوين، ومحفظة إلكترونية.
                </div>
            </a>

            {{-- ── SYS — لوحة الإدارة ── --}}
            <a href="#" class="portal-card portal-sys disabled">
                <img src="{{ asset('images/portal-sys-xl.png') }}"
                     alt="CBEX Admin"
                     class="portal-icon">
                <div class="portal-badge">SYSTEM ADMIN</div>
                <div class="portal-name">لوحة الإدارة</div>
                <div class="portal-desc">
                    التحكم الكامل بالنظام — إدارة المنظمات، اللوجستيات،
                    الامتثال، التسعير والتدقيق.
                </div>
            </a>

        </div>
    </div>

    @if(file_exists(public_path('js/pwa.js')))
        <script src="{{ asset('js/pwa.js') }}"></script>
    @endif
</body>
</html>
