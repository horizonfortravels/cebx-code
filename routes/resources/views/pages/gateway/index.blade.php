<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CBEX Group — بوابة إدارة الشحن</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @include('components.pwa-meta')
    <style>
        .gateway-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0B0F1A 0%, #0F172A 50%, #131B2E 100%);
            flex-direction: column;
            padding: 40px 20px;
        }
        .gateway-icon {
            width: 88px;
            height: 88px;
            border-radius: 22px;
            object-fit: contain;
            margin-bottom: 24px;
            filter: drop-shadow(0 8px 30px rgba(99, 102, 241, 0.4));
            transition: transform 0.3s ease;
        }
        .gateway-icon:hover {
            transform: scale(1.05);
        }
        .gateway-title {
            color: var(--tx, #F1F5F9);
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .gateway-subtitle {
            color: var(--td, #64748B);
            font-size: 13px;
            margin-bottom: 40px;
        }
        .gateway-cards {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            justify-content: center;
            max-width: 700px;
        }
        .gateway-card {
            background: var(--cd, #1A2035);
            border: 1px solid var(--bd, #1F2A40);
            border-radius: 16px;
            padding: 28px 24px;
            width: 200px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: block;
        }
        .gateway-card:hover {
            border-color: var(--pr, #3B82F6);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.15);
        }
        .gateway-card-icon {
            font-size: 32px;
            margin-bottom: 12px;
        }
        .gateway-card-name {
            color: var(--tx, #F1F5F9);
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .gateway-card-desc {
            color: var(--td, #64748B);
            font-size: 11px;
        }
    </style>
</head>
<body>
    <div class="gateway-page">

        {{-- ═══ CBEX Gateway Icon ═══ --}}
        <img src="{{ asset('images/gateway-icon-xl.png') }}"
             alt="CBEX Group"
             class="gateway-icon"
             onerror="this.style.display='none'">

        <h1 class="gateway-title">CBEX Shipping Gateway</h1>
        <p class="gateway-subtitle">اختر بوابتك للدخول إلى نظام إدارة الشحن</p>

        {{-- ═══ Gateway Cards ═══ --}}
        <div class="gateway-cards">
            <a href="{{ url('/b2b/login') }}" class="gateway-card">
                <div class="gateway-card-icon">🏢</div>
                <div class="gateway-card-name">B2B Portal</div>
                <div class="gateway-card-desc">بوابة الأعمال والشركات</div>
            </a>

            <a href="#" class="gateway-card" style="opacity: 0.5; pointer-events: none;">
                <div class="gateway-card-icon">🛒</div>
                <div class="gateway-card-name">B2C Portal</div>
                <div class="gateway-card-desc">بوابة العملاء — قريباً</div>
            </a>

            <a href="#" class="gateway-card" style="opacity: 0.5; pointer-events: none;">
                <div class="gateway-card-icon">🔑</div>
                <div class="gateway-card-name">Admin Portal</div>
                <div class="gateway-card-desc">لوحة الإدارة — قريباً</div>
            </a>
        </div>

    </div>

    <script src="{{ asset('js/pwa.js') }}"></script>
</body>
</html>
